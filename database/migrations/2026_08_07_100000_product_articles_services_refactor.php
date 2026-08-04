<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_types', function (Blueprint $table) {
            $table->string('service_category', 32)->default('maintenance')->after('slug');
        });

        DB::table('routine_types')->orderBy('id')->each(function ($row) {
            $category = match ($row->service_line ?? 'maintenance') {
                'fabrication', 'supply' => 'manufacturing',
                default => 'maintenance',
            };
            DB::table('routine_types')->where('id', $row->id)->update(['service_category' => $category]);
        });

        Schema::table('routine_types', function (Blueprint $table) {
            $table->dropIndex(['service_line']);
            $table->dropColumn('service_line');
        });

        DB::table('form_definitions')->where('usage', 'routine')->update(['usage' => 'service']);
        DB::table('form_definitions')->where('usage', 'equipment')->update(['usage' => 'article']);
        DB::table('form_definitions')->where('usage', 'supply')->update(['usage' => 'inventory']);

        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('is_system_template')->default(false)->after('company_id');
            $table->foreignId('source_system_catalog_item_id')->nullable()->after('is_system_template')
                ->constrained('catalog_items')->nullOnDelete();
            $table->unsignedSmallInteger('import_generation')->default(0)->after('source_system_catalog_item_id');
            $table->string('image_path')->nullable()->after('name');
            $table->boolean('is_detached_copy')->default(false)->after('image_path');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('image_path')->nullable()->after('location_label');
            $table->string('sync_mode', 16)->default('linked')->after('image_path');
            $table->foreignId('base_catalog_item_id')->nullable()->after('catalog_item_id')
                ->constrained('catalog_items')->nullOnDelete();
            $table->foreignId('source_system_catalog_item_id')->nullable()->after('base_catalog_item_id')
                ->constrained('catalog_items')->nullOnDelete();
            $table->unsignedSmallInteger('import_generation')->default(0)->after('source_system_catalog_item_id');
            $table->timestamp('detached_at')->nullable()->after('import_generation');
            $table->string('ocr_plate_text')->nullable()->after('detached_at');
        });

        Schema::create('catalog_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignId('result_catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('action', 32);
            $table->unsignedSmallInteger('generation')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_logs');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn([
                'image_path', 'sync_mode', 'base_catalog_item_id',
                'source_system_catalog_item_id', 'import_generation', 'detached_at', 'ocr_plate_text',
            ]);
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_system_catalog_item_id');
            $table->dropColumn(['is_system_template', 'import_generation', 'image_path', 'is_detached_copy']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        DB::table('form_definitions')->where('usage', 'service')->update(['usage' => 'routine']);
        DB::table('form_definitions')->where('usage', 'article')->update(['usage' => 'equipment']);
        DB::table('form_definitions')->where('usage', 'inventory')->update(['usage' => 'supply']);

        Schema::table('routine_types', function (Blueprint $table) {
            $table->string('service_line', 32)->default('maintenance')->after('slug');
        });

        DB::table('routine_types')->update([
            'service_line' => DB::raw("CASE service_category WHEN 'manufacturing' THEN 'fabrication' ELSE 'maintenance' END"),
        ]);

        Schema::table('routine_types', function (Blueprint $table) {
            $table->dropColumn('service_category');
            $table->index('service_line');
        });
    }
};
