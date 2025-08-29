<?php

namespace App\Controller;

use App\Entity\Property;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\UserRepository;
use App\Repository\PropertyRepository;
use App\Repository\MessengerRepository;
use App\Repository\OpportunityRepository;
use App\Repository\ReservationRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Repository\PropertyStatusRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use function Doctrine\ORM\findAll;
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


}
