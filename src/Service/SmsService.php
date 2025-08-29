<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{
    private $client;

    public function __construct(
        HttpClientInterface $client
    ){
        $this->client = $client;
    }

    public function smsService($phone, $messageToBeSent): int
    {
        $code = 0;

        try {
            $frm = 'S-IMMO APP';
            $token_ = 'QK4R46Q54USGA66';
            $ph = '243'.substr($phone, 1);
            $message = $messageToBeSent;
            $response = $this->client->request('GET', 'https://api.keccel.com/sms/v1/message.asp?token=' . $token_ . '&from=' . $frm . '&to=' . $ph . '&message=' . $message, []);

            if ($response->getStatusCode() == 200 or $response->getStatusCode() == 201) {
                $data = $response->getContent();
                if (str_contains($data, 'SENT')) {
                    $code = 1;
                }else{
                    $code = 0;
                }
            }
        } catch (TransportExceptionInterface $e) {}
        return $code;
    }
}