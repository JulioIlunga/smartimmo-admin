<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommuneRepository;
use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\ProvinceRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

class AgentPropertyController extends AbstractController
{
    private int $perPage = 50;

    #[Route('/listing/properties/{id}/agent', name: 'app_agent_property')]
    #[Cache(maxage: 3600, public: true)]
    public function index($id, PropertyRepository $propertyRepository, Request $request, UserRepository $userRepository, RequestStack $requestStack,): Response
    {

        $user = $userRepository->findOneBy(['id' => $id]);
        $type = $request->query->get('type', 1);
        $search = $request->query->get('search', '');
        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterForAgent($user, $type, $page, $this->perPage);
        $pages = ceil(($entries->count()) / $this->perPage);

        $show = $requestStack->getSession()->get('list-overview', 'card');
        $propertiesCount = $entries->count();

        return $this->render('agent_property/index.html.twig', [
            'properties' => $entries,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'agent' => $user,
            'show' => $show,
            'propertiesCount' => $propertiesCount
        ]);
    }
}
