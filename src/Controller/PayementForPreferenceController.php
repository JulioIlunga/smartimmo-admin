<?php

namespace App\Controller;

use App\Repository\AdminConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\PaymentForPreference;
use App\Entity\Preference;
use App\Entity\Property;
use App\Repository\PaymentForPreferenceRepository;
use App\Repository\PreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayementForPreferenceController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/payement/for/preference', name: 'app_payement_for_preference', methods: ['POST'])]
    public function index(Request $request, PreferenceRepository $preferenceRepository, EntityManagerInterface $entityManager, PaymentForPreferenceRepository $paymentForPreferenceRepository, AdminConfigurationRepository $adminConfigurationRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $amount = (int)$adminConfigurationRepository->find(['id' => 1])->getPreferencePrice();
        $preference = $preferenceRepository->findOneBy(['id' => $data['preference_id'] ]);
        $payment = $paymentForPreferenceRepository->findOneBy(['preference' => $preference,'user' => $this->getUser(),'status' => false]);

        if (!$data) {
            return new JsonResponse(['message' => 'Données invalides'], 400);
        }

        if(!$preference){
            return new JsonResponse(['message' => 'Préférence non trouvée'], 400);
        }

        try {

            if(!$payment){
                //New Payment
                $payment = new PaymentForPreference();
                $payment
                ->setCode('Pay-' . uniqid())
                ->setAmount($amount)
                ->setStatus(false)
                ->setPreference($preference)
                ->setTransactionId($preference->getCode())
                ->setUser($this->getUser());

                $entityManager->persist($payment);
            } else {
                $payment
                    ->setAmount($amount)
                    ->setTransactionId($preference->getCode())
                    ->setPreference($preference);
            }
            $entityManager->flush();

            $baseUrl = $this->getParameter('smartPay_BasUrl');

            $onlinePaymentUrl = $this->makeRequestPaymentLink($baseUrl, $payment);

            if (!$onlinePaymentUrl) {
                throw new \Exception('Erreur lors de la création du lien de paiement');
            }

            return new JsonResponse(
                [
                    'smart_pay_url' => $onlinePaymentUrl,
                    'paymentCode' => $payment->getCode(),
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

    public function makeRequestPaymentLink($baseUrl, PaymentForPreference $payment)
    {
        $redirectionToPaymentUrl = null;
        $SmartPayKey = $this->getParameter('smartPay_Key');

        $response = $this->client->request('POST', $baseUrl . '/api/transaction', [
            'json' => [
                "merchantCode" => $SmartPayKey,
                "code" => $payment->getCode(),
                "amount" => strval($payment->getAmount()),
                "currency" => 'USD',
                "phone" => $payment->getUser()->getPhone(),
                "description" => 'Paiement pour l\'alert de recommandation de ' . $payment->getUser()->getFirstname() . ' ' . $payment->getUser()->getName()
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
    #[Route('/api/payment/for/preference/status/{code}', name: 'app_check_payment_for_preference_status', methods: ['GET'])]
    public function checkPreferenceStatus(EntityManagerInterface $entityManager, $code): JsonResponse
    {
        $payment = $entityManager->getRepository(PaymentForPreference::class)->findOneBy(['code' => $code]);
        if($payment){
            return new JsonResponse([
                'status' => $payment->isStatus() ? 'true' : 'false'
            ]);
        }else{
            return new JsonResponse([
                'status' => 'false'
            ]);
        }
    }

    #[Route('/transaction/preference/check/manually/status/{code}', name: 'app_check_manually_transaction_status_for_preference', methods: ['POST','GET'])]
    public function callbackCheck(EntityManagerInterface $em, $code): Response
    {
        $payment = $em->getRepository(PaymentForPreference::class)->findOneBy(['code' => $code]);
        $baseUrl = $this->getParameter('smartPay_BasUrl');

        try {
            $response = $this->client->request('GET', $baseUrl . '/api/check/transaction/' . $payment->getCode());
            if ($response->getStatusCode() == 200 or $response->getStatusCode() == 201) {
                $json = json_decode($response->getContent(), true);
                if ($json['code'] == '1') {
                    $payment->setStatus(true);
                    $payment->getPreference()->setPaid(true);
                    $payment->getPreference()->setStatus(true);
                    $em->flush();
                }
                // elseif ($json['status'] == '6' || $json['status'] == '7' || $json['status'] == '9') {
                //     // $payment->setCancel(true);
                //     // $em->flush();
                // }
                $em->flush();
            }

        } catch (TransportExceptionInterface $e) {
        }

        return new JsonResponse('ok');
    }

    #[Route('/transaction/callback/response', name: 'app_transaction_callback', methods: ['POST'])]
    public function callbackMethod(PaymentForPreferenceRepository $paymentForPreferenceRepository ,EntityManagerInterface $em): Response
    {
        $data = file_get_contents('php://input');
        $json = json_decode($data, true);
        $reference = $json['reference'];
        $code = $json['code'];
        
        $payment = $paymentForPreferenceRepository->findOneBy(['code' => $reference]);

        if ($payment) {
            if ($code == '1') {
                $payment->setStatus(true);
                $payment->getPreference()->setPaid(true);
                $payment->getPreference()->setStatus(true);
                $em->flush();
            }
            // elseif ($json['status'] == '6' || $json['status'] == '7' || $json['status'] == '9') {
            //     // $payment->setCancel(true);
            //     // $em->flush();
            // }
        }

        return new JsonResponse('ok');
    }
}
