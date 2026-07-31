<?php

namespace App\Listeners;

use App\Events\RoutineValidated;
use App\Services\Integrations\OperationalEventBridge;

class DispatchRoutineValidatedIntegrations
{
    public function __construct(private OperationalEventBridge $bridge) {}

    public function handle(RoutineValidated $event): void
    {
        $this->bridge->routineValidated($event->routine->loadMissing('asset'));
    }
}
