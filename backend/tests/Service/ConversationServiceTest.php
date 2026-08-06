<?php

namespace App\Tests\Service;

use App\Entity\Conversation;
use App\Entity\User;
use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class ConversationServiceTest extends TestCase
{
    private function user(): User
    {
        return (new User())->setUsername('u' . uniqid())->setEmail(uniqid() . '@test.local')->setPasswordHash('x')->setStatus('ACTIVE');
    }

    public function testCannotOpenConversationWithSelf(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $user = $this->user();
        $this->expectException(\InvalidArgumentException::class);
        (new ConversationService($em))->direct($user, $user);
    }

    public function testExistingDirectConversationIsReturned(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $author = $this->user();
        $other = $this->user();
        $existing = new Conversation(Conversation::DIRECT, $author, null, 'existing');
        $em->method('getRepository')->willReturn($repo);
        $repo->method('findOneBy')->willReturn($existing);
        $em->expects($this->never())->method('persist');

        self::assertSame($existing, (new ConversationService($em))->direct($author, $other));
    }
}
