<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\Pierre;
use App\Entity\User;
use App\Repository\BadgeRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Centralises automatic, idempotent badge evaluation for domain events. */
class BadgeAwardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BadgeRepository $badges,
        private readonly PublicationRepository $publications,
        private readonly PublicationPierreRepository $identifications,
        private readonly MineralIdentificationBadgePrototype $mineralPrototype,
    ) {
    }

    public function onStoneIdentified(User $user, Pierre $pierre, bool $isNewMineral): void
    {
        if ($isNewMineral && $this->badges->findMineralIdentificationBadge($pierre) === null) {
            $badge = $this->mineralPrototype->createFor($pierre);
            $this->em->persist($badge);
            $this->award($user, $badge);
        }

        foreach ($this->badges->findAutomaticBadges() as $badge) {
            if ($this->isReached($user, $badge, $pierre)) $this->award($user, $badge);
        }
    }

    public function onPostCreated(User $user): void
    {
        foreach ($this->badges->findAutomaticBadges() as $badge) {
            if ($badge->getConditionType() === Badge::CONDITION_POST_COUNT && $this->publications->count(['user' => $user]) >= $badge->getConditionValue()) $this->award($user, $badge);
        }
    }

    private function isReached(User $user, Badge $badge, Pierre $identifiedPierre): bool
    {
        return match ($badge->getConditionType()) {
            Badge::CONDITION_STONE_IDENTIFICATION_COUNT => $this->identifications->countForUser($user) >= $badge->getConditionValue(),
            Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT => $badge->getPierre() !== null && $badge->getPierre()->getId()->equals($identifiedPierre->getId()) && $this->identifications->countForUserAndPierre($user, $identifiedPierre) >= $badge->getConditionValue(),
            default => false,
        };
    }

    /** A Collection-backed membership check makes duplicate events a silent no-op. */
    private function award(User $user, Badge $badge): void
    {
        if ($user->getBadges()->contains($badge)) return;

        $user->addBadge($badge);
        $notification = new Notification($user, Notification::TYPE_BADGE_AWARDED);
        $notification->setTarget($badge->getId(), Notification::TARGET_TYPE_BADGE);
        $notification->setContent(sprintf('Badge obtenu : %s — %s', $badge->getName(), $badge->getDescription() ?? 'Nouvel accomplissement débloqué.'));
        $this->em->persist($notification);
    }
}
