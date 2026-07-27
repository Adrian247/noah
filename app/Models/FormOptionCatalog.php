<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class FormOptionCatalog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }
}
