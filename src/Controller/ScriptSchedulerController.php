<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/script/scheduler')]
class ScriptSchedulerController extends AbstractController
{
    #[Route('/update/property/status', name: 'app_script_scheduler', methods: ['GET', 'POST'])]
    public function index(PropertyRepository $propertyRepository, ManagerRegistry $doctrine): Response
    {
        $today = strtotime(date('Y-m-d'));
        $properties = $propertyRepository->findBy(['publish' =>  true]);
        foreach ($properties as  $property){
            if ($property->getPropertyStatus()->getId() != 1){
                $unpublishAt = strtotime($property->getUnpublishAt()->format("Y-m-d"));
                if ($today >= $unpublishAt){
                    $property->setPublish(false);
                    $em = $doctrine->getManager();
                    $em->flush();
                }
            }
        }

        return new Response('success', 200);
    }
}
