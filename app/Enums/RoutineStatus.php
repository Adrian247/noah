<?php

namespace App\Enums;

enum RoutineStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case PendingSync = 'pending_sync';
    case Submitted = 'submitted';
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Invoiced = 'invoiced';
}
