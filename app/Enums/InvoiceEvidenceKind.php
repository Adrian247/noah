<?php

namespace App\Enums;

enum InvoiceEvidenceKind: string
{
    /** Documentos o imágenes de respaldo (varios por prefactura). */
    case Supporting = 'supporting';

    /** CFDI / factura timbrada SAT (un solo archivo activo por prefactura). */
    case SatCfdi = 'sat_cfdi';

    /** PDF del reporte de inspección generado para la rutina vinculada (referencia, sin copia duplicada). */
    case RoutineReport = 'routine_report';
}
