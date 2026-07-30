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
