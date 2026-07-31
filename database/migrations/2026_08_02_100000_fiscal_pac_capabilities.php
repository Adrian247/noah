<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('fiscal_enabled')->default(false)->after('ai_monthly_vision_quota');
            $table->string('fiscal_provider', 32)->nullable()->after('fiscal_enabled');
            $table->json('fiscal_settings')->nullable()->after('fiscal_provider');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('fiscal_uuid', 64)->nullable()->after('issued_at');
            $table->string('fiscal_series', 16)->nullable()->after('fiscal_uuid');
            $table->string('fiscal_folio', 32)->nullable()->after('fiscal_series');
            $table->timestamp('fiscal_issued_at')->nullable()->after('fiscal_folio');
            $table->text('fiscal_error')->nullable()->after('fiscal_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['fiscal_uuid', 'fiscal_series', 'fiscal_folio', 'fiscal_issued_at', 'fiscal_error']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['fiscal_enabled', 'fiscal_provider', 'fiscal_settings']);
        });
    }
};
