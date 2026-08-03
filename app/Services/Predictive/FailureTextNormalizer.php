<?php

namespace App\Services\Predictive;

use App\Models\FailureMode;
use App\Support\Predictive\FailureModeCatalog;
use Illuminate\Support\Collection;

/**
 * Normaliza el texto libre de una bitácora al modo de falla de la taxonomía.
 *
 * Las bitácoras reales escriben el mismo modo de decenas de formas, con y sin acentos y con
 * errores de dedo. La coincidencia se hace por subcadena sobre texto plegado y, si nada coincide,
 * por similitud de palabras contra el nombre y los patrones del modo.
 */
class FailureTextNormalizer
{
    /** @var array<int, Collection<int, FailureMode>> */
    private array $cache = [];

    /**
     * @return array{mode: FailureMode|null, confidence: float, matched_pattern: string|null}
     */
    public function match(int $companyId, ?string $text, ?string $equipmentClass = null): array
    {
        $needle = self::fold($text);
        if ($needle === '') {
            return ['mode' => null, 'confidence' => 0.0, 'matched_pattern' => null];
        }

        $modes = $this->modes($companyId)
            ->filter(fn (FailureMode $mode) => $mode->appliesToClass($equipmentClass));

        // El patrón más largo gana: "caja de transferencia" antes que "caja".
        $best = null;
        $bestLength = 0;
        foreach ($modes as $mode) {
            foreach ((array) $mode->text_patterns as $pattern) {
                $folded = self::fold((string) $pattern);
                if ($folded === '' || ! str_contains($needle, $folded)) {
                    continue;
                }
                if (strlen($folded) > $bestLength) {
                    $best = ['mode' => $mode, 'matched_pattern' => (string) $pattern];
                    $bestLength = strlen($folded);
                }
            }
        }

        if ($best !== null) {
            // Un patrón que cubre casi todo el texto es una coincidencia más fuerte.
            $coverage = min(1.0, $bestLength / max(1, strlen($needle)));

            return [
                'mode' => $best['mode'],
                'confidence' => round(0.70 + 0.30 * $coverage, 4),
                'matched_pattern' => $best['matched_pattern'],
            ];
        }

        return $this->fuzzyMatch($needle, $modes);
    }

    /** Resuelve el modo, cayendo al modo comodín cuando no hay coincidencia utilizable. */
    public function resolveOrFallback(int $companyId, ?string $text, ?string $equipmentClass = null): ?FailureMode
    {
        $result = $this->match($companyId, $text, $equipmentClass);
        if ($result['mode'] !== null && $result['confidence'] >= 0.45) {
            return $result['mode'];
        }

        return $this->modes($companyId)->firstWhere('code', FailureModeCatalog::FALLBACK_CODE);
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * @param  Collection<int, FailureMode>  $modes
     * @return array{mode: FailureMode|null, confidence: float, matched_pattern: string|null}
     */
    private function fuzzyMatch(string $needle, Collection $modes): array
    {
        $words = array_values(array_filter(
            explode(' ', $needle),
            fn (string $word) => strlen($word) >= 4,
        ));
        if ($words === []) {
            return ['mode' => null, 'confidence' => 0.0, 'matched_pattern' => null];
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($modes as $mode) {
            $candidates = array_merge([$mode->name], (array) $mode->text_patterns);
            foreach ($candidates as $candidate) {
                foreach (explode(' ', self::fold((string) $candidate)) as $token) {
                    if (strlen($token) < 4) {
                        continue;
                    }
                    foreach ($words as $word) {
                        $distance = levenshtein($word, $token);
                        $tolerance = strlen($token) >= 7 ? 2 : 1;
                        if ($distance > $tolerance) {
                            continue;
                        }
                        $score = 1.0 - ($distance / max(1, strlen($token)));
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $best = ['mode' => $mode, 'matched_pattern' => (string) $candidate];
                        }
                    }
                }
            }
        }

        if ($best === null) {
            return ['mode' => null, 'confidence' => 0.0, 'matched_pattern' => null];
        }

        // La coincidencia difusa nunca supera la textual: se topa por debajo de 0.70.
        return [
            'mode' => $best['mode'],
            'confidence' => round(min(0.65, 0.45 + 0.20 * $bestScore), 4),
            'matched_pattern' => $best['matched_pattern'],
        ];
    }

    /**
     * @return Collection<int, FailureMode>
     */
    private function modes(int $companyId): Collection
    {
        return $this->cache[$companyId] ??= FailureMode::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
    }

    /** Minúsculas, sin acentos y sin puntuación, para comparar texto de campo. */
    public static function fold(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $lower = mb_strtolower(trim($value), 'UTF-8');
        $ascii = strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e',
        ]);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $ascii) ?? '') ?? '');
    }
}
