<?php



namespace App\Tests\Controller;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use App\Exception\ValidationPayloadException;
use App\Repository\PublicationRepository;
use App\Repository\ValidationRepository;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ValidationControllerTest extends WebTestCase
{
    public function testAnonymousUserCannotSubmitValidation(): void
    {
        $client = static::createClient();
        $publication = $this->makePublication();

        $this->mockPublicationRepo($client, $publication);

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/validations',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => Validation::ACTION_CONFIRM]),
        );

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND],
        );
    }

    public function testInvalidPublicationIdReturnsBadRequest(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser(), 'api');

        $client->request(
            'POST',
            '/api/publications/not-a-uuid/validations',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => Validation::ACTION_CONFIRM]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testInvalidActionIsRejected(): void
    {
        $client = static::createClient();
        $publication = $this->makePublication();
        $client->loginUser($this->makeUser(), 'api');
        $this->mockPublicationRepo($client, $publication);

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/validations',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'NOT_A_REAL_ACTION']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCoherentPayloadRejectionIsSurfacedAsUnprocessableEntity(): void
    {
        $client = static::createClient();
        $publication = $this->makePublication();
        $client->loginUser($this->makeUser(), 'api');
        $this->mockPublicationRepo($client, $publication);

        $validationServiceMock = $this->createMock(ValidationService::class);
        $validationServiceMock->method('submitValidation')
            ->willThrowException(new ValidationPayloadException('Un label alternatif est requis pour une correction.'));
        $client->getContainer()->set(ValidationService::class, $validationServiceMock);

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/validations',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => Validation::ACTION_CORRECT]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSuccessfulSubmissionReturnsCreated(): void
    {
        $client = static::createClient();
        $publication = $this->makePublication();
        $validator = $this->makeUser();
        $pierre = new Pierre('Améthyste');

        $client->loginUser($validator, 'api');
        $this->mockPublicationRepo($client, $publication);

        $validation = new Validation($validator, $publication, $pierre, $validator->getTrustScore());

        $validationServiceMock = $this->createMock(ValidationService::class);
        $validationServiceMock->expects($this->once())
            ->method('submitValidation')
            ->with($publication, $validator, Validation::ACTION_CONFIRM, null)
            ->willReturn($validation);
        $client->getContainer()->set(ValidationService::class, $validationServiceMock);

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/validations',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => Validation::ACTION_CONFIRM]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Validation::ACTION_CONFIRM, $data['action']);
        $this->assertSame('Améthyste', $data['pierre']['name']);
    }

    public function testMineReturnsNullWhenUserHasNotValidatedYet(): void
    {
        $client = static::createClient();
        $publication = $this->makePublication();
        $client->loginUser($this->makeUser(), 'api');
        $this->mockPublicationRepo($client, $publication);

        $validationRepoMock = $this->createMock(ValidationRepository::class);
        $validationRepoMock->method('findOneByPublicationAndUser')->willReturn(null);
        $client->getContainer()->set(ValidationRepository::class, $validationRepoMock);

        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122() . '/validations/mine');

        $this->assertResponseIsSuccessful();
        $this->assertNull(json_decode($client->getResponse()->getContent(), true));
    }

    private function mockPublicationRepo($client, Publication $publication): void
    {
        $repoMock = $this->createMock(PublicationRepository::class);
        $repoMock->method('findOneActiveById')->willReturn($publication);
        $client->getContainer()->set(PublicationRepository::class, $repoMock);
    }

    private function makePublication(): Publication
    {
        return new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');
        $user->setTrustScore(50);

        return $user;
    }
}
