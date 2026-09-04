<?php



namespace App\Tests\Service;

use App\Entity\ParametreSysteme;
use App\Repository\ParametreSystemeRepository;
use App\Service\AdminSettingsProvider;
use App\Service\PointsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminSettingsProviderTest extends TestCase
{
    private ParametreSystemeRepository&MockObject $parametres;
    private AdminSettingsProvider $provider;

    protected function setUp(): void
    {
        $this->parametres = $this->createMock(ParametreSystemeRepository::class);
        $this->provider = new AdminSettingsProvider($this->parametres);
    }

    public function testConsensusThresholdDefaultsWhenNotConfigured(): void
    {
        $this->parametres->method('findOneByCle')->willReturn(null);

        $this->assertSame(0.66, $this->provider->getConsensusThreshold());
    }

    public function testDatasetCandidateTrustThresholdDefaultsWhenNotConfigured(): void
    {
        $this->parametres->method('findOneByCle')->willReturn(null);

        $this->assertSame(70, $this->provider->getDatasetCandidateTrustThreshold());
    }

    public function testConsensusThresholdUsesAdminConfiguredValue(): void
    {
        $this->parametres->method('findOneByCle')
            ->with('validation.consensus_threshold')
            ->willReturn(new ParametreSysteme('validation.consensus_threshold', '0.8'));

        $this->assertSame(0.8, $this->provider->getConsensusThreshold());
    }

    public function testDatasetCandidateTrustThresholdUsesAdminConfiguredValue(): void
    {
        $this->parametres->method('findOneByCle')
            ->with('validation.dataset_candidate_trust_threshold')
            ->willReturn(new ParametreSysteme('validation.dataset_candidate_trust_threshold', '85'));

        $this->assertSame(85, $this->provider->getDatasetCandidateTrustThreshold());
    }

    public function testIdentificationConfidenceThresholdUsesAdminConfiguredValue(): void
    {
        $this->parametres->method('findOneByCle')
            ->willReturnCallback(static fn (string $cle) => $cle === 'identification.confidence_threshold'
                ? new ParametreSysteme($cle, '0.4')
                : null);

        $this->assertSame(0.4, $this->provider->getIdentificationConfidenceThreshold());
    }

    public function testPointsScaleDefaultsAndUsesAdminValue(): void
    {
        $this->parametres->method('findOneByCle')
            ->willReturnMap([
                ['points.post_created', new ParametreSysteme('points.post_created', '12')],
                ['points.like_received', null],
            ]);

        $this->assertSame(12, $this->provider->getPointsForAction(PointsService::ACTION_POST_CREATED));
        $this->assertSame(2, $this->provider->getPointsForAction(PointsService::ACTION_LIKE_RECEIVED));
    }

    public function testRepositoryIsQueriedForEachLookupSoWorkersSeeAdminChanges(): void
    {
        $this->parametres->expects($this->exactly(3))
            ->method('findOneByCle')
            ->with('validation.consensus_threshold')
            ->willReturn(null);

        $this->provider->getConsensusThreshold();
        $this->provider->getConsensusThreshold();
        $this->provider->getConsensusThreshold();
    }
}
