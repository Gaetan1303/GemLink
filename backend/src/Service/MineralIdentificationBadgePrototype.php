<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Pierre;

/**
 * Prototype pattern: a mineral badge is cloned from one immutable template,
 * then specialised for the mineral newly introduced by an identification.
 */
final class MineralIdentificationBadgePrototype
{
    private Badge $template;

    public function __construct()
    {
        $this->template = (new Badge('Découvreur de pierre'))
            ->setDescription('Vous avez identifié une nouvelle pierre sur GemLink.')
            ->setCondition(Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT, 1);
    }

    public function createFor(Pierre $pierre): Badge
    {
        $badge = clone $this->template;

        return $badge
            ->setCondition(Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT, 1)
            ->setPierre($pierre)
            ->setDescription(sprintf('Vous avez identifié %s pour la première fois sur GemLink.', $pierre->getName()))
            ->setName(sprintf('Découvreur : %s', $pierre->getName()));
    }
}
