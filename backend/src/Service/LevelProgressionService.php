<?php

namespace App\Service;

use App\Entity\Niveau;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NiveauRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Computes level data from the database thresholds and handles level-up side effects. */
class LevelProgressionService
{
    public function __construct(
        private readonly NiveauRepository $levels,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array{level:int,name:string,minPoints:int,nextLevel:?int,nextLevelName:?string,nextLevelMinPoints:?int,progressPercent:int} */
    public function calculate(int $points): array
    {
        $points = max(0, $points);
        $levels = $this->levels->findAllOrdered();
        if ($levels === []) {
            return ['level' => 1, 'name' => 'Novice', 'minPoints' => 0, 'nextLevel' => null, 'nextLevelName' => null, 'nextLevelMinPoints' => null, 'progressPercent' => 100];
        }

        $currentIndex = 0;
        foreach ($levels as $index => $level) {
            if ($level->getMinPoints() > $points) break;
            $currentIndex = $index;
        }
        $current = $levels[$currentIndex];
        $next = $levels[$currentIndex + 1] ?? null;
        $percentage = $next === null ? 100 : (int) floor((($points - $current->getMinPoints()) / ($next->getMinPoints() - $current->getMinPoints())) * 100);

        return [
            'level' => $current->getNumber(), 'name' => $current->getName(), 'minPoints' => $current->getMinPoints(),
            'nextLevel' => $next?->getNumber(), 'nextLevelName' => $next?->getName(), 'nextLevelMinPoints' => $next?->getMinPoints(),
            'progressPercent' => max(0, min(100, $percentage)),
        ];
    }

    /** Updates the denormalized user level and creates the in-app notification/badge on promotion. */
    public function synchronize(User $user, ?int $previousPoints = null): void
    {
        $progression = $this->calculate($user->getPoints());
        // When points are awarded, compare against the preceding balance rather
        // than a possibly stale denormalized value after an Admin edits thresholds.
        $oldLevel = $previousPoints === null ? $user->getLevel() : $this->calculate($previousPoints)['level'];
        $newLevel = $progression['level'];
        $user->setLevel($newLevel);

        if ($newLevel <= $oldLevel) return;

        $notification = new Notification($user, Notification::TYPE_LEVEL_UP);
        $notification->setTarget($user->getId(), Notification::TARGET_TYPE_LEVEL);
        $this->em->persist($notification);

        foreach ($this->levels->findAllOrdered() as $level) {
            if ($level->getNumber() !== $newLevel || $level->getBadge() === null) continue;
            $user->addBadge($level->getBadge());
            break;
        }
    }
}
