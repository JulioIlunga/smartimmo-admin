<?php

namespace App\Controller;

use App\Entity\Favoris;
use App\Entity\Property;
use App\Entity\Province;
use App\Entity\Residence;
use App\Entity\User;
use App\Repository\CommuneRepository;
use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\ProvinceRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ListingController extends AbstractController
{
    private int $perPage = 52;

    #[Route('/listing', name: 'app_listing')]
    #[Cache(maxage: 3600, public: true)]
    public function index(PropertyRepository $propertyRepository, Request $request, RequestStack $requestStack, ProvinceRepository $provinceRepository,  CommuneRepository $communeRepository): Response
    {

        $type = $request->query->get('type', '');
        $city = $request->query->get('city', 'Kinshasa');
        $commune = $request->query->get('commune', '');
        $min = $request->query->get('min', '');
        $max = $request->query->get('max', '');
        $periodicity = 'Monthly';

        $page = $request->query->get('page', 1);

        $entries = $propertyRepository->findByFilter(
            $type,
            $city,
            $commune,
            $min,
            $max,
            $periodicity,
            $page,
            $this->perPage
        );

        $pages = ceil( ($entries->count()) / $this->perPage);
        $propertiesCount = $entries->count();
        $show = $requestStack->getSession()->get('list-overview', 'card');
        $cities = $provinceRepository->findAll();

        $communes = null;
        if ($city != ''){
            $c = $provinceRepository->findBy(['name' => $city]);
            $communes = $communeRepository->findBy(['province' => $c]);
        }

        return $this->render('listing/index.html.twig', [
            'show' => $show,
            'page' => $page,
            'pages' => $pages,
            'type' => $type,
            'city' => $city,
            'min' => $min,
            'max' => $max,
            'properties' => $entries,
            'propertiesCount' => $propertiesCount,
            'cities' => $cities,
            'communes' => $communes,
            'commune' => $commune,
            'periodicity' => $periodicity,
        ]);
    }

    #[Route('/listing/search', name: 'app_listing_search', methods: ['POST'])]
    public function searchListings(Request $request, RequestStack $requestStack, PropertyRepository $propertyRepository): Response
    {

        $type = $request->request->get('type', '');
        $city = $request->request->get('city', '');
        $commune = $request->request->get('commune', '');
        $min = $request->request->get('minBudget', '');
        $max = $request->request->get('maxBudget', '');
        $periodicity = 'Monthly';
        $page = $request->query->get('page', 1);

        $entries = $propertyRepository->findByFilterFromFilters(
            $request,
            $page,
            $this->perPage,
        );

        $pages = ceil($entries->count() / $this->perPage);
        $propertiesCount = $entries->count();

        $show = $requestStack->getSession()->get('list-overview', 'card');


        return $this->render('listing/_listing_list.html.twig', [
            'show' => $show,
            'page' => $page,
            'pages' => $pages,
            'type' => $type,
            'city' => $city,
            'commune' => $commune,
            'min' => $min,
            'max' => $max,
            'periodicity' => $periodicity,
            'properties' => $entries,
            'propertiesCount' => $propertiesCount,
        ]);
    }

    /**
     * Triggered by the buttons to switch reservation view table/yearly.
     */
    #[Route('/listing/view/{show}', name: 'listing.toggle.view', methods: ['GET'])]
    public function indexActionToggle(RequestStack $requestStack, string $show): Response
    {
        if ('card' === $show){
            $requestStack->getSession()->set('list-overview', 'card');
        }elseif ('list' === $show){
            $requestStack->getSession()->set('list-overview', 'list');
        }

        return $this->forward('App\Controller\ListingController::index');
    }

    #[Route('/listing/count/search', name: 'app_listing_get_listing_count', methods: ['POST'])]
    public function listingCount(Request $request, PropertyRepository $propertyRepository): Response
    {
        $page = $request->query->get('page', 1);
        $entries = $propertyRepository->findByFilterFromFilters($request, $page, $this->perPage);
        $propertiesCount = $entries->count();

        return $this->render('listing/_count_listing.html.twig', [
            'propertiesCount' => $propertiesCount,
        ]);
    }

    #[Route('/listing/favoris/save/or/unsaved', name: 'app_listing_favoris_management')]
    public function favoris(ManagerRegistry $doctrine, Request $request): Response
    {
        $em = $doctrine->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['id' => $request->get('user')]);
        $property = $em->getRepository(Property::class)->findOneBy(['id' => $request->get('propertyId')]);

        $favoris = $em->getRepository(Favoris::class)->findOneBy(['user' => $user, 'property' => $property]);
        if ($favoris != null){
            if ($favoris->isSaved()){
                $favoris->setSaved(false);
            }else{
                $favoris->setSaved(true);
            }
        }else{
            $favoris = new Favoris();
            $favoris->setUser($user);
            $favoris->setProperty($property);
            $favoris->setSaved(true);

            $em->persist($favoris);
        }
        $em->flush();

        return $this->render('listing/favoris.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/listing/api/request/filter/communes', name: 'app_listing_filter_communes', methods: 'POST')]
    public function filterCommune(Request $request, CommuneRepository $communeRepository, ProvinceRepository $provinceRepository): Response
    {
        $data = json_decode($request->getContent());

        $cityName = $data->city;
        $province = $provinceRepository->findOneBy(['name' => $cityName]);
        $communes = $communeRepository->findBy(['province' => $province]);
        $communesItem = array();

        $communesItem[] = 'All-%@#-Toutes les communes';
        for ($x = 0; $x <= count($communes); $x++) {
            if (isset($communes[$x])) {
                $name = $communes[$x]->getName();
                $communesItem[] = $name . '-%@#-' . $name;
            }
        }

        return new JsonResponse([
            'communes' => $communesItem, // not json_encode($communesItem)
        ]);
    }
}
