<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\PublicationLike;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\PublicationLikeRepository;
use Doctrine\ORM\EntityManagerInterface;

/** US 2.3 — toggle atomique au niveau transactionnel et notification dédupliquée. */
class LikeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PublicationLikeRepository $likes,
        private readonly NotificationRepository $notifications,
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

        $this->em->persist(new PublicationLike($publication, $user));
        $this->notifyPostAuthorOnce($user, $publication);
        $this->em->flush();

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
        $this->em->persist($notification);
    }
}
