<?php

namespace App\Services\Billing;

class FiscalResult
{
    public function __construct(
        public bool $success,
        public ?string $uuid = null,
        public ?string $series = null,
        public ?string $folio = null,
        public ?string $xmlContents = null,
        public ?string $error = null,
    ) {}
}
