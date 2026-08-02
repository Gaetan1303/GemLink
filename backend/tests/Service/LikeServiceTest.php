<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\PublicationLike;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\PublicationLikeRepository;
use App\Service\LikeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class LikeServiceTest extends TestCase
{
    public function testFirstToggleAddsLikeAndNotifiesPostAuthor(): void
    {
        [$author, $liker, $post] = $this->postAndLiker();
        $em = $this->createMock(EntityManagerInterface::class);
        $likes = $this->createMock(PublicationLikeRepository::class);
        $notifications = $this->createMock(NotificationRepository::class);
        $likes->method('findOneFor')->with($post, $liker)->willReturn(null);
        $likes->method('countForPublication')->with($post)->willReturn(1);
        $notifications->expects($this->once())->method('hasLikeNotification')->with($author, $liker, $post)->willReturn(false);

        $persisted = [];
        $em->expects($this->exactly(2))->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void { $persisted[] = $entity; });
        $em->expects($this->once())->method('flush');

        $result = (new LikeService($em, $likes, $notifications, $this->bus()))->toggle($liker, $post);

        self::assertSame(['liked' => true, 'likeCount' => 1], $result);
        self::assertInstanceOf(PublicationLike::class, $persisted[0]);
        self::assertInstanceOf(Notification::class, $persisted[1]);
        self::assertSame(Notification::TYPE_NEW_LIKE, $persisted[1]->getType());
        self::assertSame($liker, $persisted[1]->getActor());
    }

    public function testSecondToggleRemovesExistingLike(): void
    {
        [$author, $liker, $post] = $this->postAndLiker();
        $existing = new PublicationLike($post, $liker);
        $em = $this->createMock(EntityManagerInterface::class);
        $likes = $this->createMock(PublicationLikeRepository::class);
        $notifications = $this->createMock(NotificationRepository::class);
        $likes->method('findOneFor')->willReturn($existing);
        $likes->method('countForPublication')->willReturn(0);
        $em->expects($this->once())->method('remove')->with($existing);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        self::assertSame(['liked' => false, 'likeCount' => 0], (new LikeService($em, $likes, $notifications, $this->bus()))->toggle($liker, $post));
    }

    public function testRelikeDoesNotCreateAnotherNotification(): void
    {
        [$author, $liker, $post] = $this->postAndLiker();
        $em = $this->createMock(EntityManagerInterface::class);
        $likes = $this->createMock(PublicationLikeRepository::class);
        $notifications = $this->createMock(NotificationRepository::class);
        $likes->method('findOneFor')->willReturn(null);
        $likes->method('countForPublication')->willReturn(1);
        $notifications->method('hasLikeNotification')->with($author, $liker, $post)->willReturn(true);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(PublicationLike::class));

        (new LikeService($em, $likes, $notifications, $this->bus()))->toggle($liker, $post);
    }

    /** @return array{User, User, Publication} */
    private function postAndLiker(): array
    {
        $author = $this->user();
        $liker = $this->user();
        return [$author, $liker, new Publication($author, 'https://media.gem-link.org/x.jpg')];
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('u' . uniqid())->setEmail(uniqid() . '@example.com')->setPasswordHash('hash')->setRole('USER');
        return $user;
    }

    private function bus(): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        return $bus;
    }
}
