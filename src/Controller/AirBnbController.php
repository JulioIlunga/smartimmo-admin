<?php

namespace App\Controller;

use App\Repository\CommuneRepository;
use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\ProvinceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AirBnbController extends AbstractController
{
    private int $perPage = 54;

    #[Route('/listing/airbnb', name: 'air_bnb')]
    public function index(PropertyRepository $propertyRepository, Request $request, RequestStack $requestStack, ProvinceRepository $provinceRepository, CommuneRepository $communeRepository): Response
    {
        $type = $request->query->get('type', '');
        $city = $request->query->get('city', '');
        $commune = $request->query->get('commune', '');
        $min = $request->query->get('min', '');
        $max = $request->query->get('max', '');
        $checkIn = $request->query->get('checkIn', '');
        $checkOut = $request->query->get('checkOut', '');
        $periodicity = 'Daily';

        $page = $request->query->get('page', 1);

        $entries = $propertyRepository->findByFilterForAirBnb(
            $checkIn,
            $checkOut,
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

        return $this->render('air_bnb/index.html.twig', [
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
            'periodicity' => $periodicity
        ]);
    }

    #[Route('/listing/airbnb/search', name: 'air_bnb_search', methods: ['POST'])]
    public function search(PropertyRepository $propertyRepository, Request $request, RequestStack $requestStack): Response
    {

        $type = $request->request->get('type', '');
        $city = $request->request->get('city', '');
        $commune = $request->request->get('commune', 'All');
        $min = $request->request->get('minBudget', '');
        $max = $request->request->get('maxBudget', '');
        $checkIn = $request->request->get('checkIn', '');
        $checkOut = $request->request->get('checkOut', '');
        $periodicity = 'Daily';
        $page = $request->query->get('page', 1);

        $entries = $propertyRepository->findByFilterForAirBnb(
            $checkIn,
            $checkOut,
            $type,
            $city,
            $commune,
            $min,
            $max,
            $periodicity,
            $page,
            $this->perPage
        );

        $pages = ceil($entries->count() / $this->perPage);
        $propertiesCount = $entries->count();
        $show = $requestStack->getSession()->get('list-overview', 'card');

        return $this->render('air_bnb/_listing_list.html.twig', [
            'show' => $show,
            'page' => $page,
            'pages' => $pages,
            'type' => $type,
            'city' => $city,
            'commune' => $commune,
            'min' => $min,
            'max' => $max,
            'properties' => $entries,
            'propertiesCount' => $propertiesCount,
            'periodicity' => $periodicity,
        ]);
    }
}
