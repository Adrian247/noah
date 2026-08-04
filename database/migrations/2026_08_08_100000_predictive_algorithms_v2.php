<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_training_documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 64)->index();
            $table->string('name');
            $table->string('original_filename');
            $table->string('mime', 128)->nullable();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->unsignedInteger('byte_size')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->string('status', 32)->default('ready'); // ready|invalid|consumed
            $table->json('validation_errors')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('predictive_algorithm_versions', function (Blueprint $table) {
            $table->json('calibration')->nullable()->after('metrics');
            $table->json('regression_report')->nullable()->after('calibration');
        });
    }

    public function down(): void
    {
        Schema::table('predictive_algorithm_versions', function (Blueprint $table) {
            $table->dropColumn(['calibration', 'regression_report']);
        });

        Schema::dropIfExists('predictive_training_documents');
    }
};
