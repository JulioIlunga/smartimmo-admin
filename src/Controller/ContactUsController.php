<?php

namespace App\Controller;

use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactUsController extends AbstractController
{
    #[Route('/contact/us', name: 'contact_us')]
    public function index(Request $request, MailerService $mailer, RequestStack $requestStack): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $recaptchaSiteKey = $_ENV['RECAPTCHA_SITE_KEY']; // Récupération de la clé

        if ($request->isMethod('POST'))
        {

            $recaptchaResponse = $request->request->get('g-recaptcha-response');
            $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'];

            $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}");
            $responseKeys = json_decode($response, true);


            if ($responseKeys["success"])
            {

                $firstname = $request->request->get('firstname');
                $name = $request->request->get('name');
                $adressMail = $request->request->get('email');
                $about = $request->request->get('about');
                $subject = $request->request->get('subject');
                $message = $request->request->get('message');

                $mailer->sendTemplatedMail(
                    "mckayguerschom22@gmail.com",
                    $subject,
                    'contact_us/email.html.twig',[
                        'firstname'=>$firstname,
                        'name'=>$name,
                        'about'=>$about,
                        'subject'=>$subject,
                        'adressMail'=>$adressMail,
                        'message'=>$message,
                    ]
                );

                $this->addFlash('success', 'Merci pour votre message, nous vous repondrons sous peu');

                return $this->redirectToRoute('contact_us');

            }
        }
        return $this->render('contact_us/index.html.twig', [
            'recaptcha_site_key' => $recaptchaSiteKey,
        ]);
    }
}
