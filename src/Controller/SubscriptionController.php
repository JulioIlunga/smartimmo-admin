<?php

namespace App\Controller;

use App\Entity\MembershipPlans;
use App\Entity\PaymentForMembership;
use App\Entity\PaymentForPreference;
use App\Entity\Subscriptions;
use App\Entity\User;
use App\Repository\MembershipPlansRepository;
use App\Repository\PaymentForMembershipRepository;
use App\Repository\PaymentForPreferenceRepository;
use App\Repository\PreferenceRepository;
use App\Repository\UserRepository;
use App\Service\CreditWalletManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/subscription')]
class SubscriptionController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(private ManagerRegistry $doctrine, HttpClientInterface $client)
    {
        $this->client = $client;
    }

//    #[Route('/payment/for/membership', name: 'app_payment_for_membership', methods: ['POST'])]
//    public function index(Request $request, MembershipPlansRepository $membershipPlansRepository, EntityManagerInterface $entityManager, CreditWalletManager $creditWalletManager): JsonResponse
//    {
//        $data = json_decode($request->getContent(), true);
//        $plan = $membershipPlansRepository->findOneBy(['id' => $data['membership_id'] ]);
//
//        if (!$data) {
//            return new JsonResponse(['message' => 'Données invalides'], 400);
//        }
//
//        $em = $this->doctrine->getManager();
//        $user = $em->getRepository(User::class)->find($this->getUser());
//
//        // If user already has a subscription
//        $current = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;
//
//        if ($current && $current->getStatus() === 'active' && $current->getPlan() && $current->getPlan()->getId() === $plan->getId()) {
//            return new JsonResponse(['message' => 'Vous êtes déjà abonné à ce plan.'], 400);
//        }
//
//        // Create a new subscription
//        $subscription = new Subscriptions();
//        $subscription
//            ->setCode('new-sub-'.uniqid())
//            ->setPlan($plan)
//            ->setStatus('incomplete')
//            ->setClaimsUsed(0)
//            ->setClaimLimit( 0)
//            ->setCurrentPeriodStart(new \DateTimeImmutable('now'))
//            ->setCurrentPeriodEnd(new \DateTimeImmutable('+1 month'))
//            ->setCancelAtPeriodEnd(new \DateTimeImmutable('+1 month'));
//
//        if ($user->getSubscriptions() === null){
//            $subscription->addAgent($user);                          // if Subscriptions owns the relation
//        }
//
//        $em->persist($subscription);
//        $em->flush();
//
//        // Create new credit wallet
//        $creditWalletManager->ensureWallet($user);
//
//
//        // If your schema stores the FK on User (as your migration suggests), keep this line:
//        if ($user->getSubscriptions() === null) {
//            $user->setSubscriptions($subscription);
//            $em->persist($user); // only needed if $user is new
//            $em->flush();
//        }
//
//
//        try {
//            $payment = new PaymentForMembership();
//            $payment
//                ->setCode('Sub-' . uniqid())
//                ->setSubscription($subscription)
//                ->setAmount($plan->getMonthlyPrice())
//                ->setStatus(false)
//                ->setUser($this->getUser());
//
//            $entityManager->persist($payment);
//            $entityManager->flush();
//
//            $baseUrl = $this->getParameter('smartPay_BasUrl');
//            $onlinePaymentUrl = $this->makeRequestMembershipPaymentLink($baseUrl, $payment);
//            if (!$onlinePaymentUrl) {
//                throw new \Exception('Erreur lors de la création du lien de paiement');
//            }
//
//            return new JsonResponse(
//                [
//                    'smart_pay_url' => $onlinePaymentUrl,
//                    'paymentCode' => $payment->getCode(),
//                ], 200, [
//                "Content-Type" => "application/json"
//            ]);
//
//        } catch (\Exception $e) {
//
//            return new JsonResponse([
//                'message' => $e->getMessage()
//            ], 400, [
//                "Content-Type" => "application/json"
//            ]);
//        }
//    }


    #[Route('/payment/for/membership', name: 'app_payment_for_membership', methods: ['POST'])]
    public function index(
        Request $request,
        MembershipPlansRepository $membershipPlansRepository,
        EntityManagerInterface $entityManager,
        CreditWalletManager $creditWalletManager
    ): JsonResponse {
        // 1) Parse JSON body safely
        $data = json_decode($request->getContent() ?? '', true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'Données JSON invalides.'], 400);
        }

        $planId = $data['membership_id'] ?? null;
        $renew  = (bool)($data['renew'] ?? false);

        if (!$planId) {
            return new JsonResponse(['message' => 'Paramètre "membership_id" manquant.'], 400);
        }

        // 2) Resolve current user
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return new JsonResponse(['message' => 'Utilisateur non authentifié.'], 401);
        }

        // 3) Fetch plan
        $plan = $membershipPlansRepository->find($planId);
        if (!$plan) {
            return new JsonResponse(['message' => 'Plan introuvable.'], 404);
        }

        // 4) Get current subscription (if your model is one-to-one User->Subscriptions)
        $current = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;

        // 5) RENEWAL path: same active plan and user asked to renew
        if (
            $renew === true &&
            $current &&
            $current->getStatus() === 'active' &&
            $current->getPlan() &&
            $current->getPlan()->getId() === $plan->getId()
        ) {
            try {
                // Ensure wallet exists (optionally top-up after successful payment elsewhere)
                $creditWalletManager->ensureWallet($user);

                // Create payment tied to EXISTING subscription
                $payment = new \App\Entity\PaymentForMembership();
                $payment
                    ->setCode('Sub-Renew-' . uniqid())
                    ->setSubscription($current)
                    ->setAmount($plan->getMonthlyPrice())
                    ->setStatus(false)
                    ->setUser($user);

                $entityManager->persist($payment);
                $entityManager->flush();

                $baseUrl          = $this->getParameter('smartPay_BasUrl');
                $onlinePaymentUrl = $this->makeRequestMembershipPaymentLink($baseUrl, $payment);
                if (!$onlinePaymentUrl) {
                    throw new \RuntimeException('Erreur lors de la création du lien de paiement.');
                }

                return new JsonResponse([
                    'smart_pay_url' => $onlinePaymentUrl,
                    'paymentCode'   => $payment->getCode(),
                    'mode'          => 'renewal',
                ], 200);

            } catch (\Throwable $e) {
                return new JsonResponse(['message' => $e->getMessage()], 400);
            }
        }

        // 6) If already subscribed to this plan and NOT renewing -> block duplicate
        if (
            $current &&
            $current->getStatus() === 'active' &&
            $current->getPlan() &&
            $current->getPlan()->getId() === $plan->getId()
        ) {
            return new JsonResponse([
                'message' => 'Vous êtes déjà abonné à ce plan. Utilisez l’option "renouveler" si besoin.',
                'hint'    => 'Envoyez {"membership_id": ' . (int)$planId . ', "renew": true}'
            ], 400);
        }

        // 7) NEW subscription flow
        try {
            $subscription = new \App\Entity\Subscriptions();
            $now          = new \DateTimeImmutable('now');
            $periodEnd    = (clone $now)->modify('+1 month');

            $subscription
                ->setCode('new-sub-' . uniqid())
                ->setPlan($plan)
                ->setStatus('incomplete')              // will flip to active after IPN/confirmation
                ->setClaimsUsed(0)
                // Optional: pull claimLimit from the plan if available
                ->setClaimLimit(method_exists($plan, 'getMonthlyClaimLimit') ? (int)$plan->getMonthlyClaimLimit() : 0)
                ->setCurrentPeriodStart($now)
                ->setCurrentPeriodEnd($periodEnd);

            // If your column cancelAtPeriodEnd is a DATETIME: keep as date. If it’s boolean, set true/false.
            if (method_exists($subscription, 'setCancelAtPeriodEnd')) {
                // Example for boolean:
                // $subscription->setCancelAtPeriodEnd(false);
                // If it is DateTime:
                $subscription->setCancelAtPeriodEnd($periodEnd);
            }

            // Link user <-> subscription
            // Depending on your mapping, either:
            if (method_exists($subscription, 'addAgent')) {
                $subscription->addAgent($user); // if Subscriptions owns a ManyToMany or OneToMany with users/agents
            }
            if (method_exists($user, 'setSubscriptions')) {
                $user->setSubscriptions($subscription); // One-to-One on User
            }

            $entityManager->persist($subscription);
            $entityManager->persist($user);
            $entityManager->flush();

            // Ensure wallet exists
            $creditWalletManager->ensureWallet($user);

            // Create payment
            $payment = new \App\Entity\PaymentForMembership();
            $payment
                ->setCode('Sub-' . uniqid())
                ->setSubscription($subscription)
                ->setAmount($plan->getMonthlyPrice())
                ->setStatus(false)
                ->setUser($user);

            $entityManager->persist($payment);
            $entityManager->flush();

            $baseUrl          = $this->getParameter('smartPay_BasUrl');
            $onlinePaymentUrl = $this->makeRequestMembershipPaymentLink($baseUrl, $payment);
            if (!$onlinePaymentUrl) {
                throw new \RuntimeException('Erreur lors de la création du lien de paiement.');
            }

            return new JsonResponse([
                'smart_pay_url' => $onlinePaymentUrl,
                'paymentCode'   => $payment->getCode(),
                'mode'          => 'new',
            ], 200);

        } catch (\Throwable $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        }
    }


    public function makeRequestMembershipPaymentLink($baseUrl, PaymentForMembership $payment)
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
