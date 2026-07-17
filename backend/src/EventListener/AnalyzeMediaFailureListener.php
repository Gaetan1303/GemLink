<?php



namespace App\EventListener;

use App\Entity\Publication;
use App\Message\AnalyzeMediaMessage;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.1/US 3.1 CA-3 : bascule un post en ANALYSIS_FAILED et alerte l'Admin
 * par email, uniquement lorsque Symfony Messenger a épuisé les 3 tentatives
 * de retry (30 s, 2 min, 10 min — voir App\Messenger\AnalyzeMediaRetryStrategy
 * et config/packages/messenger.yaml, transport `ai_analysis`). Une erreur
 * transitoire isolée ne doit jamais déclencher ni le changement de statut ni
 * l'alerte avant la dernière tentative.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class AnalyzeMediaFailureListener
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
        private readonly string $fromName,
        // private readonly string $adminAlertEmail,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof AnalyzeMediaMessage || $event->willRetry()) {
            return;
        }

        $publication = $this->publications->find(Uuid::fromString($message->getPublicationId()));

        if ($publication === null || $publication->isDeleted()) {
            return;
        }

        $this->logger->error('Analyse IA définitivement échouée après épuisement des tentatives de retry.', [
            'publicationId' => $publication->getId()->toRfc4122(),
            'exception' => $event->getThrowable()->getMessage(),
        ]);

        $publication->setStatus(Publication::STATUS_ANALYSIS_FAILED);
        $this->em->flush();

        $this->alertAdmin($publication, $event);
    }

    /**
     * CA-3 : envoi synchrone (pas de nouveau passage par Messenger) — on est
     * déjà dans le traitement d'un échec définitif du worker, mieux vaut ne
     * pas faire dépendre l'alerte d'une file supplémentaire qui pourrait
     * elle-même échouer. Best-effort : une erreur d'envoi est loguée mais
     * n'empêche jamais la bascule de statut, déjà actée au moment de l'appel.
     */
    private function alertAdmin(Publication $publication, WorkerMessageFailedEvent $event): void
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                // ->to(new Address($this->adminAlertEmail, 'GemLink Admin'))
                ->to(new Address('admin@gem-link.org', 'GemLink Admin'))
                ->subject(sprintf('[GemLink] Analyse IA échouée — post %s', $publication->getId()->toRfc4122()))
                ->html($this->buildAlertHtml($publication, $event));

            $this->mailer->send($email);
        } catch (\Throwable $mailException) {
            $this->logger->error('Échec de l\'envoi de l\'alerte admin ANALYSIS_FAILED.', [
                'publicationId' => $publication->getId()->toRfc4122(),
                'exception' => $mailException->getMessage(),
            ]);
        }
    }

    private function buildAlertHtml(Publication $publication, WorkerMessageFailedEvent $event): string
    {
        return sprintf(
            '<p>Le post <strong>%s</strong> (ID : %s) est passé en <strong>ANALYSIS_FAILED</strong> '
            . 'après épuisement des 3 tentatives d\'analyse IA (30 s, 2 min, 10 min).</p>'
            . '<p><strong>Auteur :</strong> %s</p>'
            . '<p><strong>Média :</strong> <a href="%s">%s</a></p>'
            . '<p><strong>Dernière erreur :</strong> %s</p>',
            htmlspecialchars($publication->getTitle() ?? 'Sans titre'),
            $publication->getId()->toRfc4122(),
            htmlspecialchars($publication->getUser()->getUsername()),
            htmlspecialchars($publication->getMediaUrl()),
            htmlspecialchars($publication->getMediaUrl()),
            htmlspecialchars($event->getThrowable()->getMessage()),
        );
    }
}
