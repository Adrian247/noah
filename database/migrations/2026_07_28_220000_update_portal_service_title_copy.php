<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('portal_settings')
            ->where('service_title', 'Mantenimiento que no se detiene')
            ->update([
                'service_title' => 'Gestión técnica clara para operaciones industriales',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('portal_settings')
            ->where('service_title', 'Gestión técnica clara para operaciones industriales')
            ->update([
                'service_title' => 'Mantenimiento que no se detiene',
                'updated_at' => now(),
            ]);
    }
};
