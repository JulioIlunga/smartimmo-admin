<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use function PHPUnit\Framework\callback;

#[Route('/agent')]
class ReservationAdminCalendarController extends AbstractController
{
    #[Route('/reservation/calendar', name: 'app_reservation_admin_calendar')]
    public function indexAction(ManagerRegistry $doctrine, RequestStack $requestStack, UserRepository $userRepository, ReservationRepository $reservationRepository)
    {

        $em = $doctrine->getManager();

        $today = strtotime(date('Y').'-'.date('m').'-'.(date('d') - 2).' UTC');
        $start = $requestStack->getSession()->get('reservation-overview-start', $today);
        $interval = $requestStack->getSession()->get('reservation-overview-interval', 8);

        $year = $requestStack->getSession()->get('reservation-overview-year', date('Y'));

        $objectId = $requestStack->getSession()->get('reservation-overview-objectid', 'all');

        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $apartments = $em->getRepository(Property::class)->findBy(['user' => $user, 'periodicity' => 'Daily', 'publish' =>  true]);

        $firstApartmentId = isset($apartments[0]) ? $apartments[0]->getId() : 0;
        $selectedApartmentId = 1;

        $show = $requestStack->getSession()->get('reservation-overview', 'table');

        return $this->render('reservation_admin_calendar/index.html.twig', [
            'today' => $start,
            'interval' => $interval,
            'year' => $year,
            'selectedApartmentId' => $selectedApartmentId,
            'apartments' => $apartments,
            'objectId' => $objectId,
            'selectedCountry' => 'DE',
            'selectedSubdivision' => 'all',
            'show' => $show,
            'showFirstSteps' => ($firstApartmentId == 0),        ]);
    }

    /**
     * Triggered by the buttons to switch reservation view table/yearly.
     */
    #[Route('/view/{show}', name: 'start.toggle.view', methods: ['GET'])]
    public function indexActionToggle(RequestStack $requestStack, string $show): Response
    {
        if ('yearly' === $show) {
            $requestStack->getSession()->set('reservation-overview', 'yearly');
        } else {
            $requestStack->getSession()->set('reservation-overview', 'table');
        }

        return $this->forward('App\Controller\ReservationAdminCalendarController::indexAction');
    }

    /**
     * Gets the reservation overview.
     */
    #[Route('/table', name: 'reservations.get.table', methods: ['GET'])]
    public function getTableAction(ManagerRegistry $doctrine, RequestStack $requestStack, Request $request): Response
    {
        $year = $request->query->get('year', null);
        if (null === $year) {
            return $this->_handleTableRequest($doctrine, $requestStack, $request);
        } else {
            return $this->_handleTableYearlyRequest($doctrine, $requestStack, $request);
        }
    }

    /**
     * Displays the regular table overview based on a start date and a period.
     */
    private function _handleTableRequest(ManagerRegistry $doctrine, RequestStack $requestStack, Request $request): Response
    {
        $em = $doctrine->getManager();
        $date = $request->query->get('start');
        $intervall = $request->query->get('intervall');
        $objectId = $request->query->get('object');
        $holidayCountry = $request->query->get('holidayCountry', 'DE');
        $selectedSubdivision = $request->query->get('holidaySubdivision', 'all');

        if (null == $date) {
            $date = strtotime(date('Y').'-'.date('m').'-'.(date('d') - 2).' UTC');
        } else {
            $date = strtotime($date.' UTC');   // set timezone to UTC to ignore daylight saving changes
        }

        if (null == $intervall) {
            $intervall = 15;
        }

        $user = $em->getRepository(User::class)->findOneBy(['id' => $this->getUser()]);
        $appartments = $em->getRepository(Property::class)->findBy(['user' => $user, 'periodicity' => 'Daily', 'publish' =>  true]);

        return $this->render('reservation_admin_calendar/reservation_table.html.twig', [
            'appartments' => $appartments,
            'today' => $date,
            'intervall' => $intervall,
            'holidayCountry' => $holidayCountry,
            'selectedSubdivision' => $selectedSubdivision,
        ]);
    }

    /**
     * Loads the actual table based on a given year and apartment.
     *
     * @throws NotFoundHttpException
     */
    private function _handleTableYearlyRequest(ManagerRegistry $doctrine, RequestStack $requestStack, Request $request): Response
    {
        $em = $doctrine->getManager();
        $objectId = $request->query->get('object');
        $year = $request->query->get('year', date('Y'));
        $apartmentId = $request->query->get('apartment');

        $apartment = $em->getRepository(Property::class)->find($apartmentId);

        if (!$apartment instanceof Property) {
            throw new NotFoundHttpException();
        }

        if (!preg_match('/[0-9]{4}/', $year)) {
            throw new NotFoundHttpException();
        }

        $user = $em->getRepository(User::class)->findOneBy(['id' => $this->getUser()]);
        $appartments = $em->getRepository(Property::class)->findBy(['user' => $user, 'periodicity' => 'Daily', 'publish' =>  true]);


        $requestStack->getSession()->set('reservation-overview-objectid', $objectId);
        $requestStack->getSession()->set('reservation-overview-year', $year);
        $requestStack->getSession()->set('reservation-overview-apartment', $apartment->getId());
        $requestStack->getSession()->set('reservation-overview', 'yearly');

        return $this->render('reservation_admin_calendar/reservation_table_year.html.twig', [
            'appartments' => $appartments,
            'year' => $year,
            'apartment' => $apartment,
        ]);
    }
}
