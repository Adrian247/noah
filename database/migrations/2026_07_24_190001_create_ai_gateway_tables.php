<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->unsignedInteger('version')->default(1);
            $table->string('provider')->default('local');
            $table->string('model')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.2);
            $table->text('system_prompt');
            $table->text('user_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'slug', 'version']);
        });

        Schema::create('ai_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('use_case');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('input_excerpt')->nullable();
            $table->text('output_excerpt')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_invocations');
        Schema::dropIfExists('prompt_templates');
    }
};
