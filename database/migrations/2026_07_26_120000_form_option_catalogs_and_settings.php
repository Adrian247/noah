<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_option_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('options');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('form_max_image_size_kb')->default(2048)->after('billing_tax_rate');
            $table->json('form_allowed_image_mimes')->nullable()->after('form_max_image_size_kb');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['form_max_image_size_kb', 'form_allowed_image_mimes']);
        });

        Schema::dropIfExists('form_option_catalogs');
    }
};
