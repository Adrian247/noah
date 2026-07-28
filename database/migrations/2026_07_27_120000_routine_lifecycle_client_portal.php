<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->after('routine_id');
            $table->index('correlation_id');
        });

        Schema::table('audit_entries', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable()->after('company_id');
            $table->index(['company_id', 'correlation_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('notify_client_on_issue')->default(false)->after('issued_at');
            $table->boolean('client_portal_visible')->default(false)->after('notify_client_on_issue');
            $table->boolean('delivery_deferred')->default(false)->after('client_portal_visible');
            $table->timestamp('delivered_to_client_at')->nullable()->after('delivery_deferred');
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('routines', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status');
        });

        Schema::create('asset_client_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number', 128);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'client_id', 'unassigned_at']);
            $table->index(['asset_id', 'unassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_client_assignments');

        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'notify_client_on_issue',
                'client_portal_visible',
                'delivery_deferred',
                'delivered_to_client_at',
            ]);
        });

        Schema::table('audit_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'correlation_id']);
            $table->dropColumn('correlation_id');
        });

        Schema::table('workflow_instances', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropColumn('correlation_id');
        });
    }
};
