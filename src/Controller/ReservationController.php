<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Entity\Property;
use App\Entity\ServiceSup;
use App\Repository\PropertyRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ServiceSupRepository;
use App\Service\MailerService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/reservation/property/{propertyUuid}', name: 'app_reservation')]
    public function index($propertyUuid, Request $request, ReservationRepository $reservationRepository, PropertyRepository $propertyRepository, UserRepository $userRepository, EntityManagerInterface $entityManager,ServiceSupRepository $serviceSupRepository): Response
    {

        $property = $propertyRepository->findOneBy(['uuidProperty' => $propertyUuid]);
        $propertyFromService = $serviceSupRepository->getServiceSupFromPropertyId($property->getId());
        $checkIn = new \DateTime($request->request->get('checkIn'));
        $checkOut = new \DateTime($request->request->get('checkOut'));
        $services = $request->request->all('service');

        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        if(!$user){
            return $this->redirectToRoute('app_login');
        }

        $reservation = $reservationRepository->findOneBy(['user' => $user, 'property' => $property, 'confirmed' => false]);
        $pourcentage = 0;

        if(!$reservation){
            $reservation = new Reservation();
            $reservation->setProperty($property)
                ->setCode(uniqid())
                ->setFirstname($user->getFirstname())
                ->setLastname($user->getName())
                ->setEmail($user->getEmail())
                ->setPhone($user->getPhone())
                ->setUser($user)
                ->setStatus(false)
                ->setConfirmed(false)
            ;
            if($services){
                foreach ($services as $service){
                    foreach ($propertyFromService as $serviceSup){
                        if($serviceSup->getId() == $service){
                            $reservation->addService($serviceSup);
                        }
                        $entityManager->persist($reservation);
                        $entityManager->flush();
                    }
                }

            }
            list($duration, $totalAmount, $commission, $totalPayout) = $this->extractedReservationCheck($checkIn, $checkOut, $property, $pourcentage, $reservation, $request);

            $entityManager->persist($reservation);
            $entityManager->flush();
        }else{
            if($services != null){
                foreach ($reservation->getServices() as $service){
                    $reservation->removeService($service);
                    $entityManager->flush();
                }

                foreach ($services as $service){
                    foreach ($propertyFromService as $serviceSup){
                        if($serviceSup->getId() == $service){
                            $reservation->addService($serviceSup);
                        }
                        $entityManager->flush();
                    }
                }
            }
        }
        if ($request->request->get('checkIn') != null && $request->request->get('checkOut') != null) {
            list($duration, $totalAmount, $commission, $totalPayout) = $this->extractedReservationCheck($checkIn, $checkOut, $property, $pourcentage, $reservation, $request);
            $entityManager->flush();
        }

        return $this->render('reservation/index.html.twig', [
            'reservation' => $reservation,
            'pourcentage' => $pourcentage,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/reservation/confirmation/{code}', name: 'app_reservation_confirmation')]
    public function payementConfirmation(Payment $payment, ReservationRepository $reservationRepository, SmsService $ss, SessionInterface $session, MailerService $mailerService): Response
    {
        $reservation = $reservationRepository->findOneBy(['id' => $payment->getInvoice()->getReservation()]);

        if (!$payment) {
            throw new \Exception('Facture non trouvée');
        }

        if ($reservation->getProperty()->getUser()->getEmail() !== ''){
            $to = $reservation->getProperty()->getUser()->getEmail();
            $agentName = $reservation->getProperty()->getUser()->getName();
            $link = $this->generateUrl('app_reservation_admin', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $mailerService->sendTemplatedMail(
                $to,
                'Nouvelle Réservation',
                'reservation/confirmation_email.html.twig',[
                    'agentName' => $agentName,
                    'link'=>$link,
                ]
            );
            // $ss->smsService('0820001019', 'new reservation alert');
        }

        // Nettoyer la session
        $session->remove('reservation_data');

        return $this->render('reservation/confirmation.html.twig', [
            'reservation' => $reservation,
            'invoice' => $payment->getInvoice()
        ]);
    }

    #[Route('/reservation/edit/{code}', name: 'app_reservation_edit', methods: ['POST','GET'])]
    public function edit(Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager, SessionInterface $session, $code): Response
    {
        $reservation = $reservationRepository->findOneBy(['code' => $code]);
        $pourcentage = 0;

        if($request->isMethod('POST')){

            $checkIn = new \DateTime($request->request->get('checkIn'));
            $checkOut = new \DateTime($request->request->get('checkOut'));

            $duration = $checkIn->diff($checkOut)->days;
            $totalAmount = $reservation->getProperty()->getPrice() * $duration;
            $commission = $totalAmount * $pourcentage;
            $totalPayout = $totalAmount + $commission;

            $reservation->setDuration($duration)
                ->setCheckIn(new \DateTimeImmutable($request->request->get('checkIn')."13:00:00"))
                ->setCheckOut(new \DateTimeImmutable($request->request->get('checkOut')."11:00:00"))
                ->setDateIn(new \DateTimeImmutable($request->request->get('checkIn')))
                ->setDateOut(new \DateTimeImmutable($request->request->get('checkOut')))
                ->setTotalAmount($totalPayout)
            ;
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation', ['propertyUuid' => $reservation->getProperty()->getUuidProperty()]);
        }

        return $this->render('reservation/_edit.html.twig', [
            'reservation' => $reservation,
            'error' => true,
            'msg' => null,
        ]);
    }

    #[Route('/reservation/check/date/ajax/', name: 'app_reservation_check_date_ajax', methods: ['POST'])]
    public function editAjax(Request $request, ReservationRepository $reservationRepository): Response
    {

        $reservation = $reservationRepository->findOneBy(['id' => $request->get('reservationId')]);

        $checkIn = $request->request->get('checkIn');
        $checkOut = $request->request->get('checkOut');

        $availableDate = $reservationRepository->findAvailableDate($reservation->getProperty(), $checkIn, $checkOut);

        if($availableDate){
            $msg = "Le logement est déjà reservé pour cette date.";
            $error = true;
        }else{
            $msg = "Le logement est disponible pour cette date.";
            $error = false;
        }
        return $this->render('reservation/_date_check.html.twig', [
            'msg' => $msg,
            'error' => $error
        ]);

    }

    /**
     * @param \DateTime $checkIn
     * @param \DateTime $checkOut
     * @param Property|Agent|null $property
     * @param int $pourcentage
     * @param Reservation $reservation
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function extractedReservationCheck(\DateTime $checkIn, \DateTime $checkOut, Property|Agent|null $property, int $pourcentage, Reservation $reservation, Request $request): array
    {
        $duration = $checkIn->diff($checkOut)->days;
        $totalAmount = $property->getPrice() * $duration;
        $commission = $totalAmount * $pourcentage;
        $totalPayout = $totalAmount + $commission;

        if($reservation->getServices() != null){
            foreach ($reservation->getServices() as $service){
                $totalPayout += $service->getPrice();
            }
        }

        $reservation->setDuration($duration)
            ->setCheckIn(new \DateTimeImmutable($request->request->get('checkIn') . "13:00:00"))
            ->setCheckOut(new \DateTimeImmutable($request->request->get('checkOut') . "11:00:00"))
            ->setDateIn(new \DateTime($request->request->get('checkIn')))
            ->setDateOut(new \DateTime($request->request->get('checkOut')))
            ->setTotalAmount($totalPayout);
        return array($duration, $totalAmount, $commission, $totalPayout);
    }

    #[Route('/reservation/check/date/available/ajax/', name: 'app_reservation_check_available_date_ajax', methods: ['POST'])]
    public function checkAvailableAjax(Request $request, PropertyRepository $propertyRepository, ReservationRepository $reservationRepository): Response
    {

        $property = $propertyRepository->findOneBy(['id' => $request->get('propertyId')]);

        $checkIn = $request->request->get('checkIn');
        $checkOut = $request->request->get('checkOut');

        $availableDate = $reservationRepository->findAvailableDate($property, $checkIn, $checkOut);

        if($availableDate){
            $availabilityMsg = "Pas de disponibilité pour cette date";
            $error = false;
        }else{
            $availabilityMsg = "Date disponible";
            $error = true;
        }
        return $this->render('property_details/_reservation_airbnb_date_check.html.twig', [
            'property' => $property,
            'availabilityMsg' => $availabilityMsg,
            'availableDate' => $error
        ]);

    }
}
