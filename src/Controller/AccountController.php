<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FavorisRepository;
use App\Repository\MessengerRepository;
use App\Repository\ReservationRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\CSRFProtectionService;
use App\Service\ImageUploaderService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    #[Cache(maxage: 3600, public: true)]
    public function index(UserRepository $userRepository, RequestStack $requestStack, Request $request, FavorisRepository $favorisRepository, ReservationRepository $reservationRepository, CSRFProtectionService $csrf): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $user = $userRepository->findOneBy(['id' => $this->getUser()]);

        $favoris = $favorisRepository->findBy(['user' => $user], ['id' => 'DESC']);

        if($request->get('show') != null){
            $requestStack->getSession()->set('account-overview', $request->get('show'));
        }
        $show = $requestStack->getSession()->get('account-overview', 'favoris');

        $today = date("D d.m.Y");

        $reservations = $reservationRepository->findByUser($user, true);

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'reservations' => $reservations,
            'today' => $today,
            'favoris' => $favoris,
            'show' => $show,
            'token' => $csrf->getCSRFTokenForForm()

        ]);
    }

    /**
     * Triggered by the buttons to switch reservation view table/yearly.
     */
    #[Route('/account/view/{show}', name: 'account.toggle.view', methods: ['GET'])]
    public function indexActionToggle(RequestStack $requestStack, string $show): Response
    {
        if ('favoris' === $show){
            $requestStack->getSession()->set('account-overview', 'favoris');
        }elseif ('profil' === $show){
            $requestStack->getSession()->set('account-overview', 'profil');
        }elseif ('conversation' === $show){
            $requestStack->getSession()->set('account-overview', 'messenger');
            return $this->redirectToRoute('app_messenger');
        }elseif ('reservations' === $show){
            $requestStack->getSession()->set('account-overview', 'reservations');
        }

        return $this->forward('App\Controller\AccountController::index');
    }

    #[Route('/account/deactivate', name: 'account_deactivate', methods: ['POST'])]
    public function deactivate(Request $request, EntityManagerInterface $em, CSRFProtectionService $csrf
    ): Response {
        // CSRF
        if ($csrf->validateCSRFToken($request)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_account', ['show' => 'profil']);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('danger', 'Utilisateur introuvable ou non connecté.');
            return $this->redirectToRoute('app_login');
        }

        // --- Mark user as deactivated (adapt these lines to your User entity) ---
        // Recommended: have boolean isActive + nullable deactivatedAt.
        if (method_exists($user, 'setIsActive')) {
            $user->setIsActive(false);
        }

        if (method_exists($user, 'setStatus')) {
            // If you store a status string.
            $user->setStatus(false);
        }
        if (method_exists($user, 'setDeactivatedAt')) {
            $user->setDeactivatedAt(new DateTime());
        }

        $em->flush();

        $this->addFlash('success', 'Votre compte a été désactivé avec succès.');
        return $this->redirectToRoute('app_logout');
    }

    #[Route('/account/photo', name: 'account_upload_photo', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, ImageUploaderService $imageUploaderService, UserRepository $userRepository): Response
    {

        /** @var User|null $user */
        $user = $userRepository->find($this->getUser());
        if (!$user instanceof User) {
            $this->addFlash('danger', 'Utilisateur non connecté.');
            return $this->redirectToRoute('app_login');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('avatar');
        if (!$file) {
            $this->addFlash('warning', 'Aucun fichier reçu.');
            return $this->redirectToRoute('app_account', ['show' => 'profil']);
        }

        // Validate mime & size
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $mime = (string) $file->getMimeType();
        if (!isset($allowed[$mime])) {
            $this->addFlash('danger', 'Format non supporté. Utilisez JPG/PNG/WebP.');
            return $this->redirectToRoute('app_account', ['show' => 'profil']);
        }

        $imageUrl = $imageUploaderService->uploadAndResizeImageToS3($file);
        $user->setPicture($imageUrl);

        $em->flush();

        $this->addFlash('success', 'Photo mise à jour avec succès.');
        return $this->redirectToRoute('app_account', ['show' => 'profil']);
    }

    #[Route('/user/switch/{id}', name: 'app_account_switch')]
    public function switchToAgent(UserRepository $userRepository, EntityManagerInterface $em, RoleRepository $roleRepository, int $id): RedirectResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        // Vérifie ses rôles actuels
        $currentRoles = $user->getRoles();

        if (in_array('ROLE_AGENT', $currentRoles)) {
            // repasser en client
            $role = $roleRepository->findOneBy(['name' => 'CUSTOMER']);
        } else {
            // passer en agent
            $role = $roleRepository->findOneBy(['name' => 'AGENT']);
        }

        if ($role === null) {
            throw new \Exception("Le rôle demandé n'existe pas en base.");
        }

        $user->setRole($role);
        $user->setAgent(true);

        $em->persist($user);
        $em->flush();
        $this->addFlash('success', "Le rôle de l'utilisateur a bien changé. veuillez remplir votre numéro Agent et vos différents profils");
        return $this->redirectToRoute('app_account'); // adapte la redirection
    }
}
