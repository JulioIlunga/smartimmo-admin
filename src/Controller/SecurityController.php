<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class SecurityController extends AbstractController
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // AJAX: return only the form
        if ($request->isXmlHttpRequest()) {
            return $this->render('security/login_modal.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
            ]);
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): RedirectResponse
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
//        return new RedirectResponse($this->router->generate('app_listing'));

    }

//    #[Route(path: '/login/modal', name: 'app_login_modal')]
//    public function loginModal(AuthenticationUtils $authenticationUtils, Request $request): Response
//    {
//        // get the login error if there is one
//        $error = $authenticationUtils->getLastAuthenticationError();
//
//        // last username entered by the user
//        $lastUsername = $authenticationUtils->getLastUsername();
//
//        // If this is an AJAX request (for the modal), return just the form
//        if ($request->isXmlHttpRequest()) {
//            return $this->render('security/login_modal.html.twig', [
//                'last_username' => $lastUsername,
//                'error' => $error,
//            ]);
//        }
//
//        return $this->render('security/login_modal.html.twig', [
//            'last_username' => $lastUsername,
//            'error' => $error,
//        ]);
//    }
}
