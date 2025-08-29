<?php

namespace App\Controller;

use App\Entity\Agency;
use App\Entity\AgencyAgent;
use App\Entity\Property;
use App\Entity\User;
use App\Form\AgencyAgentType;
use App\Form\AgencyType;
use App\Repository\AgencyAgentRepository;
use App\Repository\AgencyRepository;
use App\Repository\UserRepository;
use App\Service\AwsS3Service;
use App\Service\CSRFProtectionService;
use App\Service\ImageUploaderService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/agent')]
class AgencyController extends AbstractController
{
    #[Route('/agency', name: 'app_agency')]
    public function index(AgencyAgentRepository $agencyAgentRepository, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($this->getUser());
        $agency = $agencyAgentRepository->findOneBy(['agent' => $user, 'status' =>  true]);

        $agents = null;
        if($agency){
            $agents = $agencyAgentRepository->findBy(['agency' => $agency->getAgency()]);
        }

        return $this->render('agent_work_space/agency/index.html.twig', [
            'user' => $user,
            'agency' => $agency,
            'agents' => $agents,
        ]);
    }

    #[Route('/agency/create/agency', name: 'app_agency_create_agency', methods: ['GET', 'POST'])]
    public function createAgency(ManagerRegistry $doctrine, Request $request, UserRepository $userRepository, ImageUploaderService $imageUploaderService): Response
    {
        $agency = new Agency();
        $form = $this->createForm(AgencyType::class, $agency);
        $form->handleRequest($request);
        $user = $userRepository->find($this->getUser());

        $em = $doctrine->getManager();
        if ($form->isSubmitted() && $form->isValid()) {
            $agency->setCode('AGC-'.uniqid());
            $agency->setStatus(true);
            $agency->setOwner($user);

            $file = $form->get('logo')->getData();
            if ($file){
                $url = $imageUploaderService->uploadAndResizeImageToS3($file);
                $agency->setLogo($url);
            }
            $em->persist($agency);
            $em->flush();

            $agencyAgent = new AgencyAgent();
            $agencyAgent->setAgency($agency);
            $agencyAgent->setAgent($user);
            $agencyAgent->setStatus(true);
            $agencyAgent->setOwner(true);

            $em->persist($agencyAgent);
            $em->flush();

            $this->addFlash('success', 'L\'agence a été crée avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('agent_work_space/agency/new_agency.html.twig', [
            'agency' => $agency,
            'form' => $form->createView()
        ]);
    }

    #[Route('/agency/edit/{id}/agency', name: 'app_agency_edit_agency', methods: ['GET', 'POST'])]
    public function editAgency(ManagerRegistry $doctrine, Request $request, Agency $agency, ImageUploaderService $imageUploaderService): Response
    {
        $form = $this->createForm(AgencyType::class, $agency);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $file = $form->get('logo')->getData();
            if ($file){
                $url = $imageUploaderService->uploadAndResizeImageToS3($file);
                $agency->setLogo($url);
            }

            $doctrine->getManager()->flush();

            $this->addFlash('success', 'L\'agence a été modifié avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('agent_work_space/agency/edit_agency.html.twig', [
            'agency' => $agency,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/agency/edit/status/agent/{id}/for/agency', name: 'app_agency_edit_status_agent_for_agency', methods: ['GET', 'POST'])]
    public function editStatusAgent(ManagerRegistry $doctrine, Request $request, AgencyAgent $agencyAgent, AgencyAgentRepository $agencyAgentRepository): Response
    {
        $form = $this->createForm(AgencyAgentType::class, $agencyAgent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $doctrine->getManager()->flush();
            $agenciesForAgent = $agencyAgentRepository->findBy(['agent' => $agencyAgent->getAgent()]);
            if ($agencyAgent->isStatus()){
                $agencyAgent->getAgent()->setAgency($agencyAgent->getAgency());
                $doctrine->getManager()->flush();

                foreach ($agenciesForAgent as $agencyForAgent){
                    if ($agencyForAgent->isStatus() && $agencyForAgent->getId() != $agencyAgent->getId()){
                        $agencyForAgent->setStatus(false);
                        $doctrine->getManager()->flush();
                    }
                }
            }

            $this->addFlash('success', 'Le statut de l\'agent a été modifié avec succès.');

            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('agent_work_space/agency/changeStatus.html.twig', [
            'agencyAgent' => $agencyAgent,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/agency/send/request/for/joining/agency', name: 'app_agency_send_request_for_joining', methods: ['GET', 'POST'])]
    public function sendRequest(CSRFProtectionService $csrf, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($this->getUser());

        return $this->render('agent_work_space/agency/addAgency/add_agency.html.twig', [
            'user' => $user,
            'token' => $csrf->getCSRFTokenForForm()
        ]);
    }
    #[Route('/invoice/send/link/{id}/response', name: 'app_agency_save_send_request_for_joining', methods: ['GET', 'POST'])]
    public function responseSendLink($id, ManagerRegistry $doctrine, CSRFProtectionService $csrf, Request $request, UserRepository $userRepository, AgencyRepository $agencyRepository): Response
    {
        $error = false;
        if ($csrf->validateCSRFToken($request)) {

            $em = $doctrine->getManager();
            $user = $userRepository->find($id);

            $agency = $agencyRepository->findOneBy(['code' => 'AGC-'.$request->request->get('code-'.$id)]);
            if ($agency == null){
                $error = true;
                $this->addFlash('warning', 'Code agent invalide');
            }else{
                $agencyAgent = new AgencyAgent();
                $agencyAgent->setAgency($agency);
                $agencyAgent->setAgent($user);
                $agencyAgent->setStatus(false);
                $agencyAgent->setOwner(false);

                $em->persist($agencyAgent);
                $em->flush();
                $this->addFlash('success', 'La demande a été envoyé avec succès.');

            }
        }
        return $this->render('feedback.html.twig', [
            'error' => $error,
        ]);
    }
}
