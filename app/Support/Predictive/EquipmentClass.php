<?php

namespace App\Support\Predictive;

/**
 * Normaliza cómo se nombra la clase de un equipo.
 *
 * En las bitácoras y en el habla de piso la misma máquina es "SS", "scoop", "scooptram" o "LHD", y
 * el asistente recibe cualquiera de las tres. El catálogo y los modelos usan una sola forma
 * canónica, así que todo filtro por clase pasa antes por aquí.
 */
final class EquipmentClass
{
    /** @var array<string, string> alias sin acentos → clase canónica */
    private const ALIASES = [
        // Minería subterránea móvil
        'SS' => 'SCOOPTRAM',
        'SCOOP' => 'SCOOPTRAM',
        'SCOOPTRAM' => 'SCOOPTRAM',
        'LHD' => 'SCOOPTRAM',
        'CARGADOR' => 'SCOOPTRAM',
        'VQ' => 'CAMION_BAJO_PERFIL',
        'CAMION' => 'CAMION_BAJO_PERFIL',
        'CAMIONES' => 'CAMION_BAJO_PERFIL',
        'TRUCK' => 'CAMION_BAJO_PERFIL',
        'CAMION_BAJO_PERFIL' => 'CAMION_BAJO_PERFIL',
        'JB' => 'JUMBO',
        'JUMBO' => 'JUMBO',
        'JUMBOS' => 'JUMBO',
        'PERFORADORA' => 'JUMBO',

        // Planta de beneficio
        'QUEBRADORA' => 'QUEBRADORA',
        'QUEBRADORAS' => 'QUEBRADORA',
        'TRITURADORA' => 'QUEBRADORA',
        'CRUSHER' => 'QUEBRADORA',
        'MOLINO' => 'MOLINO',
        'MOLINOS' => 'MOLINO',
        'MILL' => 'MOLINO',
        'CRIBA' => 'CRIBA',
        'CRIBAS' => 'CRIBA',
        'HARNERO' => 'CRIBA',
        'CELDA' => 'CELDA_FLOTACION',
        'CELDAS' => 'CELDA_FLOTACION',
        'FLOTACION' => 'CELDA_FLOTACION',
        'CELDA_FLOTACION' => 'CELDA_FLOTACION',
        'ESPESADOR' => 'ESPESADOR',
        'FILTRO' => 'FILTRO',
        'BANDA' => 'BANDA_TRANSPORTADORA',
        'BANDAS' => 'BANDA_TRANSPORTADORA',
        'TRANSPORTADOR' => 'BANDA_TRANSPORTADORA',
        'BANDA_TRANSPORTADORA' => 'BANDA_TRANSPORTADORA',
        'ALIMENTADOR' => 'ALIMENTADOR',
        'BOMBA' => 'BOMBA',
        'BOMBAS' => 'BOMBA',
        'ELEVADOR' => 'ELEVADOR',
        'ACONDICIONADOR' => 'ACONDICIONADOR',
        'COMPRESOR' => 'COMPRESOR',
        'VENTILADOR' => 'VENTILADOR',
        'SOPLADOR' => 'VENTILADOR',
        'COLECTOR_POLVOS' => 'COLECTOR_POLVOS',
        'MOTOR_ELECTRICO' => 'MOTOR_ELECTRICO',
        'TANQUE' => 'TANQUE',
        'GRUA' => 'GRUA',
        'SECADOR' => 'SECADOR',
    ];

    public static function canonical(?string $value): ?string
    {
        $key = self::key($value);
        if ($key === '') {
            return null;
        }

        return self::ALIASES[$key] ?? $key;
    }

    /** Compara dos formas de nombrar una clase sin importar cuál se usó. */
    public static function matches(?string $a, ?string $b): bool
    {
        $left = self::canonical($a);
        $right = self::canonical($b);

        return $left !== null && $right !== null && $left === $right;
    }

    /**
     * @param  list<string>  $classes
     */
    public static function inList(?string $value, array $classes): bool
    {
        $canonical = self::canonical($value);
        if ($canonical === null) {
            return false;
        }

        foreach ($classes as $class) {
            if (self::canonical($class) === $canonical) {
                return true;
            }
        }

        return false;
    }

    private static function key(?string $value): string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return '';
        }

        // Se quitan acentos antes de subir a mayúsculas: `strtoupper` deja "camión" como "CAMIóN".
        $folded = strtr($trimmed, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        return (string) preg_replace('/[^A-Z0-9_]+/', '_', mb_strtoupper($folded));
    }
}
