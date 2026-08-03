<?php

namespace App\Tests\Dto;

use App\Dto\AiAnalysisResult;
use App\Exception\AiAnalysisException;
use PHPUnit\Framework\TestCase;

final class AiAnalysisResultTest extends TestCase
{
    public function testReadsAllPipelineModelVersions(): void
    {
        $result = AiAnalysisResult::fromArray([
            'nom' => 'Quartz',
            'confidence' => 0.91,
            'detector_confidence' => 0.87,
            'embedding' => array_fill(0, 512, 1 / sqrt(512)),
            'model_version' => [
                'yolo' => 'yolov8-stones-v2',
                'vit' => 'vit-stones-v4',
                'clip' => 'clip-vit-b-32-openai',
            ],
        ]);

        self::assertSame('clip-vit-b-32-openai', $result->getClipModelVersion());
        self::assertSame('vit-stones-v4', $result->getModelVersion()['vit']);
    }

    public function testRejectsAnIncompleteModelVersion(): void
    {
        $this->expectException(AiAnalysisException::class);
        $this->expectExceptionMessage('model_version.clip');

        AiAnalysisResult::fromArray([
            'nom' => 'Quartz',
            'confidence' => 0.91,
            'detector_confidence' => 0.87,
            'embedding' => array_fill(0, 512, 1 / sqrt(512)),
            'model_version' => ['yolo' => 'v1', 'vit' => 'v1'],
        ]);
    }
}
