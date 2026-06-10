<?php
// src/Service/EmailService.php
namespace App\Service;

use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private string $fromEmail,
        private string $fromName
    ) {
        $this->fromEmail = $_ENV['MAILER_FROM_EMAIL'] ?? $fromEmail;
        $this->fromName = $_ENV['MAILER_FROM_NAME'] ?? $fromName;
    }

    // --- Envoi d'un email de validation de compte (US 1.2) ---
    public function sendValidationEmail(\App\Entity\User $user): void
    {
        // 1. Générer un token de validation (à usage unique, 1h)
        $plainToken = bin2hex(random_bytes(32));
        $validationToken = new \App\Entity\EmailValidationToken();
        $validationToken->setUser($user);
        $validationToken->setToken(hash('sha256', $plainToken));
        $validationToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));
        $validationToken->setUsed(false);

        // Sauvegarder le token en base (à faire dans AuthService)
        // $this->em->persist($validationToken);
        // $this->em->flush();

        // 2. Générer l'URL de validation
        $validationUrl = $this->urlGenerator->generate(
            'app_validate_email',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // 3. Envoyer l'email via Messenger
        $this->messageBus->dispatch(new SendEmailMessage(
            to: $user->getEmail(),
            subject: 'Validez votre compte GemLink',
            template: 'emails/validation.html.twig',
            templateData: [
                'user' => $user,
                'validationUrl' => $validationUrl,
                'expiresIn' => '1 heure',
            ],
            replyTo: $this->fromEmail
        ));
    }

    // --- Envoi d'un email de réinitialisation de mot de passe (US 1.6) ---
    public function sendResetPasswordEmail(\App\Entity\User $user, string $plainToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->messageBus->dispatch(new SendEmailMessage(
            to: $user->getEmail(),
            subject: 'Réinitialisez votre mot de passe GemLink',
            template: 'emails/reset_password.html.twig',
            templateData: [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresIn' => '1 heure',
            ],
            replyTo: $this->fromEmail
        ));
    }

    // --- Envoi d'un email générique ---
    public function sendEmail(
        string $to,
        string $subject,
        string $template,
        array $templateData = [],
        ?string $replyTo = null
    ): void {
        $this->messageBus->dispatch(new SendEmailMessage(
            to: $to,
            subject: $subject,
            template: $template,
            templateData: $templateData,
            replyTo: $replyTo ?? $this->fromEmail
        ));
    }

    // --- Méthode pour générer le HTML à partir d'un template Twig ---
    public function renderTemplate(string $template, array $data): string
    {
        return $this->twig->render($template, $data);
    }
}