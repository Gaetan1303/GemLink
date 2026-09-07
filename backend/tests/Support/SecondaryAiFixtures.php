<?php

namespace App\Tests\Support;

use App\Dto\{AiAnalysisResult, StoneAiReviewRequest, StoneCandidate};
use App\Service\Ai\CloudflareAiConfiguration;
use Symfony\Component\Uid\Uuid;

trait SecondaryAiFixtures
{
    private function configuration(array $overrides = []): CloudflareAiConfiguration
    {
        return new CloudflareAiConfiguration(...array_replace([
            'enabled' => true, 'accountId' => str_repeat('a', 32), 'apiToken' => 'test-token-never-log',
            'model' => '@cf/meta/test-vision', 'fallbackModel' => '', 'timeout' => 20, 'maxRetries' => 1,
            'autoAcceptThreshold' => .85, 'reviewThreshold' => .55, 'perUserMinute' => 10, 'perUserDay' => 20, 'dailyQuota' => 500,
        ], $overrides));
    }

    private function image(string $mime = 'image/png'): string
    {
        $image = imagecreatetruecolor(4, 3);
        ob_start();
        match ($mime) { 'image/jpeg' => imagejpeg($image), 'image/png' => imagepng($image), 'image/webp' => imagewebp($image) };
        return ob_get_clean();
    }

    private function request(array $overrides = []): StoneAiReviewRequest
    {
        return new StoneAiReviewRequest(...array_replace([
            'requestId' => Uuid::v7()->toRfc4122(), 'requesterKey' => 'user:' . Uuid::v7()->toRfc4122(),
            'candidates' => [new StoneCandidate('550e8400-e29b-41d4-a716-446655440000', 'Quartz', .7)],
            'modelConfidence' => .7, 'nearestReferences' => [], 'imageBase64' => base64_encode($this->image()), 'mimeType' => 'image/png',
        ], $overrides));
    }

    private function primaryData(float $confidence = .7): array
    {
        return ['nom' => 'Quartz', 'confidence' => $confidence, 'detector_confidence' => .9,
            'embedding' => array_fill(0, 512, .1), 'model_version' => ['yolo' => 'torchvision-v1', 'vit' => 'vit-v1', 'clip' => 'clip-v1']];
    }

    private function primary(float $confidence = .7): AiAnalysisResult { return AiAnalysisResult::fromArray($this->primaryData($confidence)); }

    private function verdict(array $overrides = []): array
    {
        return array_replace(['decision' => 'candidate', 'stoneId' => '550e8400-e29b-41d4-a716-446655440000', 'confidence' => .8, 'reasoningSummary' => ['Visible crystal facets.'], 'warnings' => []], $overrides);
    }
}
