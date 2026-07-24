<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_execution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('report_template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('mime')->default('application/pdf');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
