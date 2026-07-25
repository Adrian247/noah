<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\DefaultTeamResolver;

class CompanyTeamResolver extends DefaultTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        if (app()->bound(CurrentCompany::class)) {
            $id = app(CurrentCompany::class)->id();
            if ($id !== null) {
                return $id;
            }
        }

        return parent::getPermissionsTeamId();
    }

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }
        parent::setPermissionsTeamId($id);
    }
}
