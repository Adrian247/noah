<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reorienta el predictivo: predicción sobre historial de rutinas aplicadas;
 * versiones de algoritmo publicables; opt-in de recolección para entrenamiento;
 * vínculo OEM ↔ catálogo de equipos del tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_algorithm_versions', function (Blueprint $table) {
            $table->id();
            $table->string('semver', 32);
            $table->string('status', 32)->default('draft'); // draft | published | archived
            $table->string('kind', 64)->default('hazard_routines_v1');
            $table->text('notes')->nullable();
            $table->json('metrics')->nullable();
            $table->json('training_summary')->nullable();
            $table->string('artifact_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('semver');
            $table->index(['status', 'published_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('allow_predictive_training_collection')
                ->default(false)
                ->after('ai_enabled');
            $table->foreignId('predictive_algorithm_version_id')
                ->nullable()
                ->after('allow_predictive_training_collection')
                ->constrained('predictive_algorithm_versions')
                ->nullOnDelete();
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->foreignId('oem_equipment_model_id')
                ->nullable()
                ->after('manufacturer')
                ->constrained('oem_equipment_models')
                ->nullOnDelete();
        });

        Schema::table('failure_predictions', function (Blueprint $table) {
            $table->foreignId('predictive_algorithm_version_id')
                ->nullable()
                ->after('model_version')
                ->constrained('predictive_algorithm_versions')
                ->nullOnDelete();
            $table->string('feature_source', 32)->nullable()->after('predictive_algorithm_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('failure_predictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('predictive_algorithm_version_id');
            $table->dropColumn('feature_source');
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('oem_equipment_model_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('predictive_algorithm_version_id');
            $table->dropColumn('allow_predictive_training_collection');
        });

        Schema::dropIfExists('predictive_algorithm_versions');
    }
};
