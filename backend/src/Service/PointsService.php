<?php

namespace App\Service;

use App\Entity\PointTransaction;
use App\Entity\User;
use App\Repository\PointTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class PointsService
{
    public const ACTION_POST_CREATED = 'POST_CREATED';
    public const ACTION_LIKE_RECEIVED = 'LIKE_RECEIVED';
    public const ACTION_VALIDATION_SUBMITTED = 'VALIDATION_SUBMITTED';
    public const ACTION_VALIDATION_CONSENSUS_CONFIRMED = 'VALIDATION_CONSENSUS_CONFIRMED';

    private const SETTINGS = [
        self::ACTION_POST_CREATED => 'points.post_created',
        self::ACTION_LIKE_RECEIVED => 'points.like_received',
        self::ACTION_VALIDATION_SUBMITTED => 'points.validation_submitted',
        self::ACTION_VALIDATION_CONSENSUS_CONFIRMED => 'points.validation_consensus_confirmed',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PointTransactionRepository $transactions,
        private readonly AdminSettingsProvider $settings,
    ) {
    }

    public function award(User $user, string $action, string $sourceId): void
    {
        if (!isset(self::SETTINGS[$action])) {
            throw new \InvalidArgumentException('Action de points inconnue.');
        }

        $source = Uuid::fromString($sourceId);
        if ($this->transactions->hasSource($user, $action, $source)) {
            return;
        }

        $amount = $this->settings->getPointsForAction($action);
        if ($amount <= 0) {
            return;
        }

        $this->em->persist(new PointTransaction($user, $action, $amount, $source));
        $user->addPoints($amount);
        $this->em->flush();
    }
}
