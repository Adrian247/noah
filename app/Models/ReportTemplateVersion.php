<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplateVersion extends Model
{
    protected $fillable = [
        'report_template_id',
        'version',
        'status',
        'components',
        'page_settings',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'page_settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }
}
