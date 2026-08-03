<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espina de datos de confiabilidad y mantenimiento predictivo (cambio 046).
 *
 * Multipropósito: la bitácora por turno, los eventos de máquina y las mediciones de condición
 * no asumen minería; el catálogo OEM y la taxonomía se siembran como referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            /** Sistema o subconjunto afectado: motor_diesel, transmision, hidraulico, etc. */
            $table->string('system', 64);
            $table->text('description')->nullable();
            /** Clases de equipo aplicables (JB, SS, VQ, QUEBRADORA…); vacío = todas. */
            $table->json('equipment_classes')->nullable();
            $table->string('severity', 16)->default('medium');
            $table->text('typical_symptoms')->nullable();
            $table->text('typical_causes')->nullable();
            /** Señales que lo anticipan: consumo_aceite, vibracion, alarma_plc… */
            $table->json('monitoring_signals')->nullable();
            /** Códigos de evento/alarma de máquina que lo preceden (p. ej. W651, A403). */
            $table->json('precursor_event_codes')->nullable();
            $table->decimal('mean_repair_hours', 8, 2)->nullable();
            /** Sinónimos y variantes de texto libre para normalizar bitácoras. */
            $table->json('text_patterns')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'system']);
        });

        Schema::create('equipment_shift_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->string('shift', 16)->nullable();
            $table->decimal('scheduled_hours', 8, 2)->default(0);
            $table->decimal('worked_hours', 8, 2)->default(0);
            $table->decimal('standby_hours', 8, 2)->default(0);
            $table->decimal('preventive_hours', 8, 2)->default(0);
            $table->decimal('corrective_hours', 8, 2)->default(0);
            $table->decimal('operative_fail_hours', 8, 2)->default(0);
            $table->decimal('no_operator_hours', 8, 2)->default(0);
            $table->decimal('availability', 6, 4)->nullable();
            $table->decimal('utilization', 6, 4)->nullable();
            $table->decimal('hour_meter_start', 12, 2)->nullable();
            $table->decimal('hour_meter_end', 12, 2)->nullable();
            $table->decimal('diesel_liters', 10, 2)->nullable();
            $table->decimal('oil_liters', 10, 2)->nullable();
            $table->decimal('coolant_liters', 10, 2)->nullable();
            /** Producción del turno según el equipo: toneladas, metros, barrenos… */
            $table->json('production')->nullable();
            $table->string('location_label')->nullable();
            $table->string('equipment_status', 32)->nullable();
            $table->text('failure_text')->nullable();
            $table->text('comments')->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'logged_on', 'shift'], 'equipment_shift_logs_unique_slot');
            $table->index(['company_id', 'logged_on']);
        });

        Schema::create('equipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('code', 32);
            $table->string('name');
            /** alarm | warning | message */
            $table->string('severity', 16)->default('message');
            $table->unsignedInteger('occurrences')->default(1);
            $table->string('source', 32)->default('plc');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'occurred_at', 'code', 'source'], 'equipment_events_unique_slot');
            $table->index(['company_id', 'occurred_at']);
            $table->index(['asset_id', 'code', 'occurred_at']);
        });

        Schema::create('equipment_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('failure_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('downtime_hours', 8, 2)->nullable();
            /** corrective | preventive | operational */
            $table->string('maintenance_type', 32)->default('corrective');
            $table->text('reported_text')->nullable();
            $table->decimal('hour_meter', 12, 2)->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('external_ref')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'started_at', 'maintenance_type'], 'equipment_failures_unique_slot');
            $table->index(['company_id', 'started_at']);
        });

        Schema::create('equipment_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 64);
            $table->string('description')->nullable();
            $table->string('work_center', 64)->nullable();
            $table->string('location_code', 64)->nullable();
            $table->date('planned_for')->nullable();
            $table->date('executed_on')->nullable();
            /** planned | executed | skipped */
            $table->string('status', 32)->default('planned');
            $table->text('skip_reason')->nullable();
            $table->string('supervisor')->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'planned_for']);
        });

        Schema::create('equipment_component_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('component', 128);
            $table->text('description')->nullable();
            $table->timestamp('replaced_at');
            $table->decimal('hour_meter', 12, 2)->nullable();
            /** Vida esperada del componente en horas, para vida remanente. */
            $table->decimal('expected_life_hours', 10, 2)->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->unique(['asset_id', 'component', 'replaced_at'], 'equipment_component_repl_unique');
        });

        Schema::create('equipment_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            /** vibration_rms_mm_s, oil_iso4406, oil_fe_ppm, bearing_temp_c, hour_meter… */
            $table->string('metric', 64);
            $table->decimal('value', 16, 4);
            $table->string('unit', 32)->nullable();
            $table->timestamp('measured_at');
            /** Umbral de alerta vigente al medir, para evaluar desviación. */
            $table->decimal('threshold_warning', 16, 4)->nullable();
            $table->decimal('threshold_critical', 16, 4)->nullable();
            $table->string('source', 32)->default('manual');
            $table->timestamps();

            $table->unique(['asset_id', 'metric', 'measured_at'], 'equipment_measurements_unique_slot');
        });

        Schema::create('failure_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('failure_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->date('predicted_on');
            $table->unsignedSmallInteger('horizon_days');
            /** P(al menos una falla dentro de la ventana). */
            $table->decimal('probability', 6, 4);
            /** Número esperado de fallas en la ventana; no se satura como la probabilidad. */
            $table->decimal('expected_failures', 8, 4)->nullable();
            /** low | medium | high | critical */
            $table->string('risk_level', 16);
            $table->decimal('expected_downtime_hours', 8, 2)->nullable();
            /** Factores que explican el riesgo, con peso y evidencia. */
            $table->json('drivers')->nullable();
            $table->json('features')->nullable();
            /** heuristic | ml */
            $table->string('model_kind', 32)->default('heuristic');
            $table->string('model_version', 64)->nullable();
            /** Se completa al evaluar el acierto: ocurrió o no dentro de la ventana. */
            $table->boolean('outcome_failed')->nullable();
            $table->timestamp('outcome_evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['asset_id', 'predicted_on', 'horizon_days', 'failure_mode_id'],
                'failure_predictions_unique_slot'
            );
            $table->index(['company_id', 'predicted_on', 'risk_level'], 'failure_predictions_risk_idx');
        });

        // Catálogos de referencia globales (sin empresa): se copian al catálogo del tenant al usarse.
        Schema::create('oem_equipment_models', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer', 64);
            $table->string('family', 96);
            $table->string('model', 96);
            /** Clase funcional: jumbo, scooptram, camion_bajo_perfil, quebradora, molino… */
            $table->string('equipment_class', 64);
            $table->string('application', 32)->default('underground');
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->unique(['manufacturer', 'model']);
            $table->index(['equipment_class']);
        });

        Schema::create('oem_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer', 64);
            $table->string('equipment_class', 64);
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->unique(['manufacturer', 'equipment_class', 'name'], 'oem_maintenance_plans_unique');
        });

        Schema::create('oem_maintenance_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oem_maintenance_plan_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('interval_hours');
            $table->string('task');
            $table->string('system', 64)->nullable();
            $table->text('detail')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['oem_maintenance_plan_id', 'interval_hours'], 'oem_plan_items_interval_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oem_maintenance_plan_items');
        Schema::dropIfExists('oem_maintenance_plans');
        Schema::dropIfExists('oem_equipment_models');
        Schema::dropIfExists('failure_predictions');
        Schema::dropIfExists('equipment_measurements');
        Schema::dropIfExists('equipment_component_replacements');
        Schema::dropIfExists('equipment_work_orders');
        Schema::dropIfExists('equipment_failures');
        Schema::dropIfExists('equipment_events');
        Schema::dropIfExists('equipment_shift_logs');
        Schema::dropIfExists('failure_modes');
    }
};
