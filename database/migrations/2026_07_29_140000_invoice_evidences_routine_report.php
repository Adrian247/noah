<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_evidences', function (Blueprint $table) {
            $table->foreignId('generated_report_id')
                ->nullable()
                ->after('kind')
                ->constrained('generated_reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_report_id');
        });
    }
};
