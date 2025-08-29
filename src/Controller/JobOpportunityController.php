<?php

namespace App\Controller;

use App\Entity\LeadClaims;
use App\Entity\Preference;
use App\Entity\User;
use App\Repository\LeadClaimsRepository;
use App\Repository\PreferenceRepository;
use App\Repository\ProvinceRepository;
use App\Repository\UserRepository;
use App\Service\CreditWalletManager;
use App\Service\LeadService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/job-opportunity')]
class JobOpportunityController extends AbstractController
{
    #[Route('/dashboard', name: 'app_job_opportunity')]
    public function index(Request $request,
                          PreferenceRepository $requestsRepo,
                          LeadClaimsRepository $claimRepo, ProvinceRepository $provinceRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User $user */
        $q      = (string) $request->query->get('q', '');
        $cityId = $request->query->getInt('city') ?: null;
        $type   = (string) $request->query->get('type', '');

        $leads = $requestsRepo->findOpenLeadsForUser($user, $q, $cityId, $type);

        // Current subscription and claims
        $m = method_exists($user, 'getSubscriptions') ? $user->getSubscriptions() : null;
        $claimLimit = $m?->getClaimLimit() ?? 0;
        $claimsUsed = $m?->getClaimsUsed() ?? 0;
        $claimsLeft = max(0, $claimLimit - $claimsUsed);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Read filters from the query string
        $cityId = $request->query->getInt('city') ?: null;
        $type   = (string) $request->query->get('type', '');

        // Show only the covered cities in the dropdown
        $coveredCities = $user->getCoveredCities(); // Collection
        $cities = $provinceRepository->findAll();

        // (Optional) anything else you already compute, e.g. claimed_by_me...
        $claimedByMeIds = $claimRepo->createQueryBuilder('c')
            ->select('IDENTITY(c.lead)')                  // returns lead_id
            ->andWhere('c.agent = :u')->setParameter('u', $this->getUser())
            ->getQuery()->getSingleColumnResult();        // array of strings/ints

        $claimedByMeIds = array_map('intval', $claimedByMeIds);


        $filters = ['city' => $cityId, 'type' => $type];

        return $this->render('agent_work_space/job_opportunity/index.html.twig', [
            'leads'          => $leads,
            'membership'     => $m,
            'claim_limit'    => $claimLimit,
            'claims_used'    => $claimsUsed,
            'claims_left'    => $claimsLeft,
            'user' => $user,
            'cities'        => $cities,       // only covered cities
            'coveredCities'        => $coveredCities,       // only covered cities
            'filters'       => $filters,      // keep selections
            'claimed_by_me' => $claimedByMeIds // as before
        ]);
    }

    #[Route('/leads/{id}/claim', name: 'leads.claim', methods: ['POST'])]
    public function claim(
        Preference $lead,
        Request $request,
        LeadClaimsRepository $claimRepo,
        EntityManagerInterface $em,
        CreditWalletManager $creditWalletManager
    ): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['ok' => false, 'reason' => 'auth', 'message' => 'Veuillez vous connecter.'], 401);
        }

        // CSRF
        if (!$this->isCsrfTokenValid('claim_lead_' . $lead->getId(), (string)$request->request->get('_token'))) {
            return $this->json(['ok' => false, 'reason' => 'csrf', 'message' => 'Jeton de sécurité invalide.'], 419);
        }

        // Lead must be open
        if (method_exists($lead, 'isDeleted') && $lead->isDeleted()) {
            return $this->json(['ok' => false, 'reason' => 'closed', 'message' => 'Lead indisponible.'], 410);
        }
        if (method_exists($lead, 'getStatus') && $lead->getStatus() !== true) {
            return $this->json(['ok' => false, 'reason' => 'closed', 'message' => 'Lead non actif.'], 410);
        }

        // Coverage MUST exist (even admins)
        $covered = $user->getCoveredCities()?->toArray() ?? [];
        if (count($covered) === 0) {
            return $this->json(['ok' => false, 'reason' => 'coverage', 'message' => 'Aucune ville couverte définie.'], 403);
        }
        // Lead city must be covered
        $leadCity = method_exists($lead, 'getCity') ? $lead->getCity() : null;
        if (!$leadCity || !\in_array($leadCity, $covered, true)) {
            return $this->json(['ok' => false, 'reason' => 'coverage', 'message' => 'Lead hors de votre couverture.'], 403);
        }

        // Already claimed by this agent?
        if ($claimRepo->isLeadClaimedByUser($lead, $user)) {
            return $this->json(['ok' => false, 'reason' => 'already_claimed', 'message' => 'Vous avez déjà réclamé ce lead.'], 409);
        }

        // Max claims per lead
        $currentClaims = method_exists($claimRepo, 'countForLead')
            ? $claimRepo->countForLead($lead)
            : $claimRepo->count(['lead' => $lead]); // fallback
        if ($currentClaims >= 5) {
            return $this->json(['ok' => false, 'reason' => 'max_claims', 'message' => 'Ce lead a atteint la limite de réclamations.'], 403);
        }

        // Membership (pick active if it's a collection)
        $m = null;
        if (method_exists($user, 'getSubscriptions')) {
            $subs = $user->getSubscriptions();
            if ($subs instanceof \Doctrine\Common\Collections\Collection) {
                foreach ($subs as $s) {
                    if ($s->getStatus() === 'active') {
                        $m = $s;
                        break;
                    }
                }
            } else {
                $m = $subs; // single object in some schemas
            }
        }
        if (!$m || $m->getStatus() !== 'active') {
            return $this->json(['ok' => false, 'reason' => 'no_membership', 'message' => 'Un abonnement actif est requis pour réclamer.'], 403);
        }

        $limit = (int)($m->getClaimLimit() ?? 0);
        $used = (int)($m->getClaimsUsed() ?? 0);
        if ($limit > 0 && $used >= $limit) {
            return $this->json(['ok' => false, 'reason' => 'no_quota', 'message' => 'Votre quota de réclamations est atteint.'], 403);
        }

        // Dynamic credit cost based on min price/budget
        $minPrice = (float)($lead->getMinPrice() ?? 0);
        $cost = (int)ceil(max(0.0, $minPrice) / 500.0);
        $cost = max(1, min(5, $cost)); // clamp 1..5

        // Wallet check
        $wallet = $user->getCreditWallet();
        $balance = $wallet?->getBalanceCredits() ?? 0;
        if ($balance < $cost) {
            return $this->json([
                'ok' => false,
                'reason' => 'no_credits',
                'message' => "Crédits insuffisants (coût : {$cost})."
            ], 403);
        }

        // Transaction: persist claim + debit credits + increment usage
        $conn = $em->getConnection();
        $conn->beginTransaction();
        try {
            // Create claim
            $claim = new LeadClaims();
            $claim->setAgent($user);
            $claim->setLead($lead);
            $claim->setStatus('claimed');
            $claim->setClaimedAt(new \DateTimeImmutable());
            $em->persist($claim);

            // Increment usage
            if (method_exists($m, 'setClaimsUsed')) {
                $m->setClaimsUsed($used + $cost);
            }

            // Debit credits (throws if not enough, but we already checked)
            $creditWalletManager->subCredits($wallet, $cost);

            $em->flush();
            $conn->commit();

            return $this->json([
                'ok' => true,
                'message' => 'Lead réclamé avec succès.',
                'leadId' => $lead->getId(),
                'cost' => $cost,
                'wallet' => [
                    'balance' => $wallet->getBalanceCredits(),
                ],
                'quota' => [
                    'limit' => $limit,
                    'used' => $used + 1,
                    'left' => $limit > 0 ? max(0, $limit - ($used + 1)) : null,
                ],
                'client' => [
                    'name' => $lead->getUser()->getName() ?? '—',
                    'email' => $lead->getUser()->getEmail() ?? '—',
                    'phone' => $lead->getUser()->getPhone() ?? '—',
                ],
            ]);
        } catch (\Throwable $e) {
            $conn->rollBack();
            return $this->json(['ok' => false, 'reason' => 'server', 'message' => 'Erreur interne.'], 500);
        }
    }


    #[Route('/customer-requests/{id}/modal', name: 'app_customer_request_show_modal', methods: ['GET'])]
    public function showModal($id, PreferenceRepository $preferenceRepository, UserRepository $userRepository, LeadService $leadService): Response
    {
        $lead = $preferenceRepository->find($id);
        $isClaimedByMe = $leadService->isClaimedByUser($lead, $userRepository->find($this->getUser()));
        $sub = $membership ?? $this->getUser()?->getSubscriptions();
        $validSub = ($sub && $sub->getId() > 0) ? $sub : null;

        return $this->render('agent_work_space/job_opportunity/show_details.html.twig', [
            'lead' => $lead,
             'isClaimedByMe' => $isClaimedByMe,
            'membership' => $validSub,
        ]);
    }

}
