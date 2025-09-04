<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Province;
use App\Repository\UserRepository;
use App\Repository\LeadClaimsRepository;
use function Doctrine\ORM\findAll;
use App\Repository\PropertyRepository;
use App\Repository\MessengerRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OpportunityRepository;
use App\Repository\ReservationRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\PropertyStatusRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
#[IsGranted('ROLE_ADMIN')]
class AgentWorkSpaceController extends AbstractController
{
    private int $perPage = 50;

    #[Route('/', name: 'app_agent_work_space')]
    #[Cache(maxage: 3600, public: true)]
    public function index(PropertyRepository $propertyRepository, UserRepository $userRepository, RequestStack $requestStack, ReservationRepository $reservationRepository): Response
    {
        $agents = count($userRepository->findBy(['isAgent' => true]));
        $customers = count($userRepository->findBy(['isAgent' => false]));
        $annonces = count($propertyRepository->findAll());
//        $requestStack->getSession()->remove('propertyUuid');
//
//        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
//        if($user == null){
//            return $this->redirectToRoute('app_logout');
//        }
//        $propertyCount = count($propertyRepository->findBy(['user' => $user]));
//        $propertyPublish = count($propertyRepository->findBy(['user' => $user, 'publish' => true]));
//
//        $reservationCount = count($reservationRepository->findByAdmin($user, true));
//        $reservationOverCount = count($reservationRepository->findByOverReservation($user, true, true));

        return $this->render('agent_work_space/dashboard.html.twig', [
            'agents'=> $agents,
            'customers'=> $customers,
            'annonces' => $annonces,
//            'propertyCount' => $propertyCount,
//            'propertyPublish' => $propertyPublish,
//            'reservationCount' => $reservationCount,
//            'reservationOverCount' => $reservationOverCount,
        ]);
    }

    #[Route('/agent/work/space/listing', name: 'app_agent_work_space_listing')]
    public function listing(PropertyRepository $propertyRepository, UserRepository $userRepository, Request $request, RequestStack $requestStack, PropertyStatusRepository $propertyStatusRepository): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $user = $userRepository->find($this->getUser());
        $search = $request->query->get('search', '');
        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterForAgentSearch($user, $search, $page, $this->perPage);
        $pages = ceil( ($entries->count()) / $this->perPage);

        $status = $propertyStatusRepository->findAll();

        return $this->render('agent_work_space/listing/agent_listing.html.twig', [
            'properties' => $entries,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'status' => $status,
        ]);
    }

    #[Route('/agent/work/space/listing/search', name: 'app_agent_work_space_listing_search', methods: ['POST'])]
    public function listingSearch(PropertyRepository $propertyRepository, UserRepository $userRepository, Request $request, PropertyStatusRepository $propertyStatusRepository): Response
    {
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $search = $request->request->get('search', '');

        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterForAgentSearch($user, $search, $page, $this->perPage);
        $pages = ceil( ($entries->count()) / $this->perPage);

        $status = $propertyStatusRepository->findAll();

        return $this->render('agent_work_space/listing/listing_table.html.twig', [
            'properties' => $entries,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'status' => $status,
        ]);
    }

    #[Route('/agent/covered-cities', name: 'agent_covered_cities_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        // CSRF
        if (!$this->isCsrfTokenValid('update-covered-cities', (string) $request->request->get('_token'))) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['ok' => false, 'error' => 'CSRF invalide.'], 403);
            }
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_agent_work_space', ['show' => 'profil']);
        }

        $user = $userRepository->find($this->getUser());
        if (!$user) {
            return $request->isXmlHttpRequest()
                ? $this->json(['ok' => false, 'error' => 'Non authentifié.'], 401)
                : $this->redirectToRoute('app_login');
        }

        // Read IDs from the form
        $ids = $request->request->all('cityIds'); // array of strings/ints
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        // Fetch cities
        $cityRepo = $em->getRepository(Province::class);
        $selectedCities = $ids ? $cityRepo->findBy(['id' => $ids]) : [];

        // Replace coverage (ManyToMany)
        $current = new ArrayCollection($user->getCoveredCities()->toArray());
        // Remove those not selected
        foreach ($current as $c) {
            if (!in_array($c->getId(), $ids, true)) {
                $user->removeCoveredCity($c);
            }
        }
        // Add new ones
        foreach ($selectedCities as $c) {
            if (!$current->contains($c)) {
                $user->addCoveredCity($c);
            }
        }

        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'ok' => true,
                'count' => count($selectedCities),
                'cities' => array_map(fn($c) => ['id' => $c->getId(), 'name' => $c->getName()], $selectedCities),
            ]);
        }

        $this->addFlash('success', 'Villes couvertes mises à jour.');
        return $this->redirectToRoute('app_job_opportunity');
    }

    
    }


