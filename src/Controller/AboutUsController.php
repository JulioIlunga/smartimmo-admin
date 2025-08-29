<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutUsController extends AbstractController
{
    #[Route('/about/us', name: 'about_us')]
    public function index( RequestStack $requestStack): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        return $this->render('about_us/index.html.twig', []);
    }

    #[Route('/about/us/agence', name: 'about_us_agence')]
    public function agence(RequestStack $requestStack): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        return $this->render('about_us/agence.html.twig', []);
    }
}
