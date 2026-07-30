/// Filtra rutinas por fecha programada (`scheduled_at` en payload del servidor).
class RoutineScheduleFilter {
  const RoutineScheduleFilter._();

  static bool isScheduledToday(Map<String, dynamic> routine, DateTime reference) {
    final raw = routine['scheduled_at']?.toString();
    if (raw == null || raw.trim().isEmpty) {
      return true;
    }

    final scheduled = DateTime.tryParse(raw);
    if (scheduled == null) {
      return true;
    }

    final local = scheduled.toLocal();
    return local.year == reference.year &&
        local.month == reference.month &&
        local.day == reference.day;
  }
}
