<?php

namespace App\MessageHandler;

use App\Message\AwardPointsMessage;
use App\Repository\UserRepository;
use App\Service\PointsService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class AwardPointsMessageHandler
{
    public function __construct(private readonly UserRepository $users, private readonly PointsService $points) {}

    public function __invoke(AwardPointsMessage $message): void
    {
        $user = $this->users->find(Uuid::fromString($message->getUserId()));

        if ($user !== null) {
            $this->points->award($user, $message->getAction(), $message->getSourceId());
        }
    }
}
