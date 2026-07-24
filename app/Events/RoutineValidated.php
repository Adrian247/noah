<?php

namespace App\Events;

use App\Models\Routine;
use App\Models\RoutineExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoutineValidated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Routine $routine,
        public RoutineExecution $execution,
    ) {}
}
