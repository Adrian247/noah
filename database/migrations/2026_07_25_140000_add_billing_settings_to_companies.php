<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('billing_labor_rate_per_hour', 12, 2)->nullable()->after('timezone');
            $table->decimal('billing_tax_rate', 5, 4)->nullable()->after('billing_labor_rate_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['billing_labor_rate_per_hour', 'billing_tax_rate']);
        });
    }
};
