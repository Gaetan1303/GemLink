<?php



namespace App\Tests\Service;

use App\Service\ValidationWeightCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * US 2.7 CA-3 : un Trust Score de 80 doit peser exactement 4x plus qu'un
 * Trust Score de 20 dans le calcul de consensus.
 */
final class ValidationWeightCalculatorTest extends TestCase
{
    private ValidationWeightCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ValidationWeightCalculator();
    }

    public function testWeightIsEqualToTrustScore(): void
    {
        $this->assertSame(80.0, $this->calculator->fromTrustScore(80));
        $this->assertSame(20.0, $this->calculator->fromTrustScore(20));
    }

    public function testTrustScoreEightyWeighsFourTimesTrustScoreTwenty(): void
    {
        $high = $this->calculator->fromTrustScore(80);
        $low = $this->calculator->fromTrustScore(20);

        $this->assertSame(4.0, $high / $low);
    }

    #[DataProvider('lowTrustScores')]
    public function testZeroOrNegativeTrustScoreIsFlooredToMinWeight(int $trustScore): void
    {
        $this->assertSame(1.0, $this->calculator->fromTrustScore($trustScore));
    }

    public static function lowTrustScores(): array
    {
        return [
            'zero' => [0],
            'negative (should not happen but must not break the calculator)' => [-5],
        ];
    }

    public function testMaxTrustScoreIsHundred(): void
    {
        $this->assertSame(100.0, $this->calculator->fromTrustScore(100));
    }
}
