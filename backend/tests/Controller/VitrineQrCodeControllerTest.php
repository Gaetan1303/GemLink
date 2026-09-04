<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vitrine;
use App\Repository\VitrineRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les tests testDownloadRedirectsToQrCodeUrlForOwner() et
 * testDownloadIsForbiddenForNonOwner() supposent l'existence d'un Voter
 * "VIEW" sur Vitrine (référencé par denyAccessUnlessGranted() dans le
 * contrôleur). Sans ce Voter, la stratégie de décision par défaut de
 * Symfony refuse l'accès dès que personne ne se prononce (aucun voter
 * n'accorde), donc le test owner échouerait en 403 au lieu de 302 tant que
 * le Voter n'est pas créé.
 */
class VitrineQrCodeControllerTest extends WebTestCase
{
    public function testDownloadRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/vitrines/0198abcd-1234-7000-8000-000000000099/qr-code');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDownloadReturns404WhenVitrineDoesNotExist(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $user = $this->buildActiveUser();

        /** @var VitrineRepository&MockObject $vitrineRepository */
        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('find')
            ->with('slug-inexistant')
            ->willReturn(null);

        $container->set(VitrineRepository::class, $vitrineRepository);

        $client->loginUser($user, 'api');
        $client->request('GET', '/api/vitrines/slug-inexistant/qr-code');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDownloadReturns404WhenQrCodeNotYetGenerated(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $owner = $this->buildActiveUser();
        $vitrine = new Vitrine($owner, 'Ma collection', 'ma-collection');
        // qrCodeUrl volontairement laissé à null.

        /** @var VitrineRepository&MockObject $vitrineRepository */
        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('find')
            ->with((string) $vitrine->getId())
            ->willReturn($vitrine);

        $container->set(VitrineRepository::class, $vitrineRepository);

        $client->loginUser($owner, 'api');
        $client->request('GET', sprintf('/api/vitrines/%s/qr-code', $vitrine->getId()));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDownloadRedirectsToQrCodeUrlForOwner(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $owner = $this->buildActiveUser();
        $vitrine = new Vitrine($owner, 'Ma collection', 'ma-collection');
        $vitrine->setQrCodeUrl('https://cdn.gem-link.org/qr-codes/ma-collection.png');

        /** @var VitrineRepository&MockObject $vitrineRepository */
        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('find')
            ->with((string) $vitrine->getId())
            ->willReturn($vitrine);

        $container->set(VitrineRepository::class, $vitrineRepository);

        $client->loginUser($owner, 'api');
        $client->request('GET', sprintf('/api/vitrines/%s/qr-code', $vitrine->getId()));

        $this->assertResponseRedirects('https://cdn.gem-link.org/qr-codes/ma-collection.png');
    }

    public function testDownloadIsForbiddenForNonOwner(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $owner = $this->buildActiveUser();
        $someoneElse = $this->buildActiveUser();

        $vitrine = new Vitrine($owner, 'Ma collection', 'ma-collection');
        $vitrine->setQrCodeUrl('https://cdn.gem-link.org/qr-codes/ma-collection.png');

        /** @var VitrineRepository&MockObject $vitrineRepository */
        $vitrineRepository = $this->createMock(VitrineRepository::class);
        $vitrineRepository->method('find')
            ->with((string) $vitrine->getId())
            ->willReturn($vitrine);

        $container->set(VitrineRepository::class, $vitrineRepository);

        $client->loginUser($someoneElse, 'api');
        $client->request('GET', sprintf('/api/vitrines/%s/qr-code', $vitrine->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    private function buildActiveUser(): User
    {
        $user = new User();
        $user->setEmail(sprintf('%s@gem-link.org', bin2hex(random_bytes(4))));
        $user->setUsername('testuser_' . bin2hex(random_bytes(4)));
        $user->setPasswordHash('irrelevant-for-this-test');
        $user->setStatus('ACTIVE');

        return $user;
    }
}