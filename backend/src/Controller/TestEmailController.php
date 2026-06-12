<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestEmailController extends AbstractController
{
    #[Route('/test/email', name: 'app_test_email', methods: ['GET'])]
    public function testEmail(EmailService $emailService): Response
    {
        $emailService->sendEmail(
            to: 'ton_email@exemple.com',
            subject: 'Test d\'email depuis Railway',
            template: 'emails/test.html.twig',
            templateData: ['message' => 'Ceci est un test !']
        );

        return $this->json(['message' => 'Email envoyé ! Vérifie ta boîte de réception.']);
    }
}
