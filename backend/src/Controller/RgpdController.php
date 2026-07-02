<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rgpd-request')]
class RgpdController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    #[Route('', name: 'rgpd_request', methods: ['POST', 'OPTIONS'])]
    public function request(Request $request): JsonResponse
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->json(null, 204);
        }

        $data    = json_decode($request->getContent(), true);
        $nom     = trim((string) ($data['nom']     ?? ''));
        $email   = trim((string) ($data['email']   ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($nom === '' || $email === '' || $message === '') {
            return $this->json(['message' => 'Champs obligatoires manquants.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse email invalide.'], 400);
        }

        $emailMessage = (new Email())
            ->from(new Address('contact@gem-link.org', 'GemLink'))
            ->to('contact@gem-link.org')
            ->replyTo(new Address($email, $nom))
            ->subject("[GemLink RGPD] Demande de {$nom}")
            ->text(
                "Nouvelle demande RGPD\n\n" .
                "Nom : {$nom}\n" .
                "Email : {$email}\n\n" .
                "Demande :\n{$message}"
            );

        $this->mailer->send($emailMessage);

        return $this->json(['message' => 'Votre demande a bien été envoyée.'], 201);
    }
}