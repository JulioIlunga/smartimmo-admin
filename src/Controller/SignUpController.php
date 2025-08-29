<?php

namespace App\Controller;

use App\Entity\AccountConfirmation;
use App\Entity\Role;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ProvinceRepository;
use App\Service\CodeService;
use App\Service\SmsService;
use App\Service\WhatsappService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class SignUpController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/sign/up', name: 'app_sign_up')]
    public function index(ManagerRegistry $doctrine, Request $request, UserPasswordHasherInterface $passwordHasher, SmsService $smsService, CodeService $codeService, WhatsappService $whatsappService, ProvinceRepository $provinceRepository): Response
    {
        $em = $doctrine->getManager();

        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $userType = (int) $form->get('userType')->getData();

            if ($userType === 1) {
                $role = $em->getRepository(Role::class)->findOneBy(['id' => 1]); // Client
                $user->setAgent(false);
            } elseif ($userType === 2) {
                $role = $em->getRepository(Role::class)->findOneBy(['id' => 2]); // Agent immobilier
                $user->setAgent(true);

                //For covered city
                $cityRepo = $provinceRepository->findOneBy(['id' => 1]);
                $user->addCoveredCity($cityRepo);

            } else {
                throw new \InvalidArgumentException('Type d’utilisateur invalide.');
            }

            $user->setRole($role);
            $user->setCode(uniqid());
            $user->setAccountConfirmed(false);
            $user->setActive(false);
            $user->setUserType(1);
            $user->setSmartimmoAdministrator(false);
            $user->setTermsAndCondition(true);

            $encodedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            );
            $user->setPassword($encodedPassword);

            /* SEND THE TOKEN TO THE USER FOR ACCOUNT CONFIRMATION */
            $token = $codeService->ConfirmationToken(5);
            $accountConfirmation = new AccountConfirmation();
            $accountConfirmation->setUser($user);
            $accountConfirmation->setToken($token);
            $accountConfirmation->setConfirmed(false);

            $message = "Votre token SmartImmo: ".$token;
            if ($user->getPhonecode() != '+243'){
                $phone = substr(($user->getPhonecode(). $user->getPhone()), 1);
                $whatsappService->sendWhatsappMessage($phone, $token);
            }else{
                $smsService->smsService('0'.$user->getPhone(), $message);
            }

            $em->persist($user);
            $em->persist($accountConfirmation);
            $em->flush();

            return $this->redirectToRoute('app_sign_up_account_confirmation', ['phone' => $user->getPhone()]);
        }

        return $this->render('sign_up/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/sign/up/account/confirmation/{phone}', name: 'app_sign_up_account_confirmation')]
    public function accountFormConfirmation(User $user): Response
    {
        return $this->render('sign_up/account_confirmation.html.twig', [
            'user' => $user,
            'error' => false
        ]);
    }

    #[Route('/sign/up/account/token/confirmation', name: 'app_sign_up_account_token_confirmation', methods: ['POST'])]
    public function accountTokenConfirmation(ManagerRegistry $doctrine, Request $request): Response
    {
        $em = $doctrine->getManager();
        $error = true;

        $user = $em->getRepository(User::class)->findOneBy(['phone' => $request->request->get('user')]);
        $accountConfirmation = $em->getRepository(AccountConfirmation::class)->findOneBy(['user' => $user]);


        if (strcmp($request->request->get('token-number'), $accountConfirmation->getToken()) === 0){

            $user->setAccountConfirmed(true);
            $user->setActive(true);
            $accountConfirmation->setConfirmed(true);
            $em->flush();

            $error = false;
        }

        return $this->render('sign_up/_form_account_confirmation.html.twig', [
            'user' => $user,
            'error' => $error
        ]);
    }
}
