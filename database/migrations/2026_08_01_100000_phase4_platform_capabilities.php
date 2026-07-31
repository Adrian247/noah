<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type', 64);
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'trigger_type', 'is_active']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('secret', 128)->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('layout');
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('ai_monthly_token_quota')->nullable()->after('mobile_allow_biometric_unlock');
            $table->unsignedInteger('ai_monthly_vision_quota')->nullable()->after('ai_monthly_token_quota');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ai_monthly_token_quota', 'ai_monthly_vision_quota']);
        });

        Schema::dropIfExists('dashboard_preferences');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('automation_rules');
    }
};
