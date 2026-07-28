<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_definitions', function (Blueprint $table) {
            $table->string('usage', 32)->default('routine')->after('slug');
            $table->index(['company_id', 'usage']);
        });

        $equipmentFormIds = DB::table('equipment_types')
            ->whereNotNull('default_form_definition_id')
            ->pluck('default_form_definition_id');

        if ($equipmentFormIds->isNotEmpty()) {
            DB::table('form_definitions')
                ->whereIn('id', $equipmentFormIds)
                ->update(['usage' => 'equipment']);
        }

        Schema::table('supply_types', function (Blueprint $table) {
            $table->foreignId('default_form_definition_id')
                ->nullable()
                ->after('description')
                ->constrained('form_definitions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supply_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_form_definition_id');
        });

        Schema::table('form_definitions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'usage']);
            $table->dropColumn('usage');
        });
    }
};
