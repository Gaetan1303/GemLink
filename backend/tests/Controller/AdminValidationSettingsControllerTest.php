<?php



namespace App\Tests\Controller;

use App\Entity\ParametreSysteme;
use App\Entity\User;
use App\Repository\ParametreSystemeRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminValidationSettingsControllerTest extends WebTestCase
{
    public function testNonAdminUserIsForbidden(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser('USER'), 'api');

        $client->request('GET', '/api/admin/validation-settings');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanReadDefaultThresholdsWhenNothingConfigured(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser('ADMIN'), 'api');

        $repoMock = $this->createMock(ParametreSystemeRepository::class);
        $repoMock->method('findOneByCle')->willReturn(null);
        $client->getContainer()->set(ParametreSystemeRepository::class, $repoMock);

        $client->request('GET', '/api/admin/validation-settings');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0.66, $data['consensusThreshold']);
        $this->assertSame(70, $data['datasetCandidateTrustThreshold']);
    }

    public function testAdminCanUpdateExistingParameter(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser('ADMIN'), 'api');

        $existing = new ParametreSysteme('validation.consensus_threshold', '0.66');
        $repoMock = $this->createMock(ParametreSystemeRepository::class);
        $repoMock->method('findOneByCle')
            ->willReturnCallback(fn (string $cle) => $cle === 'validation.consensus_threshold' ? $existing : null);
        $client->getContainer()->set(ParametreSystemeRepository::class, $repoMock);

        $client->request(
            'PATCH', '/api/admin/validation-settings',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['consensusThreshold' => 0.75]),
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('0.75', $existing->getValeur());
    }

    public function testConsensusThresholdOutOfRangeIsRejected(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser('ADMIN'), 'api');

        $client->request(
            'PATCH', '/api/admin/validation-settings',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['consensusThreshold' => 1.5]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDatasetTrustThresholdMustBeAnInteger(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser('ADMIN'), 'api');

        $client->request(
            'PATCH', '/api/admin/validation-settings',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['datasetCandidateTrustThreshold' => 70.5]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function makeUser(string $role): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole($role);

        return $user;
    }
}
