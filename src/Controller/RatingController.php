<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Repository\AgentRepository;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RatingController extends AbstractController
{
    #[Route('/rating/{id}', name: 'app_rating')]
    // #[IsGranted('IS_AUTHENTICATED')]
    public function index(Request $request,PropertyRepository $propertyRepository,EntityManagerInterface $entityManager,AgentRepository $agentRepository, $id): JsonResponse
    {
        $ratingValue = $request->request->get('rating');
        $comment = $request->request->get('comment','');
        $property = $propertyRepository->findOneBy(['id' => $id]);
        $agent = $property->getUser();

        // dd($agent);
        $user = $this->getUser();

        // Sécurité & validation rapide
        if ($ratingValue < 1 || $ratingValue > 5) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous devez au moins noter 1 étoile avant de d\'envoyer votre avis.'
            ], 400);
        }

        // Création de l'évaluation
        $rating = new Rating();
        $rating
            ->setScore($ratingValue)
            ->setComment($comment)
            ->setProperty($property)
            ->setAgent($agent)
            ->setUser($user);
        $entityManager->persist($rating);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'message' => 'Merci pour votre évaluation.',
        ]);
    }
}
