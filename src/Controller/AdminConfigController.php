<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AdminConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin/smart/immo')]
class AdminConfigController extends AbstractController
{
    #[Route('/config', name: 'app_admin_config', methods: ['GET', 'POST'])]
    public function index(Request $request, AdminConfigurationRepository $adminConfigurationRepository, EntityManagerInterface $entityManager): Response
    {
        $config = $adminConfigurationRepository->findOneBy(['id' => 1]);
        $configLookingAgent = $adminConfigurationRepository->findOneBy(['id' => 2]);
        if ($request->isMethod('POST')) {
            $preferencePrice = $request->request->get('preferencePrice');
            $lookingForAgent = $request->request->get('lookingForAgent');


            $config->setPreferencePrice(floatval($preferencePrice));
            $configLookingAgent->setLookingForAgentUrl($lookingForAgent);

            $entityManager->flush();
            $this->addFlash('success', 'Prix des alertes de recommandation mis à jour avec succès.');

            return $this->redirectToRoute('app_admin_config');
        }
        return $this->render('admin_config/index.html.twig', [
            'config' => $config,
            'configLookingAgent' => $configLookingAgent,
        ]);
    }
}
