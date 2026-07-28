<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Administrator = 'administrator';
    case Supervisor = 'supervisor';
    case Technician = 'technician';
    case Billing = 'billing';
    case Auditor = 'auditor';
    case Client = 'client';
}
