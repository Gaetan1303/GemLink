<?php

namespace App\Tests\Service\Ai;

use App\Dto\{AiAnalysisResult, StoneAiReviewRequest, StoneAiReviewResponse};
use App\Entity\Pierre;
use App\Exception\CloudflareAiException;
use App\Repository\{PierreRepository, EmbeddingRepository};
use App\Service\{AiOrchestrationService, SecondaryAiReviewerInterface};
use App\Tests\Support\SecondaryAiFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class AiOrchestrationReviewTest extends TestCase
{
    use SecondaryAiFixtures;

    public static function bypassScores(): iterable { yield [.85]; yield [.95]; yield [1.0]; }

    #[DataProvider('bypassScores')]
    public function testHighScoreDoesNotCallSecondaryEvenWithoutSecrets(float $score): void
    {
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class);
        $reviewer->expects(self::never())->method('review');
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(['apiToken' => '']));
        $primary = $this->primary($score);
        self::assertSame($primary, $this->runReview($service, $primary));
    }

    public function testDisabledRetainsLegacyResult(): void
    {
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class);
        $reviewer->expects(self::never())->method('review');
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(['enabled' => false]));
        $primary = $this->primary(.2);
        self::assertSame($primary, $this->runReview($service, $primary));
    }

    public function testLowScoreIsUnknownWithoutNetwork(): void
    {
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class);
        $reviewer->expects(self::never())->method('review');
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration());
        $result = $this->runReview($service, $this->primary(.54));
        self::assertTrue($result->isUnknown());
        self::assertSame('UNKNOWN', $result->getLabel());
        self::assertNull($result->getComposition());
    }

    public function testMediumCallsSecondaryUsingPrimaryCropScoresAndCatalogueOnly(): void
    {
        $quartz = new Pierre('Quartz'); $fluorite = new Pierre('Fluorite'); $fluorite->setComposition('CaF2');
        $catalogue = $this->createMock(PierreRepository::class);
        $catalogue->expects(self::exactly(3))->method('findOneByNameIgnoreCase')->willReturnCallback(fn ($name) => match ($name) { 'Quartz' => $quartz, 'Fluorite' => $fluorite, default => null });
        $references = $this->createMock(EmbeddingRepository::class);
        $references->expects(self::once())->method('findReviewReferences')->with(array_fill(0, 512, .1), 'clip-v1', self::callback(fn ($id) => is_string($id)))->willReturn([['name' => 'Quartz', 'similarity' => .8]]);
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class);
        $reviewer->expects(self::once())->method('review')->willReturnCallback(function (StoneAiReviewRequest $request) use ($quartz, $fluorite) {
            self::assertSame([$quartz->getId()->toRfc4122(), $fluorite->getId()->toRfc4122()], array_column($request->candidates, 'stoneId'));
            self::assertSame([.6, .3], array_column($request->candidates, 'score'));
            self::assertSame('Quartz', $request->nearestReferences[0]->name);
            return StoneAiReviewResponse::fromArray($this->verdict(['stoneId' => $fluorite->getId()->toRfc4122()]), $request, '@cf/meta/test-vision');
        });
        $data = $this->primaryData(.6);
        $data['formule_chimique'] = 'SiO2';
        $data['detections'] = [
            ['label' => 'Quartz', 'confidence' => .6, 'detector_confidence' => .9, 'bbox' => [0,0,4,3], 'all_probabilities' => ['Absent catalogue' => .1, 'Fluorite' => .3, 'Quartz' => .6]],
            ['label' => 'Ruby', 'confidence' => .99, 'detector_confidence' => .7, 'bbox' => [0,0,2,2], 'all_probabilities' => ['Ruby' => .99]],
        ];
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(), $catalogue, $references);
        $result = $this->runReview($service, AiAnalysisResult::fromArray($data));
        self::assertSame('Fluorite', $result->getLabel());
        self::assertSame('CaF2', $result->getComposition());
        self::assertSame(.3, $result->getConfidence()); // Never promote a score with LLM confidence.
        self::assertSame(array_fill(0, 512, .1), $result->getEmbedding());
    }

    public static function failures(): iterable
    {
        yield [new CloudflareAiException('timeout')]; yield [new CloudflareAiException('disabled')];
        yield [new CloudflareAiException('quota_exceeded')]; yield [new \RuntimeException('private failure')];
    }

    #[DataProvider('failures')]
    public function testFailureOnMediumBecomesUnknownWithoutThrowing(\Throwable $error): void
    {
        $catalogue = $this->createStub(PierreRepository::class); $catalogue->method('findOneByNameIgnoreCase')->willReturn(new Pierre('Quartz'));
        $references = $this->createStub(EmbeddingRepository::class); $references->method('findReviewReferences')->willReturn([]);
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class); $reviewer->expects(self::once())->method('review')->willThrowException($error);
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(), $catalogue, $references);
        self::assertTrue($this->runReview($service, $this->primary())->isUnknown());
    }

    public function testUnknownCatalogueDoesNotInventUuidOrCreateStone(): void
    {
        $catalogue = $this->createStub(PierreRepository::class); $catalogue->method('findOneByNameIgnoreCase')->willReturn(null);
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class); $reviewer->expects(self::never())->method('review');
        $service = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(), $catalogue, $this->createStub(EmbeddingRepository::class));
        self::assertTrue($this->runReview($service, $this->primary())->isUnknown());
    }

    private function runReview(AiOrchestrationService $service, AiAnalysisResult $primary): AiAnalysisResult
    {
        return $service->reviewAnalysis($primary, $this->image(), 'image/png', Uuid::v7()->toRfc4122(), 'user:test');
    }
}
