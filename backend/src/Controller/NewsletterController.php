<?php
namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/newsletter')]
class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NewsletterSubscriberRepository $repository,
    ) {
    }

    #[Route('/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse email invalide.'], 400);
        }

        $existing = $this->repository->findByEmail($email);

        if ($existing !== null) {
            if ($existing->getStatus() === 'ACTIVE') {
                return $this->json(['message' => 'Cette adresse est déjà inscrite à la newsletter.'], 409);
            }

            $existing->resubscribe();
            $this->entityManager->flush();

            return $this->json(['message' => 'Inscription confirmée.'], 200);
        }

        $subscriber = (new NewsletterSubscriber())->setEmail($email);

        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        return $this->json(['message' => 'Inscription confirmée.'], 201);
    }

    #[Route('/unsubscribe', name: 'newsletter_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));

        $subscriber = $this->repository->findByEmail($email);

        if ($subscriber === null) {
            return $this->json(['message' => 'Adresse introuvable.'], 404);
        }

        $subscriber->unsubscribe();
        $this->entityManager->flush();

        return $this->json(['message' => 'Désinscription confirmée.'], 200);
    }
}