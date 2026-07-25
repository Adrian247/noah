<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('supply_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::create('sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 128);
            $table->string('event_id', 64);
            $table->string('event_type', 64);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'device_id', 'event_id']);
        });

        Schema::create('execution_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_execution_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('evidence');
            $table->string('path');
            $table->string('mime', 128)->nullable();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_evidences');
        Schema::dropIfExists('sync_events');
        Schema::table('supply_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::dropIfExists('suppliers');
    }
};
