<?php

namespace App\Enums;

enum WorkflowTemplate: string
{
    case StandardBilling = 'standard_billing';
    case ClassicNoBilling = 'classic_no_billing';
    case ValidationOnly = 'validation_only';
    case DualReview = 'dual_review';

    public function label(): string
    {
        return match ($this) {
            self::StandardBilling => 'Operación estándar (con facturación)',
            self::ClassicNoBilling => 'Clásico (validación y cierre, sin paso facturación)',
            self::ValidationOnly => 'Solo validación (sin PDF ni borrador automático)',
            self::DualReview => 'Doble revisión (supervisor + jefe)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::StandardBilling => 'Campo → supervisor → facturación → cierre. PDF y prefactura al aprobar.',
            self::ClassicNoBilling => 'Campo → supervisor → cierre. PDF y prefactura al aprobar; factura fuera del grafo.',
            self::ValidationOnly => 'Campo → supervisor → cierre sin acciones automáticas.',
            self::DualReview => 'Campo → supervisor → jefe de taller → facturación (opcional) → cierre.',
        };
    }

    /**
     * @return array<string, bool>
     */
    public function defaultOptions(): array
    {
        return match ($this) {
            self::StandardBilling => [
                'include_billing' => true,
                'routine_validated_on_approve' => true,
                'dual_review' => false,
                'include_email_step' => false,
            ],
            self::ClassicNoBilling => [
                'include_billing' => false,
                'routine_validated_on_approve' => true,
                'dual_review' => false,
                'include_email_step' => false,
            ],
            self::ValidationOnly => [
                'include_billing' => false,
                'routine_validated_on_approve' => false,
                'dual_review' => false,
                'include_email_step' => false,
            ],
            self::DualReview => [
                'include_billing' => true,
                'routine_validated_on_approve' => true,
                'dual_review' => true,
                'include_email_step' => false,
            ],
        };
    }
}
