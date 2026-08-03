<?php

namespace Tests\Unit\Models;

use App\Models\FailurePrediction;
use PHPUnit\Framework\TestCase;

class FailurePredictionRiskLevelTest extends TestCase
{
    public function test_risk_level_is_decided_by_expected_failures(): void
    {
        $this->assertSame(FailurePrediction::RISK_CRITICAL, FailurePrediction::riskLevelFor(1.0));
        $this->assertSame(FailurePrediction::RISK_CRITICAL, FailurePrediction::riskLevelFor(3.4));
        $this->assertSame(FailurePrediction::RISK_HIGH, FailurePrediction::riskLevelFor(0.4));
        $this->assertSame(FailurePrediction::RISK_MEDIUM, FailurePrediction::riskLevelFor(0.15));
        $this->assertSame(FailurePrediction::RISK_LOW, FailurePrediction::riskLevelFor(0.149));
        $this->assertSame(FailurePrediction::RISK_LOW, FailurePrediction::riskLevelFor(0.0));
    }

    /**
     * La conversión importa porque el servicio ML entrega probabilidad y el nivel de riesgo se
     * calcula con valor esperado: las dos rutas tienen que caer en el mismo nivel.
     */
    public function test_expected_failures_round_trips_with_probability(): void
    {
        foreach ([0.05, 0.2, 0.45, 0.7, 0.9] as $expected) {
            $probability = 1 - exp(-$expected);
            $recovered = FailurePrediction::expectedFailuresFromProbability($probability);

            $this->assertEqualsWithDelta($expected, $recovered, 0.001);
            $this->assertSame(
                FailurePrediction::riskLevelFor($expected),
                FailurePrediction::riskLevelFor($recovered),
            );
        }
    }

    public function test_probability_of_one_is_bounded_instead_of_diverging(): void
    {
        $this->assertGreaterThan(0, FailurePrediction::expectedFailuresFromProbability(1.0));
        $this->assertLessThan(INF, FailurePrediction::expectedFailuresFromProbability(1.0));
        $this->assertSame(0.0, FailurePrediction::expectedFailuresFromProbability(0.0));
    }
}
