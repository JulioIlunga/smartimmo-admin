<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\PaymentForMembership;
use App\Repository\MembershipPlansRepository;
use App\Repository\PaymentRepository;
use App\Repository\PaymentForMembershipRepository;
use App\Repository\PaymentForPreferenceRepository;

// kept since you had it injected
use App\Repository\SubscriptionsRepository;
use App\Service\CreditWalletManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CallbackTransactionController extends AbstractController {
    private const SUCCESS_CODE = '1';
    /** @var string[] */
    private const CANCEL_CODES = ['6', '7', '9'];

    #[Route('/transaction/callback/response', name: 'app_transaction_callback', methods: ['POST'])]
    public function callback(
        Request $request,
        EntityManagerInterface $em,
        PaymentRepository $paymentRepository,
        PaymentForMembershipRepository $paymentForMembershipRepository,
        PaymentForPreferenceRepository $paymentForPreferenceRepository, CreditWalletManager $creditWalletManager
    ): Response {
        // 1) Parse & validate JSON
        $json = json_decode($request->getContent(), true);
        if (!is_array($json)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $reference = (string)($json['reference'] ?? '');
        $code      = (string)($json['code'] ?? ($json['status'] ?? '')); // some providers use "status"

        if ($reference === '' || $code === '') {
            return $this->json(['error' => 'Missing reference or code'], Response::HTTP_BAD_REQUEST);
        }

        $dirty = false;
        $handled = false;

        // 2) Membership payments (including "upgrade")
        $membershipPayment = $paymentForMembershipRepository->findOneBy(['code' => $reference]);
        if ($membershipPayment instanceof PaymentForMembership) {
            $handled = true;

            if ($code === self::SUCCESS_CODE) {
                $this->applySuccessfulMembershipPayment($membershipPayment, $creditWalletManager);
                $dirty = true;
            } elseif (in_array($code, self::CANCEL_CODES, true)) {
                // If you store cancellation on this entity, set it here
                // e.g. $membershipPayment->setCanceled(true);
                // $dirty = true;
            }
        }

        // 3) Reservation payments
        $reservationPayment = $paymentRepository->findOneBy(['code' => $reference]);
        if ($reservationPayment instanceof Payment) {
            $handled = true;

            if ($code === self::SUCCESS_CODE) {
                $this->applySuccessfulReservationPayment($reservationPayment);
                $dirty = true;
            } elseif (in_array($code, self::CANCEL_CODES, true)) {
                $reservationPayment->setCancel(true);
                $dirty = true;
            }
        }

        // (Optional) 4) Preference payments — only if you actually support them.
        // $preferencePayment = $paymentForPreferenceRepository->findOneBy(['code' => $reference]);
        // if ($preferencePayment) {
        //     $handled = true;
        //     if ($code === self::SUCCESS_CODE) {
        //         // TODO: implement success logic
        //         $dirty = true;
        //     } elseif (in_array($code, self::CANCEL_CODES, true)) {
        //         // TODO: implement cancel logic
        //         $dirty = true;
        //     }
        // }

        if ($dirty) {
            $em->flush();
        }

        // If nothing matched the reference, you may want to log or return 404.
        if (!$handled) {
            // return $this->json(['error' => 'Unknown reference'], Response::HTTP_NOT_FOUND);
            // Keeping "ok" to avoid leaking info to gateway; log internally instead.
        }

        return $this->json(['status' => 'ok']);
    }

    /**
     * Handle a successful membership (upgrade/new/renewal) payment.
     * - Activates the subscription from the payment
     * - Extends the billing period correctly (renew = extend from current end, new = from now)
     * - Resets claim counters for the new period
     * - Adds monthly credits once to the user’s wallet
     */
    private function applySuccessfulMembershipPayment(
        PaymentForMembership $payment,
        CreditWalletManager $walletManager
    ): void {
        // Mark payment as successful
        $payment->setStatus(true);

        $subscription = $payment->getSubscription();
        $user         = $payment->getUser();
        if (!$subscription || !$user) {
            return; // nothing to do
        }

        $now  = new \DateTimeImmutable('now');
        $plan = $subscription->getPlan();

        // Determine renewal vs new
        // Prefer explicit flag (if you set one on payment payload), else infer by active+has future end
        $explicitRenew = method_exists($payment, 'isRenew') ? (bool) $payment->isRenew() : null;
        $currentEnd    = $subscription->getCurrentPeriodEnd();
        $isActive      = $subscription->getStatus() === 'active';
        $isRenew       = $explicitRenew ?? ($isActive && $currentEnd instanceof \DateTimeInterface && $currentEnd >= $now);

        // Choose the correct anchor for the next period
        // If renew and period not expired yet, start from current end; else from now
        $periodStart = ($isRenew && $currentEnd instanceof \DateTimeInterface && $currentEnd > $now)
            ? \DateTimeImmutable::createFromMutable((new \DateTime($currentEnd->format('c'))))
            : $now;

        $periodEnd = $periodStart->modify('+1 month');

        // Activate / refresh the subscription period
        $subscription
            ->setStatus('active')
            ->setCurrentPeriodStart($periodStart)
            ->setCurrentPeriodEnd($periodEnd)
            ->setCancelAtPeriodEnd($periodEnd);

        // Reset quotas each billing cycle
        $monthlyCredits = 0;
        if ($plan) {
            // Align the field name with your schema (monthlyCredit vs monthlyCredits)
            if (method_exists($plan, 'getMonthlyCredit')) {
                $monthlyCredits = (int) $plan->getMonthlyCredit();
            } elseif (method_exists($plan, 'getMonthlyCredits')) {
                $monthlyCredits = (int) $plan->getMonthlyCredits();
            }
        }

        $subscription
            ->setClaimsUsed(0)
            ->setClaimLimit($monthlyCredits);

        // Ensure relation user <-> subscription is set correctly
        // Avoid duplicate add
        if (method_exists($subscription, 'addAgent')) {
            // If your Subscriptions has ManyToMany with User named "agents"
            // and addAgent() guards duplicates internally, this is safe:
            $subscription->addAgent($user);
        }
        if (method_exists($user, 'getSubscriptions') && method_exists($user, 'setSubscriptions')) {
            // If your schema stores a single subscription on User:
            $currentSub = $user->getSubscriptions();

            if ($currentSub && $currentSub !== $subscription && $currentSub->getStatus() === 'active') {
                // Soft-cancel the old one (plan switch)
                $currentSub->setStatus('canceled');
            }

            // Link this (new/renewed) subscription
            $user->setSubscriptions($subscription);
        }

        // Ensure the user has a wallet, then credit monthly amount ONCE per successful payment
        if (method_exists($user, 'getCreditWallet')) {
            $wallet = $user->getCreditWallet();
            if (!$wallet) {
                $wallet = $walletManager->ensureWallet($user); // create if missing (adjust to your API)
            }
            if ($wallet && $monthlyCredits > 0) {
                $walletManager->addCredits($wallet, $monthlyCredits);
            }
        }
    }


    /**
     * Handle a successful reservation payment: mark invoice as paid/closed accordingly.
     */
    private function applySuccessfulReservationPayment(Payment $payment): void
    {
        $invoice     = $payment->getInvoice();
        $reservation = $invoice->getReservation();

        // Confirm reservation
        $reservation->setConfirmed(true);

        // Mark invoice paid state
        $invoice->setPaid(true);

        $amountPaid = (float) $invoice->getAmountPaid();
        $newAmount  = (float) $payment->getAmount();
        $totalDue   = (float) $invoice->getAmount();
        $payType    = (string) $reservation->getPayementType(); // 'full' or partial

        if ($payType === 'full') {
            // Full payment: close invoice fully
            $invoice
                ->setAmountPaid($newAmount)
                ->setClosed(true);
            return;
        }

        // Partial payments: accumulate paid amount
        $updatedPaid = $amountPaid + $newAmount;
        $invoice->setAmountPaid($updatedPaid);

        if ($updatedPaid >= $totalDue) {
            $invoice->setClosed(true);
        }
    }
}
