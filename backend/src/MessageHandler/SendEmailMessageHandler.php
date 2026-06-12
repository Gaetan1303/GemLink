<?php
// src/MessageHandler/SendEmailMessageHandler.php
namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
class SendEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $fromEmail,
        private string $fromName
    ) {
        $this->fromEmail = $_ENV['MAILER_FROM_EMAIL'] ?? $fromEmail;
        $this->fromName = $_ENV['MAILER_FROM_NAME'] ?? $fromName;
    }

    public function __invoke(SendEmailMessage $message): void
    {
        // 1. Rendre le template Twig
        $html = $this->twig->render($message->getTemplate(), $message->getTemplateData());

        // 2. Créer l'email
        $email = (new Email())
            ->from(new \Symfony\Component\Mime\Address($this->fromEmail, $this->fromName))
            ->to($message->getTo())
            ->subject($message->getSubject())
            ->html($html)
            ->text(strip_tags($html)); // Version texte pour les clients qui ne supportent pas le HTML

        if ($message->getReplyTo()) {
            $email->replyTo($message->getReplyTo());
        }

        // 3. Envoyer l'email
        $this->mailer->send($email);
    }
}