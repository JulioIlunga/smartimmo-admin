<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\PaymentForMembership;
use App\Repository\PaymentForMembershipRepository;
use App\Repository\PaymentRepository;
use App\Service\CreditWalletManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\s;

final class ManualTransactionCheckController extends AbstractController
{
    private const SUCCESS_CODE = '1';
    /** @var string[] */
    private const CANCEL_CODES = ['6', '7', '9'];

    #[Route('/transaction/check/manually/status/{code}', name: 'app_check_manually_transaction_status', methods: ['GET','POST'])]
    public function checkManually(string $code, EntityManagerInterface $em, PaymentForMembershipRepository $pfmRepo, PaymentRepository $paymentRepo, CreditWalletManager $walletManager, HttpClientInterface $httpClient, ParameterBagInterface $params): Response {
        $dirty   = false;
        $handled = false;

        /** @var PaymentForMembership|null $pfm */
        $pfm = $pfmRepo->findOneBy(['code' => $code]);

        /** @var Payment|null $payment */
        $payment = $pfm ? null : $paymentRepo->findOneBy(['code' => $code]); // only search Payment if not membership

        if (!$pfm && !$payment) {
            return $this->json(['error' => 'Reference not found'], Response::HTTP_NOT_FOUND);
        }

        // Idempotency shortcut (optional but useful)
        if ($pfm && $pfm->isStatus() === true) {
            return $this->json(['status' => 'already_processed', 'type' => 'membership']);
        }
        if ($payment && ($payment->isStatus() === true || $payment->isCancel() === true)) {
            return $this->json(['status' => 'already_processed', 'type' => 'reservation']);
        }

        // Build upstream endpoint
        $baseUrl  = (string) $params->get('smartPay_BasUrl'); // verify your parameter name
        $endpoint = rtrim($baseUrl, '/').'/api/check/transaction/'.($pfm?->getCode() ?? $payment?->getCode());

        // Call provider
        try {
            $resp = $httpClient->request('GET', $endpoint);
        } catch (TransportExceptionInterface $e) {
            return $this->json(['error' => 'Upstream transport error'], Response::HTTP_BAD_GATEWAY);
        }

        $httpCode = $resp->getStatusCode();
        if ($httpCode !== 200 && $httpCode !== 201) {
            return $this->json(['error' => 'Upstream error', 'http_code' => $httpCode], Response::HTTP_BAD_GATEWAY);
        }

        $json = json_decode($resp->getContent(false), true);
        if (!is_array($json)) {
            return $this->json(['error' => 'Invalid upstream JSON'], Response::HTTP_BAD_GATEWAY);
        }

        // Some providers send "status" instead of "code"
        $providerCode = (string) ($json['code'] ?? ($json['status'] ?? ''));

        // === Membership branch ===
        if ($pfm) {
            $handled = true;

            if ($providerCode === self::SUCCESS_CODE) {
                $this->applySuccessfulMembershipPayment($pfm, $walletManager);
                $dirty = true;

            } elseif (in_array($providerCode, self::CANCEL_CODES, true)) {
                // If you keep a cancel flag on PFM, set it here (optional)
                // $pfm->setCanceled(true);
                $dirty = true;
            }
        }

        // === Reservation/Invoice branch ===
        if ($payment) {
            $handled = true;

            if ($providerCode === self::SUCCESS_CODE) {
                $this->applySuccessfulReservationPayment($payment);
                $dirty = true;

            } elseif (in_array($providerCode, self::CANCEL_CODES, true)) {
                $payment->setCancel(true);
                $dirty = true;
            }
        }

        if ($dirty) {
            $em->flush();
        }

        return $this->json([
            'status'        => 'ok',
            'handled'       => $handled,
            'provider_code' => $providerCode,
            'type'          => $pfm ? 'membership' : 'reservation',
        ]);
    }

    /**
     * Apply effects of a SUCCESSFUL membership payment (manual check path).
     * - Idempotent (won’t run twice if already processed)
     * - Handles new vs. renewal (extends from current end when renewing)
     * - Resets claims counters and sets monthly claim limit
     * - Adds monthly credits once to the user's wallet
     */
    private function applySuccessfulMembershipPayment(
        PaymentForMembership $pfm,
        CreditWalletManager $walletManager
    ): void {
        // Idempotency guard
        if ($pfm->isStatus() === true) {
            return;
        }

        $pfm->setStatus(true);

        $subscription = $pfm->getSubscription();
        $user         = $pfm->getUser();
        if (!$subscription || !$user) {
            return;
        }

        $now  = new \DateTimeImmutable('now');
        $plan = $subscription->getPlan();

        // Determine renew vs new:
        // Prefer explicit flag if your entity has it (e.g., $pfm->isRenew()),
        // otherwise infer from current status + future period end.
        $explicitRenew = method_exists($pfm, 'isRenew') ? (bool) $pfm->isRenew() : null;
        $currentEnd    = $subscription->getCurrentPeriodEnd();
        $isActive      = $subscription->getStatus() === 'active';
        $isRenew       = $explicitRenew ?? ($isActive && $currentEnd instanceof \DateTimeInterface && $currentEnd >= $now);

        // Anchor for the new period: extend from the later of now or the current end (for renewals)
        $periodStart = ($isRenew && $currentEnd instanceof \DateTimeInterface && $currentEnd > $now)
            ? \DateTimeImmutable::createFromMutable(new \DateTime($currentEnd->format('c')))
            : $now;

        $periodEnd = $periodStart->modify('+1 month');

        // Monthly claim limit from plan
        $monthlyCredits = 0;
        if ($plan) {
            if (method_exists($plan, 'getMonthlyCredit')) {
                $monthlyCredits = (int) $plan->getMonthlyCredit();
            } elseif (method_exists($plan, 'getMonthlyCredits')) {
                $monthlyCredits = (int) $plan->getMonthlyCredits();
            }
        }

        // Activate/refresh subscription + reset quotas
        $subscription
            ->setStatus('active')
            ->setCurrentPeriodStart($periodStart)
            ->setCurrentPeriodEnd($periodEnd)
            ->setCancelAtPeriodEnd($periodEnd)
            ->setClaimsUsed(0)
            ->setClaimLimit($monthlyCredits);

        // If you keep a ManyToMany (e.g., addAgent), ensure user is linked (safe if addAgent dedupes)
        if (method_exists($subscription, 'addAgent')) {
            $subscription->addAgent($user);
        }

        // If your User holds the FK to the active subscription, switch references safely
        if (method_exists($user, 'getSubscriptions') && method_exists($user, 'setSubscriptions')) {
            $currentSub = $user->getSubscriptions();
            if ($currentSub && $currentSub !== $subscription && $currentSub->getStatus() === 'active') {
                // Soft-cancel the previous active sub if switching plan
                $currentSub->setStatus('canceled');
            }
            $user->setSubscriptions($subscription);
        }

        // Ensure wallet exists; add monthly credits exactly once (per successful payment)
        if (method_exists($user, 'getCreditWallet')) {
            $wallet = $user->getCreditWallet();
            if (!$wallet) {
                // Create if missing (adapt to your manager’s API)
                $wallet = $walletManager->ensureWallet($user);
            }
            if ($wallet && $monthlyCredits > 0) {
                $walletManager->addCredits($wallet, $monthlyCredits);
            }
        }

        // NOTE: Flush outside this method (in the controller/service that calls it).
    }




    /**
     * Success for reservation: confirm, mark invoice paid/closed properly
     */
    private function applySuccessfulReservationPayment(Payment $payment): void
    {
        $invoice     = $payment->getInvoice();
        $reservation = $invoice->getReservation();

        // Confirm reservation and mark invoice paid
        $reservation->setConfirmed(true);
        $invoice->setPaid(true);

        $amountPaid = (float) $invoice->getAmountPaid();
        $newAmount  = (float) $payment->getAmount();
        $totalDue   = (float) $invoice->getAmount();
        $payType    = (string) $reservation->getPayementType(); // 'full' or partial

        if ($payType === 'full') {
            $invoice
                ->setAmountPaid($newAmount)
                ->setClosed(true);
            $payment->setStatus(true);
            return;
        }

        // partial
        $updatedPaid = $amountPaid + $newAmount;
        $invoice->setAmountPaid($updatedPaid);
        $payment->setStatus(true);

        if ($updatedPaid >= $totalDue) {
            $invoice->setClosed(true);
        }
    }

    #[Route('/transaction/payment/status/{code}', name: 'api_payment_status', methods: ['GET'])]
    public function paymentStatus(string $code, PaymentForMembershipRepository $membershipRepo, PaymentRepository $paymentRepo): JsonResponse
    {
        // 1) Try membership upgrade payment
        if ($pfm = $membershipRepo->findOneBy(['code' => $code])) {
            // NOTE: adjust to getStatus() if your accessor isn't isStatus()
            $ok = (bool) $pfm->isStatus();

            return new JsonResponse([
                'type'   => 'membership',
                'code'   => $code,
                'status' => $ok ? 'true' : 'false',
            ]);
        }

        // 2) Try reservation/invoice payment
        if ($payment = $paymentRepo->findOneBy(['code' => $code])) {
            // NOTE: adjust to getStatus() if your accessor isn't isStatus()
            $ok = (bool) $payment->isStatus();

            return new JsonResponse([
                'type'   => 'invoice',
                'code'   => $code,
                'status' => $ok ? 'true' : 'false',
            ]);
        }

        // 3) Not found
        return new JsonResponse([
            'type'   => 'unknown',
            'code'   => $code,
            'status' => 'false',
            'error'  => 'Reference not found',
        ], 404);
    }
}
