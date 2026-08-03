<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\AvatarUploadService;
use App\Service\Media\MediaUploaderInterface;
use App\Service\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProfileServiceTest extends TestCase
{
    public function testTrustScoreCannotBeModifiedFromProfilePayload(): void
    {
        $service = new ProfileService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(UserRepository::class),
            $this->createStub(TagRepository::class),
            new AvatarUploadService($this->createStub(MediaUploaderInterface::class)),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('calculé automatiquement');

        $service->update(new User(), ['trustScore' => 100], null);
    }
}
