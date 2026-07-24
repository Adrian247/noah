<?php

namespace App\Events;

use App\Models\Routine;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExecutionSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Routine $routine) {}
}
