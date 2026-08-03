<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 64);
            $table->string('platform', 16);
            $table->string('token', 512);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->unique('token');
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
