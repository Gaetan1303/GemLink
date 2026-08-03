<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\PublicationLike;
use App\Entity\User;
use App\Message\AwardPointsMessage;
use App\Repository\NotificationRepository;
use App\Repository\PublicationLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/** US 2.3 — toggle atomique au niveau transactionnel et notification dédupliquée. */
class LikeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicationLikeRepository $likes,
        private readonly NotificationRepository $notifications,
        private readonly MessageBusInterface $messageBus,
    ) {}

    /** @return array{liked: bool, likeCount: int} */
    public function toggle(User $user, Publication $publication): array
    {
        $existing = $this->likes->findOneFor($publication, $user);

        if ($existing !== null) {
            $this->em->remove($existing);
            $this->em->flush();

            return ['liked' => false, 'likeCount' => $this->likes->countForPublication($publication)];
        }

        $like = new PublicationLike($publication, $user);
        $this->em->persist($like);
        $this->notifyPostAuthorOnce($user, $publication);
        $this->em->flush();

        if (!$publication->getUser()->getId()->equals($user->getId())) {
            $this->messageBus->dispatch(new AwardPointsMessage(
                $publication->getUser()->getId()->toRfc4122(),
                PointsService::ACTION_LIKE_RECEIVED,
                Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_URL), sprintf('like:%s:%s', $user->getId(), $publication->getId()))->toRfc4122(),
            ));
        }

        return ['liked' => true, 'likeCount' => $this->likes->countForPublication($publication)];
    }

    private function notifyPostAuthorOnce(User $liker, Publication $publication): void
    {
        $postAuthor = $publication->getUser();
        if ($postAuthor->getId()->equals($liker->getId()) || $this->notifications->hasLikeNotification($postAuthor, $liker, $publication)) {
            return;
        }

        $notification = new Notification($postAuthor, Notification::TYPE_NEW_LIKE);
        $notification->setTarget($publication->getId(), Notification::TARGET_TYPE_PUBLICATION);
        $notification->setActor($liker);
        $notification->setContent(sprintf('%s aime votre publication.', $liker->getUsername()));
        $this->em->persist($notification);
    }
}
