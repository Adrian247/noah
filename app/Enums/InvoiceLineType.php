<?php

namespace App\Enums;

enum InvoiceLineType: string
{
    case Supply = 'supply';
    case Labor = 'labor';
    case Other = 'other';
}
