<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'contact_email',
        'contact_phone',
    ];

    public function supplyItems(): HasMany
    {
        return $this->hasMany(SupplyItem::class);
    }
}
