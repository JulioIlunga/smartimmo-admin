<?php

namespace App\Controller;

use App\Entity\Images;
use App\Entity\Message;
use App\Entity\Messenger;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use App\Repository\MessengerRepository;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account')]
class MessengerController extends AbstractController
{
    #[Route('/messenger', name: 'app_messenger')]
    public function index(UserRepository  $userRepository, RequestStack $requestStack, MessengerRepository $messengerRepository): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $messengersReceived = $messengerRepository->findMessengersReceived($user);
        $messengersSent = $messengerRepository->findMessengersSent($user);
        $show = $requestStack->getSession()->get('messenger-overview', 'received');
        $showNav = $requestStack->getSession()->get('account-overview', 'messenger');
        $requestStack->getSession()->set('account-overview', 'messenger');


        return $this->render('messenger/index.html.twig', [
            'user' => $user,
            'show' => $show,
            'showNav' => $showNav,
            'messengersReceived' => $messengersReceived,
            'messengersSent' => $messengersSent,
        ]);
    }

    /**
     * Triggered by the buttons to switch reservation view table/yearly.
     */
    #[Route('/messenger/view/{show}', name: 'messenger.toggle.view', methods: ['GET'])]
    public function indexActionToggle(RequestStack $requestStack, string $show): Response
    {
        $requestStack->getSession()->remove('propertyUuid');

        if ('received' === $show){
            $requestStack->getSession()->set('messenger-overview', 'received');
        }elseif ('sent' === $show){
            $requestStack->getSession()->set('messenger-overview', 'sent');
        }

        return $this->forward('App\Controller\MessengerController::index');
    }

    #[Route('/messenger/chat/{propertyUuid}/{messengerId?}', name: 'app_messenger_chat')]
    public function chat($propertyUuid, $messengerId, PropertyRepository $propertyRepository, UserRepository  $userRepository, MessageRepository $messageRepository, RequestStack $requestStack, ManagerRegistry $doctrine, MessengerRepository $messengerRepository, Request $request,): Response
    {
        $em = $doctrine->getManager();
        $user = $userRepository->findOneBy(['id' => $this->getUser()]);
        $property = $propertyRepository->findOneBy(['uuidProperty'=>$propertyUuid]);

        if($messengerId == null){
            $messenger = $messengerRepository->findOneBy(['property'=>$property, 'agent' => $property->getUser(), 'client' => $user]);
            if ($messenger == null){
                $messenger = new Messenger();
                $messenger->setCode(uniqid());
                $messenger->setProperty($property);
                $messenger->setAgent($property->getUser());
                $messenger->setClient($user);

                $em->persist($messenger);
                $em->flush();
            }
        }else{
            $messenger = $messengerRepository->findOneBy(['code' => $messengerId]);
        }

        $messages = $messageRepository->findBy(['messenger' => $messenger], ['id' => 'DESC']);
        $show = $requestStack->getSession()->get('messenger-overview', 'received');

        $requestStack->getSession()->set('account-overview', 'messenger');
        $showNav = $requestStack->getSession()->get('account-overview', 'messenger');

        return $this->render('messenger/message.html.twig', [
            'user' => $user,
            'messenger' => $messenger,
            'messages' => $messages,
            'show' => $show,
            'showNav' => $showNav,
        ]);
    }

    #[Route('/messenger/chat/for/save/between/agent/and/client', name: 'app_messenger_chat_save', methods: ['POST'])]
    public function saveChat(MessengerRepository $messengerRepository, MessageRepository $messageRepository, Request $request, ManagerRegistry $doctrine, UserRepository $userRepository, PropertyRepository $propertyRepository): Response
    {

        $em = $doctrine->getManager();
        $user = $userRepository->findOneBy(['id' => $request->request->get('whoSendId')]);
        $messenger = $messengerRepository->findOneBy(['id' => $request->request->get('messengerId')]);

        $message = new Message();
        $message->setMessenger($messenger);
        $message->setMessage($request->request->get('message'));
        $message->setWhoSent($user);

        $em->persist($message);
        $em->flush();

        $messages = $messageRepository->findBy(['messenger' => $messenger], ['id' => 'DESC']);

        return $this->render('messenger/message_stream.html.twig', [
            'messenger' => $messenger,
            'messages' => $messages,
            'user' => $user,
        ]);
    }
}
