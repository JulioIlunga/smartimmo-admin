<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsappService
{
    private $client;
    private $token;

    public function __construct(HttpClientInterface $client, string $token)
    {
        $this->client = $client;
        $this->token = $token;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendWhatsappMessage($phone, $code): void
    {
        try {

            $url = "https://graph.facebook.com/v22.0/670125546173689/messages/";

            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => strval($phone),
                    'type' => 'template',
                    'template' => [
                        'name' => 'confirmation',
                        'language' => [
                            'code' => 'en_US',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => strval($code),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => strval($code), // this will be injected into the dynamic URL
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
//            $response = $e->getResponse();
//            $errorBody = $response->getContent(false); // Get raw error content
        }
    }
}
