<?php

namespace App\Models\Concerns;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = app(CurrentCompany::class)->id();
            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('company_id') === null) {
                $companyId = app(CurrentCompany::class)->id();
                if ($companyId !== null) {
                    $model->setAttribute('company_id', $companyId);
                }
            }
        });
    }
}
