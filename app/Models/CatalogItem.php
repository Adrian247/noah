<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'manufacturer', 'specifications'];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
