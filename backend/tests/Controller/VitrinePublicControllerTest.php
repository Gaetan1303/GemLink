<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vitrine;
use App\Repository\VitrineRepository;
use App\Service\VitrineViewCounterService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VitrinePublicControllerTest extends WebTestCase
{
    public function testShowReturns404WhenVitrineDoesNotExist(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('findOnePublishedBySlug')
            ->with('slug-inexistant')
            ->willReturn(null);

        $container->set(VitrineRepository::class, $vitrineRepository);

        $client->request('GET', '/api/public/vitrines/slug-inexistant');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowReturnsPublicDataWithoutAuthentication(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $user = $this->createMock(User::class);

        $vitrine = new Vitrine($user, 'Ma collection d\'améthystes', 'collection-ametystes');
        $vitrine->setDescription('Une sélection de mes plus belles pièces.');
        $vitrine->publish();

        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('findOnePublishedBySlug')
            ->with('collection-ametystes')
            ->willReturn($vitrine);

        $viewCounter = $this->createMock(VitrineViewCounterService::class);
        $viewCounter->expects($this->once())
            ->method('incrementView')
            ->with($vitrine->getId()->toRfc4122());

        $container->set(VitrineRepository::class, $vitrineRepository);
        $container->set(VitrineViewCounterService::class, $viewCounter);

        // Aucun header Authorization : la route doit être accessible sans JWT.
        $client->request('GET', '/api/public/vitrines/collection-ametystes');

        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertSame('collection-ametystes', $data['slug']);
        $this->assertSame('Ma collection d\'améthystes', $data['title']);
        $this->assertSame(0, $data['viewCount']);
        $this->assertSame([], $data['items']);
        $this->assertArrayHasKey('username', $data['creator']);
    }
}