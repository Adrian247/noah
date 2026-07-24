<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SupplyItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'sku', 'name', 'unit', 'standard_cost'];

    protected function casts(): array
    {
        return [
            'standard_cost' => 'decimal:4',
        ];
    }
}
