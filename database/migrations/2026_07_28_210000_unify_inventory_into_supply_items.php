<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_items', function (Blueprint $table) {
            if (! Schema::hasColumn('supply_items', 'sector')) {
                $table->string('sector', 32)->default('mechanical')->after('name');
            }
            if (! Schema::hasColumn('supply_items', 'material_kind')) {
                $table->string('material_kind', 32)->default('consumable')->after('sector');
            }
            if (! Schema::hasColumn('supply_items', 'quantity_on_hand')) {
                $table->decimal('quantity_on_hand', 14, 4)->default(0)->after('standard_cost');
            }
            if (! Schema::hasColumn('supply_items', 'min_stock')) {
                $table->decimal('min_stock', 14, 4)->nullable()->after('quantity_on_hand');
            }
            if (! Schema::hasColumn('supply_items', 'storage_location')) {
                $table->string('storage_location')->nullable()->after('min_stock');
            }
            if (! Schema::hasColumn('supply_items', 'notes')) {
                $table->text('notes')->nullable()->after('storage_location');
            }
            if (! Schema::hasColumn('supply_items', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }
        });

        if (Schema::hasTable('inventory_movements') && ! Schema::hasColumn('inventory_movements', 'supply_item_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('supply_item_id')->nullable()->after('company_id');
            });
        }

        if (Schema::hasTable('inventory_materials')) {
            foreach (DB::table('inventory_materials')->get() as $row) {
                $supplyId = $row->supply_item_id;
                if ($supplyId === null) {
                    $supplyId = DB::table('supply_items')->insertGetId([
                        'company_id' => $row->company_id,
                        'sku' => $row->code,
                        'name' => $row->name,
                        'sector' => $row->sector,
                        'material_kind' => $row->material_kind,
                        'unit' => $row->unit,
                        'standard_cost' => 0,
                        'quantity_on_hand' => $row->quantity_on_hand,
                        'min_stock' => $row->min_stock,
                        'storage_location' => $row->storage_location,
                        'notes' => $row->notes,
                        'is_active' => $row->is_active,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                } else {
                    DB::table('supply_items')->where('id', $supplyId)->update([
                        'sector' => $row->sector,
                        'material_kind' => $row->material_kind,
                        'quantity_on_hand' => $row->quantity_on_hand,
                        'min_stock' => $row->min_stock,
                        'storage_location' => $row->storage_location,
                        'notes' => $row->notes,
                        'is_active' => $row->is_active,
                    ]);
                }

                if (Schema::hasColumn('inventory_movements', 'inventory_material_id')) {
                    DB::table('inventory_movements')
                        ->where('inventory_material_id', $row->id)
                        ->update(['supply_item_id' => $supplyId]);
                }
            }
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'inventory_material_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropForeign(['inventory_material_id']);
                $table->dropColumn('inventory_material_id');
            });
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'supply_item_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->foreign('supply_item_id')->references('id')->on('supply_items')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('inventory_movements') && ! Schema::hasColumn('inventory_movements', 'routine_execution_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->foreignId('routine_execution_id')->nullable()->after('routine_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('routine_consumptions')) {
            Schema::table('routine_consumptions', function (Blueprint $table) {
                if (! Schema::hasColumn('routine_consumptions', 'usage_type')) {
                    $table->string('usage_type', 32)->default('out')->after('supply_item_id');
                }
            });
            if (! Schema::hasColumn('routine_consumptions', 'inventory_movement_id')) {
                Schema::table('routine_consumptions', function (Blueprint $table) {
                    $table->foreignId('inventory_movement_id')->nullable()->after('usage_type')->constrained('inventory_movements')->nullOnDelete();
                });
            }
        }

        Schema::dropIfExists('inventory_materials');
    }

    public function down(): void
    {
        if (Schema::hasTable('routine_consumptions')) {
            Schema::table('routine_consumptions', function (Blueprint $table) {
                if (Schema::hasColumn('routine_consumptions', 'inventory_movement_id')) {
                    $table->dropConstrainedForeignId('inventory_movement_id');
                }
                if (Schema::hasColumn('routine_consumptions', 'usage_type')) {
                    $table->dropColumn('usage_type');
                }
            });
        }

        Schema::table('supply_items', function (Blueprint $table) {
            foreach (['sector', 'material_kind', 'quantity_on_hand', 'min_stock', 'storage_location', 'notes', 'is_active'] as $col) {
                if (Schema::hasColumn('supply_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
