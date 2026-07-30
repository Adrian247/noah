<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('mobile_require_app_lock')->default(false)->after('form_allowed_image_mimes');
            $table->boolean('mobile_allow_biometric_unlock')->default(true)->after('mobile_require_app_lock');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['mobile_require_app_lock', 'mobile_allow_biometric_unlock']);
        });
    }
};
