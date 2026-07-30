<?php



namespace App\Tests\Service;

use App\Entity\ParametreSysteme;
use App\Repository\ParametreSystemeRepository;
use App\Service\AdminSettingsProvider;
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

    public function testRepositoryIsQueriedOnlyOncePerKeyWithinTheSameRequest(): void
    {
        $this->parametres->expects($this->once())
            ->method('findOneByCle')
            ->with('validation.consensus_threshold')
            ->willReturn(null);

        $this->provider->getConsensusThreshold();
        $this->provider->getConsensusThreshold();
        $this->provider->getConsensusThreshold();
    }
}
