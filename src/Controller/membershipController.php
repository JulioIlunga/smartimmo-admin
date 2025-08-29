<?php

namespace App\Controller;

use App\Entity\CreditWallet;
use App\Entity\Payment;
use App\Entity\PaymentForMembership;
use App\Entity\Subscriptions;
use App\Entity\MembershipPlans;
use App\Entity\User;
use App\Repository\MembershipPlansRepository;
use App\Repository\PaymentForMembershipRepository;
use App\Repository\SubscriptionsRepository;
use App\Repository\UserRepository;
use App\Service\CreditWalletManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/membership', name: 'membership.')]
class membershipController extends AbstractController
{
    public function __construct(private ManagerRegistry $doctrine, HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/dashboard/manage', name: 'membership.manage', methods: ['GET'])]
    public function manage(SubscriptionsRepository $subsRepo, MembershipPlansRepository $plansRepo): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Try from relation first (recommended), fallback to repo lookup.
        $subs = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;
        if (!$subs) {
            $subs = $subsRepo->findOneBy(['user' => $user, 'status' => 'active']);
        }

        $wallet = method_exists($user, 'getCreditWallet') ? $user->getCreditWallet() : null;

        // Optional: show available plans (e.g., to upgrade or subscribe if none)
        $plans = $plansRepo->findBy([], ['monthlyPrice' => 'ASC']);

        // Compute usage % safely
        $claimLimit = $subs ? (int)($subs->getClaimLimit() ?? 0) : 0;
        $claimsUsed = $subs ? (int)($subs->getClaimsUsed() ?? 0) : 0;
        $pct = $claimLimit > 0 ? min(100, (int)floor(($claimsUsed / $claimLimit) * 100)) : 0;

        $m = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;

        return $this->render('agent_work_space/job_opportunity/manage.html.twig', [
            'user' => $user,
            'subs' => $subs,
            'wallet' => $wallet,
            'plans' => $plans,
            'pct' => $pct,
            'membership' => $m,
        ]);
    }

    /**
     * Step 1: Confirmation page
     * GET /membership/upgrade/{planId}
     */
    #[Route('/membership/upgrade/{planId}', name: 'membership.upgrade_confirm')]
    public function confirm(int $planId, MembershipPlansRepository $planRepo, UserRepository $userRepository): Response {
        $user = $userRepository->find($this->getUser());
        $targetPlan = $planRepo->find($planId);

        if (!$targetPlan) {
            throw $this->createNotFoundException('Plan introuvable.');
        }

        // Your way to get the active membership:
        /** @var MembershipPlans|null $membership */
        $membership = $user->getSubscriptions()->getPlan() ? : null;

        // Load all plans in ascending order (must be implemented in your repo)
        $plans = $planRepo->findAllOrderedAsc();

        // Determine current plan index and next allowed index
        $currentPlanId = $membership?->getId();
        $currentIndex  = -1;
        foreach ($plans as $i => $p) {
            if ($p->getId() === $currentPlanId) {
                $currentIndex = $i;
                break;
            }
        }

        $allowedIndex = ($currentIndex === -1) ? 0 : $currentIndex + 1;

        // Only allow upgrading to the very next plan
        $targetIndex = array_search($targetPlan, $plans, true);
        if ($targetIndex === false || $targetIndex !== $allowedIndex) {
            // Block direct URL jumps to higher tiers
            throw new AccessDeniedHttpException('Mise à niveau non autorisée pour ce plan.');
        }

        return $this->render('agent_work_space/job_opportunity/upgrade.html.twig', [
            'membership'  => $membership,
            'currentPlan' => $membership,
            'targetPlan'  => $targetPlan,
        ]);
    }

    #[Route('/payement/for/upgrade/membership', name: 'app_payment_for_upgrade_membership', methods: ['POST'])]
    public function index(Request $request, MembershipPlansRepository $membershipPlansRepository, EntityManagerInterface $entityManager, CreditWalletManager $creditWalletManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $plan = $membershipPlansRepository->findOneBy(['id' => $data['membership_id'] ]);

        if (!$data) {
            return new JsonResponse(['message' => 'Données invalides'], 400);
        }

        $em = $this->doctrine->getManager();
        $user = $em->getRepository(User::class)->find($this->getUser());

        // If user already has a subscription
        $current = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;

        if ($current && $current->getStatus() === 'active' && $current->getPlan() && $current->getPlan()->getId() === $plan->getId()) {
            return new JsonResponse(['message' => 'Vous êtes déjà abonné à ce plan.'], 400);
        }

        // If switching plan, cancel previous (soft-cancel: turn off autorenew)
        if ($current && $current->getStatus() === 'active') {
            $current->setStatus('canceled'); // or 'past_due' / 'canceled_at_period_end' depending on your logic
            $em->persist($current);
        }

        // Create a new subscription
        $subscription = new Subscriptions();
        $subscription
            ->setCode('upgrade-sub-'.uniqid())
            ->setPlan($plan)
            ->setStatus('incomplete')
            ->setClaimsUsed((int)0)
            ->setClaimLimit((int)$plan->getMonthlyCredit() ?? 0)
            ->setCurrentPeriodStart(new \DateTimeImmutable('now'))
            ->setCurrentPeriodEnd(new \DateTimeImmutable('+1 month'))
            ->setCancelAtPeriodEnd(new \DateTimeImmutable('+1 month'));

        $em->persist($subscription);
        $em->flush();

        try {
            $payment = new PaymentForMembership();
            $payment
                ->setCode('Sub-' . uniqid())
                ->setSubscription($subscription)
                ->setAmount($plan->getMonthlyPrice())
                ->setStatus(false)
                ->setUser($this->getUser());

            $entityManager->persist($payment);
            $entityManager->flush();

            $baseUrl = $this->getParameter('smartPay_BasUrl');
            $onlinePaymentUrl = $this->makeRequestUpgradeMembershipPaymentLink($baseUrl, $payment);
            if (!$onlinePaymentUrl) {
                throw new \Exception('Erreur lors de la création du lien de paiement');
            }

            return new JsonResponse(
                [
                    'smart_pay_url' => $onlinePaymentUrl,
                    'paymentCode' => $payment->getCode(),
                ], 200, [
                "Content-Type" => "application/json"
            ]);

        } catch (\Exception $e) {

            return new JsonResponse([
                'message' => $e->getMessage()
            ], 400, [
                "Content-Type" => "application/json"
            ]);
        }
    }

    public function makeRequestUpgradeMembershipPaymentLink($baseUrl, PaymentForMembership $payment)
    {
        $redirectionToPaymentUrl = null;
        $SmartPayKey = $this->getParameter('smartPay_Key');

        $response = $this->client->request('POST', $baseUrl . '/api/transaction', [
            'json' => [
                "merchantCode" => $SmartPayKey,
                "code" => $payment->getCode(),
                "amount" => strval($payment->getAmount()),
                "currency" => 'USD',
                "phone" => $payment->getUser()->getPhone(),
                "description" => 'Paiement pour l\'abonnement agent de ' . $payment->getUser()->getFirstname() . ' ' . $payment->getUser()->getName()
            ]
        ]);

        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 201) {
            $data = $response->toArray();
            if ($response->toArray()['code'] == '1') {
                $redirectionToPaymentUrl = $data['checkoutUrl'];
            }
        }

        return $redirectionToPaymentUrl;
    }
}
