<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\User;

trait UsesMeinCompany
{
    protected function meinCompany(): Company
    {
        return Company::query()->where('name', 'Mein Company')->firstOrFail();
    }

    protected function meinUser(string $email): User
    {
        $company = $this->meinCompany();

        return User::query()
            ->where('email', $email)
            ->whereHas('memberships', fn ($q) => $q->where('company_id', $company->id)->where('is_active', true))
            ->firstOrFail();
    }
}
