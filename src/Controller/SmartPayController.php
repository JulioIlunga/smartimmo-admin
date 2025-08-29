<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\InvoiceRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/smart/pay')]
class SmartPayController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/', name: 'app_smart_pay', methods: ['POST'])]
    public function index(Request $request, ReservationRepository $reservationRepository, InvoiceRepository $invoiceRepository, ManagerRegistry $doctrine): JsonResponse
    {
        $em = $doctrine->getManager();
        $data = json_decode($request->getContent(), true);
        $amountToBePaid = $data['amount_to_pay'];
        $paymentType = $data['payment_type'];

        if (!$data) {
            return new JsonResponse(['message' => 'Données invalides'], 400);
        }

        try {

            $data = json_decode($request->getContent(), true);

            $reservation = $reservationRepository->findOneBy(['id' => $data['reservation_id']]);
            if (!$reservation) {
                throw new \Exception('Réservation non trouvée');
            }

            $invoice = $invoiceRepository->findInvoice($reservation);

            //New Payment
            $payment = new Payment();
            $payment
                ->setCode('Pay-' . uniqid())
                ->setStatus(false)
                ->setCancel(false)
                ->setPaymentMethod('smartpay');

            if($invoice){

                $invoice[0]
                    ->setAmount($reservation->getTotalAmount())
                    ->setAmountToBePaid($amountToBePaid);

                $reservation->setPayementType($paymentType);

                $payment
                    ->setInvoice($invoice[0])
                    ->setTransactionId($invoice[0]->getCode())
                    ->setAmount($invoice[0]->getAmountToBePaid());

            }else{

                $invoice = new Invoice();
                $invoice
                    ->setCode('Inv-' . uniqid())
                    ->setAmount($reservation->getTotalAmount())
                    ->setAmountToBePaid($amountToBePaid)
                    ->setAmountPaid(0)
                    ->setPaid(false)
                    ->setClosed(false)
                    ->setReservation($reservation)
                    ->setUser($reservation->getUser())
                ;

                $reservation->setPayementType($paymentType);

                $em->persist($invoice);

                $payment
                    ->setInvoice($invoice)
                    ->setTransactionId($invoice->getCode())
                    ->setAmount($invoice->getAmountToBePaid());

            }
            $em->persist($payment);
            $em->flush();

            $baseUrl = $this->getParameter('smartPay_BasUrl');
            $onlinePaymentUrl = $this->makeRequestPaymentLink($baseUrl, $payment, $amountToBePaid);

            if (!$onlinePaymentUrl) {
                throw new \Exception('Erreur lors de la création du lien de paiement');
            }

            return new JsonResponse(
                [
                    'smart_pay_url' => $onlinePaymentUrl,
                    'paymentCode' => $payment->getCode()
                ], 200, [
                "Content-Type" => "application/json"
            ]);


        } catch (\Exception $e) {

            return new JsonResponse([
                'message' => $e->getMessage()
            ], 400, [
                "Content-Type" => "application/json"
            ]);

        }
    }

    public function makeRequestPaymentLink($baseUrl, Payment $payment, $amountPaid)
    {
        $redirectionToPaymentUrl = null;
        $SmartPayKey = $this->getParameter('smartPay_Key');
        $reservation = $payment->getInvoice()->getReservation();

        $response = $this->client->request('POST', $baseUrl . '/api/transaction', [
            'json' => [
                "merchantCode" => $SmartPayKey,
                "code" => $payment->getCode(),
                "amount" => strval($amountPaid),
                "currency" => 'USD',
                "phone" => $reservation->getPhone(),
                "description" => 'Paiement pour la réservation de ' . $reservation->getFirstname() . ' ' . $reservation->getLastname() .
                               ' - ' . 'Location court séjour' .
                               ' du ' . $reservation->getCheckIn()->format('d/m/Y') .
                               ' au ' . $reservation->getCheckOut()->format('d/m/Y'),
            ]
        ]);

        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 201) {
            $data = $response->toArray();
            if ($response->toArray()['code'] == '1') {
                $redirectionToPaymentUrl = $data['checkoutUrl'];
            }
        }

        return $redirectionToPaymentUrl;
    }



//    #[Route('/api/invoice/status/{code}', name: 'app_check_invoice_status', methods: ['GET'])]
//    public function checkInvoiceStatus(Payment $payment): JsonResponse
//    {
//        return new JsonResponse([
//            'status' => $payment->isStatus() ? 'true' : 'false'
//        ]);
//    }
//
//    #[Route('/transaction/check/manually/status/{code}', name: 'app_check_manually_transaction_status', methods: ['POST','GET'])]
//    public function callbackCheck(ManagerRegistry $doctrine, $code): Response
//    {
//        $em = $doctrine->getManager();
//        $payment = $em->getRepository(Payment::class)->findOneBy(['code' => $code]);
//        $baseUrl = $this->getParameter('smartPay_BasUrl');
//
//        try {
//            $response = $this->client->request('GET', $baseUrl . '/api/check/transaction/' . $payment->getCode());
//            if ($response->getStatusCode() == 200 or $response->getStatusCode() == 201) {
//                $json = json_decode($response->getContent(), true);
//                if ($json['code'] == '1') {
//                    $payment->setStatus(true);
//                    $payment->getInvoice()->setPaid(true);
//                    $this->extractedPayment($payment, $em);
//                    $em->flush();
//
//                }elseif ($json['status'] == '6' || $json['status'] == '7' || $json['status'] == '9') {
//                    $payment->setCancel(true);
//                    $em->flush();
//                }
//                $em->flush();
//            }
//
//        } catch (TransportExceptionInterface $e) {
//        }
//
//        return new JsonResponse('ok');
//    }
//
//    #[Route('/transaction/callback/response', name: 'app_transaction_callback', methods: ['POST'])]
//    public function callbackMethod(PaymentRepository $paymentRepository ,InvoiceRepository $invoiceRepository, ManagerRegistry $doctrine): Response
//    {
//        $em = $doctrine->getManager();
//        $data = file_get_contents('php://input');
//        $json = json_decode($data, true);
//        $reference = $json['reference'];
//        $code = $json['code'];
//
//        $payment = $paymentRepository->findOneBy(['code' => $reference]);
//
//        if ($payment) {
//            if ($code == '1') {
//                $payment
//                    ->setStatus(true)
//                    ->getInvoice()->setPaid(true);
//
//                $this->extractedPayment($payment, $em);
//
//            }elseif ($json['status'] == '6' || $json['status'] == '7' || $json['status'] == '9') {
//                $payment->setCancel(true);
//                $em->flush();
//            }
//        }
//
//        return new JsonResponse('ok');
//    }
//
//    /**
//     * @param object|Payment|null $payment
//     * @param \Doctrine\Persistence\ObjectManager $em
//     * @return void
//     */
//    public function extractedPayment(Payment|null $payment, ObjectManager $em): void
//    {
//        $payment->getInvoice()->getReservation()->setConfirmed(true);
//
//        $em->flush();
//
//        if ($payment->getInvoice()->getReservation()->getPayementType() == 'full') {
//            $payment->getInvoice()->setClosed(true);
//            $payment->getInvoice()->setAmountPaid($payment->getAmount());
//            $em->flush();
//        } else {
//            $amountPaid = $payment->getAmount() + $payment->getInvoice()->getAmountPaid();
//            $payment->getInvoice()->setAmountPaid($amountPaid);
//            if ($payment->getInvoice()->getAmountPaid() == $payment->getInvoice()->getAmount()) {
//                $payment->getInvoice()->setClosed(true);
//                $em->flush();
//            }
//            $em->flush();
//        }
//    }

}
