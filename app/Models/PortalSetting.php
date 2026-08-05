<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSetting extends Model
{
    protected $fillable = [
        'hero_image_url',
        'hero_image_alt',
        'service_title',
        'service_description',
        'service_highlights',
        'help_title',
        'help_text',
        'contact_email',
        'contact_phone',
        'contact_hours',
    ];

    protected function casts(): array
    {
        return [
            'service_highlights' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            self::defaultAttributes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'hero_image_url' => null,
            'hero_image_alt' => null,
            'service_title' => 'Gestión técnica clara para operaciones industriales',
            'service_description' => 'Phoenix centraliza servicios en campo, validación técnica, evidencias, facturación y un asistente operativo con grounding en datos reales, en una sola plataforma para operaciones industriales.',
            'service_highlights' => [
                'Servicios y validación en tiempo real',
                'Formularios, reportes y workflows configurables',
                'Asistente IA con datos verificados de tu empresa',
                'Trazabilidad y auditoría por organización',
            ],
            'help_title' => '¿Problemas para acceder?',
            'help_text' => 'Si olvidaste tu contraseña o no tienes usuario, contacta al administrador de tu empresa.',
            'contact_email' => 'soporte@pyro-systems.com',
            'contact_phone' => '+52 55 0000 0000',
            'contact_hours' => 'Lun–Vie 8:00–18:00 (hora Ciudad de México)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPortalPayload(): array
    {
        return [
            'service_title' => $this->service_title,
            'service_description' => $this->service_description,
            'service_highlights' => $this->service_highlights ?? [],
            'help_title' => $this->help_title,
            'help_text' => $this->help_text,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_hours' => $this->contact_hours,
        ];
    }
}
