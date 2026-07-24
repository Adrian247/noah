<?php

namespace App\Support;

use App\Models\Company;

class CurrentCompany
{
    public function __construct(public ?Company $company = null) {}

    public function id(): ?int
    {
        return $this->company?->id;
    }
}
