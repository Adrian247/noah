<?php

namespace App\Events;

use App\Models\ReportTemplateVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportTemplateVersionPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReportTemplateVersion $version) {}
}
