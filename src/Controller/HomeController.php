<?php

namespace App\Controller;

use App\Repository\AdminConfigurationRepository;
use App\Repository\CommuneRepository;
use App\Repository\PropertyRepository;
use App\Repository\ProvinceRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\SmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    #[Cache(maxage: 3600, public: true)]
    public function index(PropertyRepository $propertyRepository, ProvinceRepository $provinceRepository, UserRepository $userRepository, RequestStack $requestStack, ReservationRepository $reservationRepository, AdminConfigurationRepository $adminConfigurationRepository): Response
    {

        if ($requestStack->getSession()->get('propertyUuid') !== null  ){
            $propertyUuid = $requestStack->getSession()->get('propertyUuid');
            $requestStack->getSession()->remove('propertyUuid');

            return $this->redirectToRoute('app_property_details', ['propertyUuid' => $propertyUuid]);
        }

        $requestStack->getSession()->remove('propertyUuid');

        $properties = $propertyRepository->findBy(['publish' => true]);
        $pubishAppart = $propertyRepository->findBy(['publish' => true, 'type'=>'appartement'],['createdAt' => 'DESC'],10);


        $maisonCount = count($propertyRepository->findBy(['type' => 'Maison']));
        $villaCount = count($propertyRepository->findBy(['type' => 'Villa']));
        $apartmentCount = count($propertyRepository->findBy(['type' => 'Maison']));
        $boutiqueCount =  count($propertyRepository->findBy(['type' => 'Boutique']));
        $bureauCount =  count($propertyRepository->findBy(['type' => 'Bureau']));

        $cities = $provinceRepository->findAll();

        $propertiesByCity = $propertyRepository->createQueryBuilder('p')
            ->select('p.city, COUNT(p.id) as propertyCount')
            ->groupBy('p.city')
            ->getQuery()
            ->getResult();

        $topAgents = $userRepository->findBy(['active' => true, 'topAgent' => true], [], 4);

        $reservationByUser =  [];
        if ($this->getUser()){
            $user = $userRepository->findOneBy(['id' => $this->getUser()]);
            $reservationByUser = $reservationRepository->findBy(['user' => $user, 'confirmed' => false], ['id' => 'DESC'], 3);
        }

        $adminConfig = $adminConfigurationRepository->findOneBy(['id' => 2]);

        return $this->render('home/index.html.twig', [
            'properties' => $properties,
            'maisonCount' => $maisonCount,
            'villaCount' => $villaCount,
            'apartmentCount' => $apartmentCount,
            'boutiqueCount' => $boutiqueCount,
            'bureauCount' => $bureauCount,
            'propertiesByCity' => $propertiesByCity,
            'publishAppart' => $pubishAppart,
            'reservationByUser' => $reservationByUser,
            'cities' => $cities,
            'topAgents' => $topAgents,
            'adminConfig' => $adminConfig
        ]);
    }

    #[Route('/home/api/request/filter/communes', name: 'app_home_filter_communes', methods: ['POST'])]
    public function filterCommune(Request $request, CommuneRepository $communeRepository, ProvinceRepository $provinceRepository): Response
    {
        $data = json_decode($request->getContent());
        $city = $data->city ?? null;

        if (!$city) {
            return new JsonResponse(['error' => 'City is missing'], 400);
        }

        $province = $provinceRepository->findOneBy(['name' => $city]);
        if (!$province) {
            return new JsonResponse(['communes' => []]); // or handle not found
        }

        $communes = $communeRepository->findBy(['province' => $province]);

        // Prepare array
        $communesItem = ['Tous']; // Always include "Tous" first
        foreach ($communes as $commune) {
            $communesItem[] = $commune->getName();
        }

        // Return raw array, not JSON-encoded string
        return new JsonResponse(['communes' => $communesItem]);
    }

}
