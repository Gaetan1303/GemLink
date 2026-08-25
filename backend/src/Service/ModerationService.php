<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\Report;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/** Orchestre une décision de modération et tous ses effets persistants. */
class ModerationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function decide(Report $report, User $moderator, string $decision, ?string $reason = null): void
    {
        if ($report->getStatus() !== 'PENDING') {
            throw new \LogicException('Ce signalement a déjà été traité.');
        }
        if (!in_array($decision, ['ACCEPTED', 'REJECTED'], true)) {
            throw new \InvalidArgumentException('Décision invalide.');
        }

        $publication = $report->getPublication();
        $report->decide($decision);

        if ($decision === 'ACCEPTED') {
            $publication->setDeletedAt(new DateTimeImmutable());

            $notification = new Notification(
                $publication->getUser(),
                Notification::TYPE_POST_REMOVED_BY_MODERATION,
            );
            $notification
                ->setActor($moderator)
                ->setTarget($publication->getId(), Notification::TARGET_TYPE_PUBLICATION)
                ->setContent('Votre publication a été retirée à la suite d’une décision de modération.');
            $this->em->persist($notification);
        } elseif ($publication->getStatus() === Publication::STATUS_AUTO_HIDDEN) {
            $publication->setStatus(Publication::STATUS_PUBLISHED);
        }

        $auditReason = trim((string) $reason);
        if ($auditReason === '') {
            $auditReason = $report->getReasonType();
            if ($report->getDescription() !== null && trim($report->getDescription()) !== '') {
                $auditReason .= ': ' . trim($report->getDescription());
            }
        }

        $this->em->persist(new AuditLog(
            $moderator,
            $decision === 'ACCEPTED' ? AuditLog::ACTION_REPORT_ACCEPTED : AuditLog::ACTION_REPORT_REJECTED,
            AuditLog::TARGET_TYPE_PUBLICATION,
            $publication->getId(),
            $auditReason,
        ));
        $this->em->flush();
    }
}
