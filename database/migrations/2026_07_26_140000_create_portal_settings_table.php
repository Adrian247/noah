<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hero_image_url')->nullable();
            $table->string('hero_image_alt')->nullable();
            $table->string('service_title')->nullable();
            $table->text('service_description')->nullable();
            $table->json('service_highlights')->nullable();
            $table->string('help_title')->nullable();
            $table->text('help_text')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_hours')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');
    }
};
