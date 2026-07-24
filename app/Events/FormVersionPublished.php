<?php

namespace App\Events;

use App\Models\FormVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormVersionPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public FormVersion $version) {}
}
