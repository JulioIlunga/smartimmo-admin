<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaValidator
{
    private $secretKey;
    private $httpClient;

    public function __construct(string $secretKey, HttpClientInterface $httpClient)
    {
        $this->secretKey = $secretKey;
        $this->httpClient = $httpClient;
    }

    public function verify(string $recaptchaResponse, string $userIp): bool
    {
        $response = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $this->secretKey,
                'response' => $recaptchaResponse,
                'remoteip' => $userIp,
            ],
        ]);

        $responseData = $response->toArray();

        return isset($responseData['success']) && $responseData['success'] === true;
    }
}
