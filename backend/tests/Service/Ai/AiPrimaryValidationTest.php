<?php

namespace App\Tests\Service\Ai;

use App\Dto\AiAnalysisResult;
use App\Exception\AiAnalysisException;
use App\Tests\Support\SecondaryAiFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiPrimaryValidationTest extends TestCase
{
    use SecondaryAiFixtures;
    public static function malformed(): iterable
    {
        yield [['confidence' => 2]]; yield [['confidence' => '0.7']]; yield [['detector_confidence' => NAN]];
        yield [['embedding' => array_fill(0, 512, INF)]]; yield [['nom' => str_repeat('x', 101)]];
        yield [['detections' => [['label' => 'Quartz', 'confidence' => .7, 'detector_confidence' => .9, 'bbox' => [0,0,1,1], 'all_probabilities' => ['Quartz' => -1]]]]];
    }
    #[DataProvider('malformed')]
    public function testMalformedServerScoresAreRejected(array $overrides): void
    {
        $this->expectException(AiAnalysisException::class);
        AiAnalysisResult::fromArray(array_replace($this->primaryData(), $overrides));
    }
}
