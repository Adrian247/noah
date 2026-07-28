<?php

namespace App\Models;

use App\Enums\FormUsage;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormDefinition extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug', 'usage'];

    protected function casts(): array
    {
        return [
            'usage' => FormUsage::class,
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }
}
