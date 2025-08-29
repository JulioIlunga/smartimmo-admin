<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Property;
use App\Entity\Reservation;
use App\Repository\InvoiceRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use App\Form\PaymentType;
use App\Form\ReservationType;
use App\Repository\PropertyRepository;
use App\Repository\ServiceSupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/agent/')]
class ReservationAdminController extends AbstractController
{
    #[Route('reservation', name: 'app_reservation_admin')]
    public function index(ReservationRepository $reservationRepository, UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $reservations = $reservationRepository->findByAdmin($user,true);

        return $this->render('reservation_admin/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[Route('reservation/new', name: 'reservation_new', methods: ['GET', 'POST'])]
    public function newReservation(Request $request, ReservationRepository $reservationRepository, UserRepository $userRepository, PropertyRepository $propertyRepository, ServiceSupRepository $serviceRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $properties = $propertyRepository->findBy(['user' => $user, 'periodicity' => 'Daily']);
        $services = $serviceRepository->findBy(['user' => $user->getId(), 'status' => true]);

        return $this->render('reservation_admin/new/new.html.twig', [
            'services' => $services,
            'availableDate' => false,
            'availabilityMsg' => '',
            'properties' => $properties,
            'disabledDates' => [],
            'property' => null,
        ]);
    }

    #[Route('reservation/check/property/unavailable-dates', name: 'app_reservation_admin_property_unavailable_dates', methods: ['POST'])]
    public function getUnavailableDates(Request $request, ReservationRepository $reservationRepository, PropertyRepository $propertyRepository): Response
    {
        $property = $propertyRepository->find($request->request->get('propertyId'));
        $reservations = $reservationRepository->findBy(['property' => $property, 'confirmed' => true]);
        $disabledDates = [];
        foreach ($reservations as $booked) {
            $start = $booked->getCheckIn();
            $end = $booked->getCheckOut();
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

            foreach ($period as $date) {
                $disabledDates[] = $date->format('Y-m-d');
            }
        }

        return $this->render('reservation_admin/new/_date.html.twig', [
            'disabledDates' => $disabledDates,
            'property' => $property,
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/reservation/admin/saving/check/and/persist', name: 'app_reservation_admin_saving', methods: ['POST'])]
    public function adminReservationSaving(Request $request, PropertyRepository $propertyRepository, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        if(!$user){
            return $this->redirectToRoute('app_login');
        }

        $property = $propertyRepository->findOneBy(['id' => $request->request->get('property')]);
        $checkIn = new \DateTime($request->request->get('checkIn'));
        $checkOut = new \DateTime($request->request->get('checkOut'));

        $pourcentage = 0;
        $reservation = new Reservation();
        $reservation->setProperty($property)
            ->setCode(uniqid())
            ->setFirstname($request->request->get('clientFirstname'))
            ->setLastname($request->request->get('clientName'))
            ->setEmail($request->request->get('clientEmail'))
            ->setPhone($request->request->get('clientPhone'))
            ->setUser($user)
            ->setStatus(true)
            ->setConfirmed(true)
        ;
        $duration = $checkIn->diff($checkOut)->days;
        $totalAmount = $property->getPrice() * $duration;
        $commission = $totalAmount * $pourcentage;
        $totalPayout = $totalAmount + $commission;

        $reservation->setDuration($duration)
            ->setCheckIn(new \DateTimeImmutable($request->request->get('checkIn') . "13:00:00"))
            ->setCheckOut(new \DateTimeImmutable($request->request->get('checkOut') . "11:00:00"))
            ->setDateIn(new \DateTime($request->request->get('checkIn')))
            ->setDateOut(new \DateTime($request->request->get('checkOut')))
            ->setTotalAmount($totalPayout);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $invoice = new Invoice();
        if($request->request->get('paymentType') == 'full'){
            $reservation->setPayementType($request->request->get('paymentType'));

            $payment = $this->getPaymentExtracted($invoice, $reservation);

        }else if ($request->request->get('paymentType') == 'partial' && $request->request->get('amount-to-pay') == $reservation->getTotalAmount()){
            $reservation->setPayementType('full');

            $payment = $this->getPaymentExtracted($invoice, $reservation);

        }else{
            $reservation->setPayementType($request->request->get('paymentType'));

            $invoice
                ->setCode('Inv-' . uniqid())
                ->setAmount($reservation->getTotalAmount())
                ->setAmountToBePaid($reservation->getTotalAmount())
                ->setAmountPaid($request->request->get('amount-to-pay'))
                ->setPaid(false)
                ->setClosed(false)
                ->setReservation($reservation)
                ->setUser($reservation->getUser());

            $payment = new Payment();
            $payment
                ->setCode('Pay-' . uniqid())
                ->setStatus(true)
                ->setCancel(false)
                ->setPaymentMethod('cash')
                ->setInvoice($invoice)
                ->setTransactionId($invoice->getCode())
                ->setAmount($invoice->getAmountPaid());

        }
         $entityManager->persist($payment);
         $entityManager->persist($invoice);
         $entityManager->flush();

        return $this->redirectToRoute('app_reservation_admin');
    }


    #[Route('reservation/check/date/available/{propertyId}', name: 'app_reservation_check_available_date', methods: ['POST'])]
    public function checkAvailableAjax(Request $request, PropertyRepository $propertyRepository, ReservationRepository $reservationRepository, $propertyId): Response
    {

        $property = $propertyRepository->findOneBy(['id' => $propertyId]);

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
        return $this->render('reservation_admin/new/_reservation_date_check.html.twig', [
            'property' => $property,
            'availabilityMsg' => $availabilityMsg,
            'availableDate' => $error
        ]);

    }

    #[Route('reservation/details/{id}', name: 'app_reservation_details')]
    public function details(Reservation $reservation): Response
    {
        $user = $this->getUser();

        return $this->render('reservation_admin/modal/_details.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('reservation/details/invoice/{id}', name: 'app_reservation_invoice')]
    public function invoice(Invoice $invoice, UserRepository $userRepository): Response
    {

        return $this->render('reservation_admin/modal/_invoice.html.twig', [
            'invoice' => $invoice,
            'reservation' => $invoice->getReservation()
        ]);
    }

    #[Route('reservation/invoice/payment/{id}', name: 'app_reservation_invoice_payment',)]
    public function payment(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si la facture est close
        if ($invoice->isClosed()) {
            $this->addFlash('error', 'Impossible d\'ajouter un paiement à une facture clôturée.');
            return $this->redirectToRoute('app_reservation_invoice', ['id' => $invoice->getId()]);
        }

        $payment = new Payment();
        $payment->setInvoice($invoice);

        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $amount = $data->getAmount();

            $totalPaid = $invoice->getAmountPaid() + $amount;

            if ($totalPaid > $invoice->getAmount()) {
                $this->addFlash('warning', 'Vous ne pouvez payez au delà du montant restant');

                return $this->render('reservation_admin/modal/_payment_form.html.twig', [
                        'invoice' => $invoice,
                        'form' => $form->createView()
                    ]);
            }

            if ($totalPaid <= $invoice->getAmount()) {

                $payment
                    ->setCode(uniqid())
                    ->setAmount($amount)
                    ->setPaymentMethod($form->getData()->getPaymentMethod())
                    ->setStatus(true)
                    ->setCreatedAt(new \DateTime())
                ;

                $entityManager->persist($payment);
                $entityManager->flush();

                $invoice->setAmountPaid($totalPaid);
                if($invoice->getAmountPaid() == $invoice->getAmount()){
                    $invoice->setClosed(true);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Le paiement a été enregistré avec succès');
                return $this->render('reservation_admin/modal/_invoice.html.twig', [
                        'invoice' => $invoice,
                    ]);
            }
        }

        return $this->render('reservation_admin/modal/_payment_form.html.twig', [
            'invoice' => $invoice,
            'form' => $form->createView()
        ]);
    }

    /**
     * @param Invoice $invoice
     * @param Reservation $reservation
     * @return Payment
     */
    public function getPaymentExtracted(Invoice $invoice, Reservation $reservation): Payment
    {
        $invoice
            ->setCode('Inv-' . uniqid())
            ->setAmount($reservation->getTotalAmount())
            ->setAmountToBePaid($reservation->getTotalAmount())
            ->setAmountPaid($reservation->getTotalAmount())
            ->setPaid(true)
            ->setClosed(true)
            ->setReservation($reservation)
            ->setUser($reservation->getUser());


        $payment = new Payment();
        $payment
            ->setCode('Pay-' . uniqid())
            ->setStatus(true)
            ->setCancel(false)
            ->setPaymentMethod('cash')
            ->setInvoice($invoice)
            ->setTransactionId($invoice->getCode())
            ->setAmount($invoice->getAmountToBePaid());
        return $payment;
    }

}
