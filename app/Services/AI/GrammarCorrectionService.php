<?php

namespace App\Services\AI;

class GrammarCorrectionService
{
    /**
     * Placeholder until AI Gateway is implemented; normalizes whitespace only.
     */
    public function correct(string $text): string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($trimmed === '') {
            return '';
        }

        return ucfirst($trimmed).(str_ends_with($trimmed, '.') ? '' : '.');
    }
}
