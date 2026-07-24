<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormDefinition extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'slug'];

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }
}
