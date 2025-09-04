<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PreferenceRepository;
use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\RatingRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use FontLib\Table\Type\name;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/admin/smart/immmo')]
class SmartImmoAdminController extends AbstractController
{
    private int $perPage = 50;

    #[Route('/', name: 'app_smart_immo_admin')]
    public function index(PropertyRepository $propertyRepository, UserRepository $userRepository, Request $request, RequestStack $requestStack, PropertyStatusRepository $propertyStatusRepository): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $search = $request->query->get('search', '');
        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterForAdmin($search, $page, $this->perPage);
        $pages = ceil( ($entries->count()) / $this->perPage);

        $status = $propertyStatusRepository->findAll();
        $users = $userRepository->findBy(['isAgent' => true], ['id' => 'DESC']);

        $count = $entries->count();

        return $this->render('smart_immo_admin/published/index.html.twig', [
            'properties' => $entries,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'status' => $status,
            'users' => $users,
            'count' => $count
        ]);
    }
    #[Route('/search', name: 'app_smart_immo_admin_search', methods: ['GET','POST'])]
    public function listingSearchAdmin(PropertyRepository $propertyRepository, UserRepository $userRepository, Request $request, PropertyStatusRepository $propertyStatusRepository): Response
    {

        $search = $request->request->get('search', '');
        if ($search != ''){
            $search = $userRepository->findOneBy(['id' => $search])->getId();
        }
        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterForAdmin($search, $page, $this->perPage);
        $pages = ceil( ($entries->count()) / $this->perPage);
        $count = $entries->count();
        $status = $propertyStatusRepository->findAll();


        return $this->render('smart_immo_admin/published/listing_table.html.twig', [
            'properties' => $entries,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'count' => $count,
            'status' => $status,
        ]);
    }
    #[Route('/listing/change/status/of/a/property/{id}/{propertyId}/{page}', name: 'app_smart_immo_admin_change_property_status')]
    public function changePropertyStatus($id, $propertyId, $page, ManagerRegistry $doctrine, PropertyStatusRepository $propertyStatusRepository, PropertyRepository $propertyRepository,RatingRepository $ratingRepository ): RedirectResponse{

        $em = $doctrine->getManager();
        $status = $propertyStatusRepository->findOneBy(['id' => $id]);
        $property = $propertyRepository->findOneBy(['id' => $propertyId]);
        if ($status->getId() == 4){
            $property->setPublish(false);
        }
        $property->setPropertyStatus($status);
        $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));
        $em->flush();

        $this->addFlash('success',  "Le statut de l'annonce a été modifié avec succès.");

        return $this->redirectToRoute('app_smart_immo_admin', ['page' => $page]);
    }

    #[Route('/real/estate/agent', name: 'app_smart_immo_admin_real_estate_agent')]
    public function realEstateAgent(
        UserRepository $userRepository,
        RatingRepository $ratingRepository,
        Request $request
    ): Response {
        $q = $request->query->get('q'); // récupération du terme recherché

        $qb = $userRepository->createQueryBuilder('u')
            ->where('u.isAgent = :isAgent')
            ->setParameter('isAgent', true);

        // 🔎 Filtre recherche
        if ($q) {
            $qb->andWhere('u.firstname LIKE :q OR u.name LIKE :q OR u.phone LIKE :q')
            ->setParameter('q', '%' . $q . '%');
        }

        $qb->orderBy('u.id', 'DESC');

        $agents = $qb->getQuery()->getResult();
        $count = count($agents);

        $averageRating = $ratingRepository->getAverageRatingsForAgents($agents);
        $ratingCount   = $ratingRepository->getRatingCountsForAgents($agents);

        return $this->render('smart_immo_admin/realEstateAgent/index.html.twig', [
            'agents'        => $agents,
            'count'         => $count,
            'averageRating' => $averageRating,
            'ratingCount'   => $ratingCount,
        ]);
    }

    #[Route('/real/estate/customer', name: 'app_smart_immo_admin_real_estate_customer')]
    public function realEstateCustomer(UserRepository $userRepository, Request $request,PreferenceRepository $preferenceRepository, RatingRepository $ratingRepository): Response
    {
        $customers = $userRepository->findBy(['isAgent' => false], ['id' => 'DESC']);
        $count = count($customers);
        // dd($customers);
        // $averageRating = $ratingRepository->getAverageRatingsForAgents($customers);
        // $ratingCount = $ratingRepository->getRatingCountsForAgents($customers);

        $q = $request->query->get('q'); // récupération du terme recherché

        if ($q) {
            $customers = $userRepository->createQueryBuilder('u')
                ->where('u.isAgent = :isAgent')
                ->andWhere('u.firstname LIKE :q OR u.phone LIKE :q OR u.name LIKE :q')
                ->setParameter('isAgent', false)
                ->setParameter('q', '%'.$q.'%')
                ->orderBy('u.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $customers = $userRepository->findBy(['isAgent' => false], ['id' => 'DESC']);
        }
        return $this->render('smart_immo_admin/realEstateCustomer/index.html.twig', [
            'customers' => $customers,
            'count' => $count,
            // 'averageRating' => $averageRating,
            // 'ratingCount' => $ratingCount
        ]);
    }
    // #[Route('/real/estate/agent/action/{id}', name: 'app_smart_immo_admin_real_estate_agent_action')]
    // public function realEstateAgentAction(User $user, ManagerRegistry $doctrine, RoleRepository $roleRepository, Request $request): Response
    // {
    //     $em = $doctrine->getManager();
    //     $blockReason = $request->request->get('blockReason');
    //     $banFromPlatform = $request->request->get('banFromPlatform'); // null si décoché

    //     if ($banFromPlatform) {
    //         $user->setActive(false);
    //     } else {
    //         $user->setActive(true);
    //     }
    //     $user->setBlock(true);
    //     // $user->setAgent(false);
    //     $role = $roleRepository->findOneBy(['name' => 'CUSTOMER']);
    //     $user->setRole($role);
    //     $user->setTopAgent(false);
    //     $user->setBlockReason($blockReason);
    //     $em->flush();

    //     $this->addFlash('success',  "L'agent {$user->getFirstname()} {$user->getName()} a été bloqué avec succès.");

    //     return $this->redirectToRoute('app_smart_immo_admin_real_estate_agent');
    // }

    #[Route('/real/estate/agent/block/reasons/{id}', name: 'app_agent_block_reasons_modal', methods: ['POST'])]
    public function showModal(
        int $id,
        RoleRepository $roleRepository,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $agent = $userRepository->find($id);

        if (!$agent) {
            throw $this->createNotFoundException('Agent non trouvé');
        }

        // Récupérer données envoyées
        $reason = $request->request->get('reason');
        $permanent = $request->request->get('permanent') !== null; // true si coché

        // Mettre à jour l'agent
        $role = $roleRepository->findOneBy(['name' => 'CUSTOMER']);
        $agent->setRole($role);
        $agent->setTopAgent(false);
        $agent->setBlock(true);
        $agent->setBlockReason($reason);
        if ($permanent) {
        $agent->setActive(false);

    } 
        $em->flush();

        $this->addFlash('success', 'Agent bloqué avec succès.');

        // Rediriger vers la liste
        return $this->redirectToRoute('app_smart_immo_admin_real_estate_agent');
    }

    #[Route('/real/estate/agent/block/reasons/{id}', name: 'app_agent_block_reasons_modal_get', methods: ['GET'])]
    public function showModalGet($id, UserRepository $userRepository): Response
    {
        $agent = $userRepository->find($id);

        if (!$agent) {
            throw $this->createNotFoundException('Agent non trouvé');
        }

        return $this->render('smart_immo_admin/realEstateAgent/_block_modal.html.twig', [
            'agent' => $agent,
        ]);
    }

    #[Route('/real/estate/block/customer/{id}', name:'app_smart_immo_real_estate_block_customer')]
    public function blockCustomer(User $user, ManagerRegistry $doctrine, RoleRepository $roleRepository, Request $request): Response
    {
        $em = $doctrine->getManager();

        $user->setActive(false);
        $em->flush();
        $this->addFlash('success', "L'utilisateur {$user->getFirstname()} {$user->getName()} a bien été bloqué");
        return $this->redirectToRoute('app_smart_immo_admin_real_estate_customer');
    }
    #[Route('/real/estate/unblock/customer/{id}', name:'app_smart_immo_real_estate_unblock_customer')]
    public function unBlockCustomer(User $user, ManagerRegistry $doctrine, RoleRepository $roleRepository, Request $request): Response
    {
        $em = $doctrine->getManager();

        $user->setActive(true);
        $user->setBlock(false);
        $em->flush();
        $this->addFlash('success', "L'utilisateur {$user->getFirstname()} {$user->getName()} a bien été débloqué");
        return $this->redirectToRoute('app_smart_immo_admin_real_estate_customer');
    }

    #[Route('/real/estate/agent/action/unblock/{id}', name: 'app_smart_immo_real_estate_unblock_agent')]
    public function unblockAgent(User $user, ManagerRegistry $doctrine, RoleRepository $roleRepository): Response
    {


        $em = $doctrine->getManager();
        $user->setActive(true);
        $user->setBlock(false);
        // $user->setAgent(true);
        $role = $roleRepository->findOneBy(['name' => 'AGENT']);
        $user->setRole($role);
        $em->flush();

        $this->addFlash('success',  "L'agent {$user->getFirstname()} {$user->getName()} a été débloqué avec succès.");

        return $this->redirectToRoute('app_smart_immo_admin_real_estate_agent');
    }


    #[Route('/agent/{id}/reviews', name: 'app_agent_reviews')]
    public function viewAgentReviews(
        $id,
        EntityManagerInterface $em,
        RatingRepository $ratingRepository,
        Request $request
    ): Response {
        $agent = $em->getRepository(User::class)->findOneBy(['id' => $id]);
        // dd($agent);
        if (!$agent) {
            throw $this->createNotFoundException('Agent non trouvé');
        }

        // Récupération de la moyenne et du nombre total d'avis
        $averageRating = $ratingRepository->getAverageRatingForAgent($agent->getId());
        $ratingCount = $ratingRepository->count(['agent' => $agent->getId()]);

        // Pagination des commentaires
        $limit = 10; // Nombre de commentaires par page
        $page = max(1, (int) $request->query->get('page', 1));
        $reviews = $ratingRepository->getReviewsForAgent($agent->getId(), $page, $limit);

        // Calcul du nombre total de pages
        $totalReviews = $ratingRepository->countReviewsForAgent($agent->getId());
        $totalPages = ceil($totalReviews / $limit);

        return $this->render('smart_immo_admin/realEstateAgent/agent_reviews.html.twig', [
            'agent' => $agent,
            'reviews' => $reviews,
            'ratingCount' => $ratingCount,
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }
    #[Route('/export/agent/list/cvs', name: 'app_smart_immo_agents_export_in_cvs', methods: ['GET'])]
    public function exportPDF(ManagerRegistry $doctrine): Response
    {
        $em = $doctrine->getManager();
        $agents = $em->getRepository(User::class)->findBy(['isAgent' => true]);

        $handle = fopen('php://temp', 'w');
        fputcsv($handle, ['NOM', 'POST-NOM','TELEPHONE']);
        foreach ($agents as $i){
            fputcsv($handle, [$i->getFirstname(), $i->getName(), $i->getPhonecode(). $i->getPhone()]);
        }
        rewind($handle);
        $response = new Response(stream_get_contents($handle));
        fclose($handle);

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition','attachment; filename="LISTE-AGENTS-'.date('d-m-Y').'.csv"');
        return $response;
    }
}
