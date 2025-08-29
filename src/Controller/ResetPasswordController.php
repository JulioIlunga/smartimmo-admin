<?php

namespace App\Controller;

use App\Entity\AccountConfirmation;
use App\Entity\AccountResetPassword;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Repository\UserRepository;
use App\Service\CodeService;
use App\Service\SmsService;
use App\Service\WhatsappService;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use phpDocumentor\Reflection\Location;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset/password', name: 'app_reset_password')]
    public function index(Request $request, UserRepository $userRepository, ManagerRegistry $doctrine, SmsService $smsService, CodeService $codeService, WhatsappService $whatsappService): Response
    {
        $em = $doctrine->getManager();
        if ($request->isMethod('POST')) {
            $user = $userRepository->findOneBy(['phone' => $request->request->get('phone-number')]);
            if ($user != null){

                $this->extracted($codeService, $user, $em, $smsService, $whatsappService);

                return  $this->redirectToRoute('app_reset_password_token_confirmation', ['phone' => $user->getPhone()]);

            }else{

                $this->addFlash('error', ('Impossible de retrouvé ce numéro de téléphone'));
                return $this->redirectToRoute('app_reset_password');
            }
        }

        return $this->render('reset_password/index.html.twig', [
            'error' => false
        ]);
    }

    #[Route('/reset/password/token/confirmation/{phone}/{error?}', name: 'app_reset_password_token_confirmation')]
    public function sendToken($phone, $error, UserRepository $userRepository): Response
    {

        $user = $userRepository->findOneBy(['phone' => $phone]);
        if (null == $user) {
            $this->addFlash('error', ('Impossible de retrouvé ce numéro de téléphone'));

            return $this->redirectToRoute('app_reset_password');
        }
        $error_ =  false;
        if ($error != null){
            $error_ =  true;
        }

        return $this->render('reset_password/tokenConfirmation/token_confirmation.html.twig', [
            'user' => $user,
            'error' => $error_
        ]);
    }

    #[Route('/reset/password/account/token/confirmation', name: 'app_reset_password_account_token_confirmation', methods: ['POST'])]
    public function accountTokenConfirmation(ManagerRegistry $doctrine, Request $request): Response
    {
        $em = $doctrine->getManager();

        $user = $em->getRepository(User::class)->findOneBy(['id' => $request->request->get('user')]);

        if (null == $user) {
            $this->addFlash('error', ('Impossible de retrouvé ce numéro de téléphone'));

            return $this->redirectToRoute('app_reset_password');
        }

        $resetAccountConfirmation = $em->getRepository(AccountResetPassword::class)->findOneBy(['user' => $user, 'token' => $request->request->get('token-number')]);

        if ($resetAccountConfirmation){
            $resetAccountConfirmation->setConfirmed(true);
            $em->flush();

            return $this->redirectToRoute('app_reset_password_set_new_password', ['code' => $resetAccountConfirmation->getCode(), 'phone' => $user->getPhone()]);
        }else{
            $error = true;
            return $this->redirectToRoute('app_reset_password_token_confirmation', ['phone' => $user->getPhone(), 'error' => $error]);

        }

    }


    #[Route('/reset/password/set/new/password/{code}/{phone}', name: 'app_reset_password_set_new_password')]
    public function tokenConfirmation(Request $request, ManagerRegistry $doctrine, $code, $phone, UserPasswordHasherInterface $passwordHasher): Response
    {

        $em = $doctrine->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        if (null == $user) {
            $this->addFlash('error', ('Impossible de retrouvé ce numéro de téléphone'));
            return $this->redirectToRoute('app_reset_password');
        }

        $resetAccountConfirmation = $em->getRepository(AccountResetPassword::class)->findOneBy(['user' => $user, 'code' => $code, 'confirmed' => true]);
        if (null == $resetAccountConfirmation){
            return $this->redirectToRoute('app_reset_password');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.

            // Encode the plain password, and set it.
            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            );

            $user->setPassword($encodedPassword);
            $accountConfirmation = $em->getRepository(AccountConfirmation::class)->findOneBy(['user' => $user]);
            if (!$accountConfirmation->isConfirmed()){
                $accountConfirmation->setConfirmed(true);
            }
            $user->setAccountConfirmed(true);
            $user->setActive(true);
            $em->flush();

            $this->addFlash('success', ('Votre mot de passe a été réinitialisé avec succès'));
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/newPasswordReset/_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset/password/resend/token/{id}', name: 'app_reset_password_resend_token')]
    public function resendToekn(User $user, ManagerRegistry $doctrine, SmsService $smsService, CodeService $codeService): Response
    {
        $em = $doctrine->getManager();
        if ($user != null){

            $this->extracted($codeService, $user, $em, $smsService);
        }

        return $this->render('reset_password/tokenConfirmation/_form.html.twig', [
            'user' => $user,
            'error' => false
        ]);
    }

    /**
     * @param CodeService $codeService
     * @param User $user
     * @param ObjectManager $em
     * @param SmsService $smsService
     * @param WhatsappService $whatsappService
     * @return void
     * @throws TransportExceptionInterface
     */
    public function extracted(CodeService $codeService, User $user, ObjectManager $em, SmsService $smsService, WhatsappService $whatsappService): void
    {
        $code = $codeService->ConfirmationToken(5);

        $resetPassword = new AccountResetPassword();
        $resetPassword->setCode(uniqid());
        $resetPassword->setUser($user);
        $resetPassword->setToken($code);
        $resetPassword->setConfirmed(false);
        $em->persist($resetPassword);
        $em->flush();

        if ($user->getPhonecode() != '+243'){
            $phone = substr(($user->getPhonecode(). $user->getPhone()), 1);
            $whatsappService->sendWhatsappMessage($phone, $code);
        }else{
            $smsService->smsService('+' . $user->getPhone(), 'Votre code smart-immo : ' . $code);
        }
    }
}
