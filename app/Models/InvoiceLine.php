<?php

namespace App\Models;

use App\Enums\InvoiceLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'line_type',
        'sort_order',
        'source_routine_consumption_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'line_type' => InvoiceLineType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sourceConsumption(): BelongsTo
    {
        return $this->belongsTo(RoutineConsumption::class, 'source_routine_consumption_id');
    }
}
