<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Línea de servicio en tipos de rutina; rutinas pueden ir a cliente sin activo
 * (fabricación / suministro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_types', function (Blueprint $table) {
            $table->string('service_line', 32)
                ->default('maintenance')
                ->after('slug')
                ->index();
        });

        Schema::table('routines', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('clients')
                ->nullOnDelete();
        });

        // PostgreSQL: dropear NOT NULL del asset_id.
        Schema::table('routines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
        });

        DB::statement('ALTER TABLE routines ALTER COLUMN asset_id DROP NOT NULL');

        Schema::table('routines', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->dropForeign(['asset_id']);
        });

        DB::statement('DELETE FROM routines WHERE asset_id IS NULL');
        DB::statement('ALTER TABLE routines ALTER COLUMN asset_id SET NOT NULL');

        Schema::table('routines', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
        });

        Schema::table('routine_types', function (Blueprint $table) {
            $table->dropIndex(['service_line']);
            $table->dropColumn('service_line');
        });
    }
};
