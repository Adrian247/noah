<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_form_definition_id')
                ->nullable()
                ->constrained('form_definitions')
                ->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('supply_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                ->nullable()
                ->after('company_id')
                ->constrained('equipment_types')
                ->restrictOnDelete();
        });

        Schema::table('supply_items', function (Blueprint $table) {
            $table->foreignId('supply_type_id')
                ->nullable()
                ->after('company_id')
                ->constrained('supply_types')
                ->restrictOnDelete();
            $table->json('specifications')->nullable()->after('standard_cost');
        });
    }

    public function down(): void
    {
        Schema::table('supply_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_type_id');
            $table->dropColumn('specifications');
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_type_id');
        });

        Schema::dropIfExists('supply_types');
        Schema::dropIfExists('equipment_types');
    }
};
