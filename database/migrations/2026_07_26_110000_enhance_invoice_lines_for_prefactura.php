<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('line_type', 32)->default('other')->after('invoice_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('line_type');
            $table->foreignId('source_routine_consumption_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('routine_consumptions')
                ->nullOnDelete();
            $table->json('metadata')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_routine_consumption_id');
            $table->dropColumn(['line_type', 'sort_order', 'metadata']);
        });
    }
};
