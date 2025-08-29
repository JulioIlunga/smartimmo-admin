<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\PropertyStatusRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AgentChangeStatusController extends AbstractController
{
    #[Route('/open/source/for/agent/change/status/{agentCode}/{propertyUuid}', name: 'app_agent_change_status')]
    public function index($propertyUuid, $agentCode, PropertyRepository $propertyRepository, UserRepository $userRepository, PropertyStatusRepository $propertyStatusRepository): Response
    {
        $agent = $userRepository->findOneBy(['code' => $agentCode]);
        $property = $propertyRepository->findOneBy(['uuidProperty' => $propertyUuid, 'user' => $agent]);
        $status = $propertyStatusRepository->findAll();

        return $this->render('agent_change_status/index.html.twig', [
            'property' => $property,
            'status' => $status,
        ]);
    }

    #[Route('/open/source/for/agent/change/status/of/property/{id}/{propertyId}', name: 'app_agent_change_status_of_property')]
    public function changePropertyStatusForAgent($id, $propertyId, ManagerRegistry $doctrine, PropertyStatusRepository $propertyStatusRepository, PropertyRepository $propertyRepository): RedirectResponse{

        $em = $doctrine->getManager();
        $status = $propertyStatusRepository->findOneBy(['id' => $id]);
        $property = $propertyRepository->findOneBy(['id' => $propertyId]);
        if ($status->getId() == 4){
            $property->setPublish(false);
        }
        $property->setPropertyStatus($status);
        $property->setUnpublishAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+1 month'))));
        $em->flush();

        $this->addFlash('success',  "Le statut de l'annonce a été modifié avec succès.");

        return $this->redirectToRoute('app_agent_change_status', ['agentCode' => $property->getUser()->getCode(), 'propertyUuid' => $property->getUuidProperty()]);
    }
}
