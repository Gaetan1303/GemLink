<?php



namespace App\Tests\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Vitrine;
use App\Entity\VitrinePublication;
use App\Exception\VitrineAccessDeniedException;
use App\Exception\VitrineEmptyException;
use App\Exception\VitrineValidationException;
use App\Repository\PublicationRepository;
use App\Repository\VitrineRepository;
use App\Service\VitrineService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.1 — Création et gestion d'une Vitrine.
 * VitrineService/VitrineRepository/PublicationRepository sont mockés dans le
 * container pour isoler le contrôleur (même pattern que PublicationControllerTest).
 */
final class VitrineControllerTest extends WebTestCase
{
    // ── Liste (authentifiée) ───────────────────────────────────

    public function testIndexRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/vitrines');

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]
        );
    }

    public function testIndexReturnsOwnVitrines(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Mes Améthystes', 'mes-amethystes');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->expects($this->once())
            ->method('findByUser')
            ->with($owner)
            ->willReturn([$vitrine]);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->loginUser($owner, 'api');

        $client->request('GET', '/api/vitrines');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertSame('mes-amethystes', $data['items'][0]['slug']);
    }

    // ── Détail ──────────────────────────────────────────────────

    public function testShowUnknownVitrineReturns404(): void
    {
        $client = static::createClient();

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn(null);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);

        $client->request('GET', '/api/vitrines/' . Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowDraftVitrineByNonOwnerReturns404(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Brouillon', 'brouillon');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->loginUser($stranger, 'api');

        $client->request('GET', '/api/vitrines/' . $vitrine->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowPublishedVitrineIsPublic(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Ma Collection', 'ma-collection');
        $publication = new Publication($owner, 'https://media.gem-link.org/x.jpg');
        $vitrine->addItem(new VitrinePublication($vitrine, $publication, 0));
        $vitrine->publish();

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->expects($this->once())->method('recordView');

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);

        // Pas de loginUser() : accès visiteur anonyme.
        $client->request('GET', '/api/vitrines/' . $vitrine->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Vitrine::STATUS_PUBLISHED, $data['status']);
        $this->assertCount(1, $data['items']);
    }

    // ── CA-1 : création ─────────────────────────────────────────

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/vitrines', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['title' => 'Test']));

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]
        );
    }

    public function testCreateSuccessReturns201(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Mes Quartz', 'mes-quartz');

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->expects($this->once())
            ->method('createVitrine')
            ->willReturn($vitrine);

        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request(
            'POST',
            '/api/vitrines',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'Mes Quartz', 'description' => 'Belle collection'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('mes-quartz', $data['slug']);
    }

    public function testCreateWithBlankTitleReturns422(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->method('createVitrine')
            ->willThrowException(new VitrineValidationException('Le titre est obligatoire.'));

        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request(
            'POST',
            '/api/vitrines',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => ''])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Mise à jour / suppression ───────────────────────────────

    public function testUpdateByNonOwnerReturns403(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->method('updateVitrine')
            ->willThrowException(new VitrineAccessDeniedException('Non autorisé.'));

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($stranger, 'api');

        $client->request(
            'PATCH',
            '/api/vitrines/' . $vitrine->getId()->toRfc4122(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'Nouveau titre'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteByOwnerReturns204(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->expects($this->once())->method('deleteVitrine');

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request('DELETE', '/api/vitrines/' . $vitrine->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    // ── CA-2 : items ─────────────────────────────────────────────

    public function testAddItemSuccessReturns201(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');
        $publication = new Publication($owner, 'https://media.gem-link.org/x.jpg');
        $item = new VitrinePublication($vitrine, $publication, 0);

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->expects($this->once())->method('addItem')->willReturn($item);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request(
            'POST',
            '/api/vitrines/' . $vitrine->getId()->toRfc4122() . '/items',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['publicationId' => $publication->getId()->toRfc4122()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testAddItemUnknownPublicationReturns404(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->loginUser($owner, 'api');

        $client->request(
            'POST',
            '/api/vitrines/' . $vitrine->getId()->toRfc4122() . '/items',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['publicationId' => Uuid::v7()->toRfc4122()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── CA-3 : réordonnancement ────────────────────────────────

    public function testReorderWithEmptyArrayReturns422(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->loginUser($owner, 'api');

        $client->request(
            'PATCH',
            '/api/vitrines/' . $vitrine->getId()->toRfc4122() . '/items/reorder',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['orderedPublicationIds' => []])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── CA-4 : publication ─────────────────────────────────────

    public function testPublishEmptyVitrineReturns422(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->method('publish')
            ->willThrowException(new VitrineEmptyException());

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request('POST', '/api/vitrines/' . $vitrine->getId()->toRfc4122() . '/publish');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('vide', $data['message']);
    }

    public function testPublishSuccessReturns200(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser();
        $vitrine = new Vitrine($owner, 'Titre', 'titre');
        $publication = new Publication($owner, 'https://media.gem-link.org/x.jpg');
        $vitrine->addItem(new VitrinePublication($vitrine, $publication, 0));

        $vitrineRepoMock = $this->createMock(VitrineRepository::class);
        $vitrineRepoMock->method('find')->willReturn($vitrine);

        $vitrineServiceMock = $this->createMock(VitrineService::class);
        $vitrineServiceMock->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function () use ($vitrine): void {
                $vitrine->publish();
            });

        $client->getContainer()->set(VitrineRepository::class, $vitrineRepoMock);
        $client->getContainer()->set(VitrineService::class, $vitrineServiceMock);
        $client->loginUser($owner, 'api');

        $client->request('POST', '/api/vitrines/' . $vitrine->getId()->toRfc4122() . '/publish');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Vitrine::STATUS_PUBLISHED, $data['status']);
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