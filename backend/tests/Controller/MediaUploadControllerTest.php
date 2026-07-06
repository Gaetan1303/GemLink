<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Service\Media\MediaUploadService;
use App\Service\Media\UploadedMedia;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * US 2.1 — MediaUploadController est le seul point d'entrée HTTP pour
 * l'upload (SRP), découplé de la création du post.
 */
final class MediaUploadControllerTest extends WebTestCase
{
    public function testUploadRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/media');

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]
        );
    }

    public function testUploadSuccessReturns201WithMediaReference(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();

        $mediaUploadServiceMock = $this->createMock(MediaUploadService::class);
        $mediaUploadServiceMock->expects($this->once())
            ->method('upload')
            ->willReturn(new UploadedMedia(
                'https://media.gem-link.org/publications/2026/07/x.jpg',
                Publication::MEDIA_TYPE_IMAGE,
                'image/jpeg',
                2048,
            ));

        $client->getContainer()->set(MediaUploadService::class, $mediaUploadServiceMock);
        $client->loginUser($user, 'api');

        $tmpFile = tempnam(sys_get_temp_dir(), 'gemlink_upload_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0fake-jpeg-content");
        $uploadedFile = new UploadedFile($tmpFile, 'pierre.jpg', 'image/jpeg', null, true);

        $client->request('POST', '/api/media', [], ['file' => $uploadedFile]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('https://media.gem-link.org/publications/2026/07/x.jpg', $data['mediaUrl']);
        $this->assertSame(Publication::MEDIA_TYPE_IMAGE, $data['mediaType']);

        @unlink($tmpFile);
    }

    public function testUploadWithInvalidMediaReturns422(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();

        $mediaUploadServiceMock = $this->createMock(MediaUploadService::class);
        $mediaUploadServiceMock->method('upload')
            ->willThrowException(new InvalidMediaException('Type de fichier non supporté.'));

        $client->getContainer()->set(MediaUploadService::class, $mediaUploadServiceMock);
        $client->loginUser($user, 'api');

        $client->request('POST', '/api/media');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');

        return $user;
    }
}
