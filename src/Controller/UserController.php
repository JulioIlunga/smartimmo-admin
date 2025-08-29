<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AwsS3Service;
use App\Service\ImageUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/user/edit/information', name: 'app_user_edit_information', methods: ['POST'])]
    public function index(Request $request, UserRepository $userRepository, ManagerRegistry $doctrine, ImageUploaderService $imageUploaderService): Response
    {

        $user = $userRepository->find($this->getUser());
        $user->setSalutation($request->request->get('salutation'));
        $user->setFirstname($request->request->get('firstname'));
        $user->setName($request->request->get('name'));
        $user->setEmail($request->request->get('email'));
        $user->setSex($request->request->get('sex'));
        $user->setAddress($request->request->get('address'));
        $user->setAgentPhone($request->request->get('agentPhone'));
        $user->setAgentPhoneCode($request->request->get('agentPhoneCode'));
        $user->setFbckLink($request->request->get('fbk'));
        $user->setInstaGLink($request->request->get('instagram'));
        $user->setLinkedInLink($request->request->get('linkedIn'));

        $file = $request->files->get('picture');
        if ($file){
            $imageUrl = $imageUploaderService->uploadAndResizeImageToS3($file);
            $user->setPicture($imageUrl);
        }


        $this->addFlash('success', 'Mise à jour fait avec succès.');


        $em = $doctrine->getManager();
        $em->flush();

        return $this->redirectToRoute('app_account', ['show' => 'profil']);
    }

    #[Route('/admin/user/{id<\d+>}/toggle-top', name: 'admin_user_toggle_top', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleTopAgent(User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Toggle
        $user->setTopAgent(!$user->isTopAgent());
        $em->flush();

        return $this->json([
            'ok'       => true,
            'topAgent' => $user->isTopAgent(),
        ]);
    }
}
