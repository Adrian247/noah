<?php

namespace App\Services\AI;

use App\Models\Routine;

/**
 * Fachada de capacidades de producto; toda invocación pasa por AiGateway.
 */
class AiCapabilitiesService
{
    public function __construct(
        private readonly AiGateway $gateway,
    ) {}

    public function generateReportNarrative(Routine $routine, ?int $userId = null): string
    {
        return $this->gateway->generateReportNarrative($routine, $userId);
    }

    public function extractPlateText(string $imageContents, ?int $userId = null): string
    {
        return $this->gateway->extractPlateText($imageContents, $userId);
    }
}
