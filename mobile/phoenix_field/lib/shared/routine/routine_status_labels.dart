import 'package:flutter/material.dart';

String routineStatusLabel(String status) {
  switch (status) {
    case 'assigned':
      return 'Asignada';
    case 'in_progress':
      return 'En progreso';
    case 'submitted':
      return 'Enviada';
    case 'pending_validation':
      return 'Pendiente validación';
    case 'validated':
      return 'Validada';
    case 'pending_billing':
      return 'Pendiente facturación';
    case 'invoiced':
      return 'Facturada';
    case 'rejected':
      return 'Rechazada';
    case 'draft':
      return 'Borrador';
    case 'pending_sync':
      return 'Pendiente sync';
    default:
      return status.replaceAll('_', ' ');
  }
}

/// Texto corto para orientar al técnico según el estado.
String routineStatusHint(String status) {
  switch (status) {
    case 'assigned':
      return 'Lista para ejecutar en campo';
    case 'in_progress':
      return 'Ejecución en curso';
    case 'submitted':
    case 'pending_sync':
      return 'Enviada; esperando sync o revisión';
    case 'pending_validation':
      return 'En revisión del supervisor';
    case 'validated':
      return 'Aprobada por supervisor';
    case 'pending_billing':
      return 'Lista para facturar';
    case 'invoiced':
      return 'Cerrada y facturada';
    case 'rejected':
      return 'Rechazada; revisa el motivo y corrige';
    case 'draft':
      return 'Borrador sin asignar';
    default:
      return 'Estado del flujo del servicio';
  }
}

Color routineStatusColor(String status) {
  switch (status) {
    case 'assigned':
      return const Color(0xFF38BDF8); // sky
    case 'in_progress':
      return const Color(0xFFFBBF24); // amber
    case 'submitted':
    case 'pending_sync':
      return const Color(0xFFA78BFA); // violet
    case 'pending_validation':
      return const Color(0xFFF59E0B); // orange
    case 'validated':
      return const Color(0xFF34D399); // emerald
    case 'pending_billing':
      return const Color(0xFF2DD4BF); // teal
    case 'invoiced':
      return const Color(0xFF94A3B8); // slate
    case 'rejected':
      return const Color(0xFFF87171); // red
    case 'draft':
      return const Color(0xFF94A3B8);
    default:
      return const Color(0xFF64748B);
  }
}

IconData routineStatusIcon(String status) {
  switch (status) {
    case 'assigned':
      return Icons.assignment_turned_in_outlined;
    case 'in_progress':
      return Icons.play_circle_outline;
    case 'submitted':
    case 'pending_sync':
      return Icons.cloud_upload_outlined;
    case 'pending_validation':
      return Icons.hourglass_top_outlined;
    case 'validated':
      return Icons.verified_outlined;
    case 'pending_billing':
      return Icons.receipt_long_outlined;
    case 'invoiced':
      return Icons.check_circle_outline;
    case 'rejected':
      return Icons.highlight_off_outlined;
    case 'draft':
      return Icons.edit_note_outlined;
    default:
      return Icons.info_outline;
  }
}

bool routineCanSubmitFromField(String status) =>
    status == 'assigned' || status == 'rejected';

String? routineRejectionReason(Map<String, dynamic>? payload) {
  if (payload == null) {
    return null;
  }
  final execution = payload['latest_execution'];
  if (execution is Map) {
    final reason = execution['rejection_reason']?.toString().trim();
    if (reason != null && reason.isNotEmpty) {
      return reason;
    }
  }
  return null;
}

String localSyncStatusLabel(String status) {
  switch (status) {
    case 'synced':
      return 'Sync: enviado';
    case 'pending_upload':
      return 'Sync: pendiente';
    case 'sync_error':
      return 'Sync: error';
    default:
      return 'Sync: $status';
  }
}

/// Chip compacto para listas.
class RoutineStatusChip extends StatelessWidget {
  const RoutineStatusChip({
    super.key,
    required this.status,
    this.compact = false,
  });

  final String status;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final color = routineStatusColor(status);
    final label = routineStatusLabel(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 8 : 10,
        vertical: compact ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.55)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(routineStatusIcon(status), size: compact ? 14 : 16, color: color),
          SizedBox(width: compact ? 4 : 6),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: compact ? 11 : 12,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.2,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Banner destacado para el detalle del servicio.
class RoutineStatusBanner extends StatelessWidget {
  const RoutineStatusBanner({
    super.key,
    required this.status,
    this.localSyncStatus,
    this.rejectionReason,
  });

  final String status;
  final String? localSyncStatus;
  final String? rejectionReason;

  @override
  Widget build(BuildContext context) {
    final color = routineStatusColor(status);
    final theme = Theme.of(context);
    final reason = rejectionReason?.trim();
    final showRejection = reason != null && reason.isNotEmpty;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            color.withValues(alpha: 0.28),
            color.withValues(alpha: 0.10),
          ],
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.55), width: 1.4),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.22),
                  shape: BoxShape.circle,
                  border: Border.all(color: color.withValues(alpha: 0.6)),
                ),
                child: Icon(routineStatusIcon(status), color: color, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'ESTADO',
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: color,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      routineStatusLabel(status),
                      style: theme.textTheme.titleLarge?.copyWith(
                        color: color,
                        fontWeight: FontWeight.w800,
                        height: 1.15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      showRejection
                          ? 'Corregir según el motivo del supervisor y reenviar'
                          : routineStatusHint(status),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.85),
                      ),
                    ),
                    if (localSyncStatus != null && localSyncStatus!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        localSyncStatusLabel(localSyncStatus!),
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (showRejection) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF87171).withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: const Color(0xFFF87171).withValues(alpha: 0.45),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Motivo de rechazo',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: const Color(0xFFF87171),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(reason, style: theme.textTheme.bodyMedium),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
