<?php

namespace App\Models;

use App\Enums\InvoiceEvidenceKind;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEvidence extends Model
{
    use BelongsToCompany;

    protected $table = 'invoice_evidences';

    protected $fillable = [
        'company_id',
        'invoice_id',
        'kind',
        'generated_report_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceEvidenceKind::class,
            'size_bytes' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class);
    }
}
