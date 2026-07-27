<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug', 'description'];

    public function versions(): HasMany
    {
        return $this->hasMany(ReportTemplateVersion::class);
    }
}
