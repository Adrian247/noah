<?php

use App\Models\PortalSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $portal = PortalSetting::query()->find(1);
        if ($portal === null) {
            return;
        }

        $defaults = PortalSetting::defaultAttributes();
        $portal->fill([
            'service_description' => $defaults['service_description'],
            'service_highlights' => $defaults['service_highlights'],
        ])->save();
    }

    public function down(): void
    {
        $portal = PortalSetting::query()->find(1);
        if ($portal === null) {
            return;
        }

        $portal->fill([
            'service_description' => 'Phoenix centraliza rutinas en campo, validación técnica, evidencias y facturación en una sola plataforma pensada para operaciones industriales.',
            'service_highlights' => [
                'Rutinas y validación en tiempo real',
                'Formularios y reportes configurables',
                'Trazabilidad y auditoría por empresa',
            ],
        ])->save();
    }
};
