<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/contact')]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    #[Route('', name: 'contact_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $nom     = trim((string) ($data['nom']     ?? ''));
        $email   = trim((string) ($data['email']   ?? ''));
        $sujet   = trim((string) ($data['sujet']   ?? 'Sans sujet'));
        $message = trim((string) ($data['message'] ?? ''));

        if ($nom === '' || $email === '' || $message === '') {
            return $this->json(['message' => 'Champs obligatoires manquants.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse email invalide.'], 400);
        }

        $emailMessage = (new Email())
            ->from($email)
            ->to('contact@gem-link.org')
            ->replyTo($email)
            ->subject("[GemLink Contact] {$sujet}")
            ->text("Nom : {$nom}\nEmail : {$email}\n\n{$message}");

        $this->mailer->send($emailMessage);

        return $this->json(['message' => 'Message envoyé avec succès.'], 201);
    }
}