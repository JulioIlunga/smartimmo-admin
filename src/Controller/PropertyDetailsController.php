<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\PropertyReport;
use App\Entity\User;
use App\Repository\ImagesRepository;
use App\Repository\PropertyReportRepository;
use App\Repository\PropertyRepository;
use App\Repository\RatingRepository;
use App\Repository\ReservationRepository;
use App\Repository\ServiceSupRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;

class PropertyDetailsController extends AbstractController
{
    #[Route('/property', name: 'app_property_details')]
    #[Cache(maxage: 3600, public: true)]
    public function index(Request $request, PropertyRepository $propertyRepository, ImagesRepository $imagesRepository, RequestStack $requestStack, ReservationRepository $reservationRepository, ServiceSupRepository $serviceSupRepository, RatingRepository $ratingRepository): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $uuid = $request->query->get('propertyUuid');
        $property = $propertyRepository->findOneBy(['uuidProperty' => $uuid]);

        $reservations = $reservationRepository->findBy(['property' => $property, 'confirmed' => true]);

        $reservedDates = [];
        foreach ($reservations as $booked) {
            $start = $booked->getCheckIn();
            $end = $booked->getCheckOut();
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($start, $interval, $end);

            foreach ($period as $date) {
                $reservedDates[] = $date->format('Y-m-d');
            }
        }

        if ($uuid == null && $property == null){
            return $this->redirectToRoute('app_listing');
        }

        $images = $imagesRepository->findBy(['property' => $property]);

        $image1 = null;
        $image2 = null;
        $image3 = null;
        $image4 = null;

        if (isset($images[0])){ $image1 = $images[0]; }
        if (isset($images[1])){ $image2 = $images[1]; }
        if (isset($images[2])){ $image3 = $images[2]; }
        if (isset($images[3])){ $image4 = $images[3]; }

        $properties = $propertyRepository->findBy(['user' => $property->getUser(), 'publish' => true], ['id' => 'DESC'], 8);
        $services = $serviceSupRepository->getServiceSupFromPropertyId($property->getId());

        $averageRating = $ratingRepository->getAverageRatingForAgent($property->getUser()->getId());
        $ratingCount = $ratingRepository->count(['agent' => $property->getUser()->getId()]);
        $ratingExist = $ratingRepository->findOneBy(['property' => $property, 'user' => $this->getUser()]);

        return $this->render('property_details/index.html.twig', [
            'property' => $property,
            'properties' => $properties,
            'reservedDates' => $reservedDates,
            'services' => $services,
            'image1' => $image1,
            'image2' => $image2,
            'image3' => $image3,
            'image4' => $image4,
            'availabilityMsg' => '',
            'availableDate' => [],
            'averageRating' => $averageRating,
            'ratingCount' => $ratingCount,
            'ratingExist' => $ratingExist,
            'ratingScore' => $ratingExist ? $ratingExist->getScore() : 0,
            'ratingComment' => $ratingExist ? $ratingExist->getComment() : '',
        ]);
    }

    #[Route('/property/view/detail/{id}', name: 'app_property_details_view_agent_detail')]
    public function viewAgentDetail($id, ManagerRegistry $doctrine): Response
    {
        $em = $doctrine->getManager();
        $agent = $em->getRepository(User::class)->findOneBy(['id' => $id]);
        $onlineListingCount = count($em->getRepository(Property::class)->findOnlineListing($agent));

        return $this->render('property_details/agent_details_view.html.twig', [
            'agent' => $agent,
            'onlineListingCount' => $onlineListingCount,
        ]);
    }

    #[Route('/property/login/before/pursue/{property}', name: 'app_property_save_for_login')]
    public function savePropertyUuid(Property $property, RequestStack $requestStack): RedirectResponse
    {
        $requestStack->getSession()->set('propertyUuid', strval($property->getUuidProperty()));
        return $this->redirectToRoute('app_login');
    }

    #[Route('/property/report/unavailable/property/from/user/{id}', name: 'app_property_report_unavailable_property', methods: ['POST'])]
    public function reportProperty(Property $property, Request $request, ManagerRegistry $doctrine, UserRepository $userRepository, PropertyReportRepository $propertyReportRepository): Response
    {
        $em = $doctrine->getManager();
        $user = $userRepository->find($request->request->get('user'));

        $ifReportExist = $propertyReportRepository->findBy(['user' => $user, 'property' => $property]);
        if (!$ifReportExist){
            $report = new PropertyReport();
            $report->setStatus(true);
            $report->setProperty($property);
            $report->setUser($user);

            $em->persist($report);
            $em->flush();
        }

        return new JsonResponse([], 200);
    }
}
