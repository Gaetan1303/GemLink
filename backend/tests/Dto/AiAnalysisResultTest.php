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
            'detections' => [
                ['label' => 'Quartz', 'confidence' => 0.91, 'detector_confidence' => 0.87, 'bbox' => [10, 20, 100, 120], 'embedding' => array_fill(0, 512, 1 / sqrt(512))],
                ['label' => 'Améthyste', 'confidence' => 0.82, 'detector_confidence' => 0.79, 'bbox' => [110, 20, 190, 130], 'embedding' => array_fill(0, 512, 1 / sqrt(512))],
            ],
        ]);

        self::assertSame('clip-vit-b-32-openai', $result->getClipModelVersion());
        self::assertSame('vit-stones-v4', $result->getModelVersion()['vit']);
        self::assertCount(2, $result->getDetections());
        self::assertSame('Améthyste', $result->getDetections()[1]['nom']);
        self::assertSame([110, 20, 190, 130], $result->getDetections()[1]['bbox']);
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
