<?php

namespace App\Controller;

use App\Entity\ServiceSup;
use App\Form\ServiceSupType;
use App\Repository\ServiceSupRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/agent/work/space')]
class ServiceSupController extends AbstractController
{
    #[Route('/services/additionnal', name: 'additionnal_services')]
    public function additionnalServices(ServiceSupRepository $serviceSupRepository, UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $services = $serviceSupRepository->findBy(['user' => $user->getId(), 'status' => true]);

        return $this->render('agent_work_space/serviceSup/index.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('services/additionnal/create', name: 'additionnal_services_create', methods: ['GET', 'POST'])]
    public function additionnalServicesCreate(ManagerRegistry $doctrine, Request $request, UserRepository $userRepository): Response
    {
        $service = new ServiceSup();
        $form = $this->createForm(ServiceSupType::class, $service);
        $form->handleRequest($request);

        $em = $doctrine->getManager();
        if ($form->isSubmitted() && $form->isValid()) {
            // $data = $form->getData();
            $service
                ->setCode(uniqid())
                // ->setName($data->getName())
                ->setName($form->get('name')->getData())
                ->setPrice($form->get('price')->getData())
                ->setStatus(true)
                ->setUser($this->getUser())
            ;

            // dd($service);

            $em->persist($service);
            $em->flush();

            $this->addFlash('success', 'Le service supplémentaire a été crée avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('agent_work_space/serviceSup/new_service.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
        ]);
    }  
}
