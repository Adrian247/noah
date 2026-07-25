<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('routine_id')->constrained()->nullOnDelete();
            $table->decimal('tax_rate_snapshot', 5, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('tax_rate_snapshot');
        });

        Schema::dropIfExists('clients');
    }
};
