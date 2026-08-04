<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogImportLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'source_catalog_item_id',
        'result_catalog_item_id',
        'action',
        'generation',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'meta' => 'array',
        ];
    }

    public function sourceCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'source_catalog_item_id');
    }

    public function resultCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'result_catalog_item_id');
    }
}
