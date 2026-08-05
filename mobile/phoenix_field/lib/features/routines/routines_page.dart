import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/shared/routine/routine_context.dart';
import 'package:phoenix_field/shared/routine/routine_schedule_filter.dart';
import 'package:phoenix_field/shared/routine/routine_status_labels.dart';

enum _StatusFilter { all, actionable, waiting }

class RoutinesPage extends ConsumerStatefulWidget {
  const RoutinesPage({super.key});

  @override
  ConsumerState<RoutinesPage> createState() => _RoutinesPageState();
}

class _RoutinesPageState extends ConsumerState<RoutinesPage> {
  bool _syncing = false;
  String? _syncError;
  bool _todayOnly = true;
  _StatusFilter _statusFilter = _StatusFilter.actionable;

  Future<void> _refresh() async {
    setState(() {
      _syncing = true;
      _syncError = null;
    });
    try {
      await ref.read(syncRepositoryProvider).syncNow();
    } catch (e) {
      setState(() => _syncError = _friendlySyncError(e));
    } finally {
      if (mounted) {
        setState(() => _syncing = false);
      }
    }
  }

  String _friendlySyncError(Object error) {
    if (error is DioException) {
      final status = error.response?.statusCode;
      final data = error.response?.data;
      String? serverMessage;
      if (data is Map && data['message'] != null) {
        serverMessage = data['message'].toString();
      }
      if (status == 401) {
        return 'Sesión expirada. Cierra sesión e inicia de nuevo.';
      }
      if (status == 403) {
        return serverMessage ??
            'Sin permiso para sincronizar o descargar evidencias. Revisa empresa/rol.';
      }
      if (status == 422) {
        return serverMessage ?? 'Datos de sync inválidos.';
      }
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.receiveTimeout) {
        return 'Sin conexión con el servidor. Comprueba la URL API y la red.';
      }
      return serverMessage ?? 'Error de sync (${status ?? error.type.name}).';
    }
    return error.toString();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  bool _matchesStatusFilter(String status) {
    switch (_statusFilter) {
      case _StatusFilter.all:
        return true;
      case _StatusFilter.actionable:
        return status == 'assigned' || status == 'rejected';
      case _StatusFilter.waiting:
        return status == 'pending_sync' ||
            status == 'submitted' ||
            status == 'pending_validation' ||
            status == 'pending_upload';
    }
  }

  String? _rejectionReason(Map<String, dynamic> payload) {
    final execution = payload['latest_execution'];
    if (execution is Map) {
      final reason = execution['rejection_reason']?.toString().trim();
      if (reason != null && reason.isNotEmpty) {
        return reason;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final routinesStream = ref.watch(syncRepositoryProvider).watchRoutines();

    return Scaffold(
      appBar: AppBar(
        title: Text(_todayOnly ? 'Servicios de hoy' : 'Todos los servicios'),
        actions: [
          IconButton(
            onPressed: _syncing ? null : _refresh,
            icon: _syncing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
          ),
        ],
      ),
      body: StreamBuilder<List<LocalRoutine>>(
        stream: routinesStream,
        builder: (context, snapshot) {
          final allRoutines = snapshot.data ?? [];
          final routines = allRoutines.where((routine) {
            if (_todayOnly) {
              final payload = _decodePayload(routine.payloadJson);
              if (!RoutineScheduleFilter.isScheduledToday(payload, DateTime.now())) {
                return false;
              }
            }
            return _matchesStatusFilter(routine.status);
          }).toList();

          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                SegmentedButton<bool>(
                  segments: const [
                    ButtonSegment(value: true, label: Text('Hoy')),
                    ButtonSegment(value: false, label: Text('Todas')),
                  ],
                  selected: {_todayOnly},
                  onSelectionChanged: (selection) {
                    setState(() => _todayOnly = selection.first);
                  },
                ),
                const SizedBox(height: 10),
                SegmentedButton<_StatusFilter>(
                  segments: const [
                    ButtonSegment(
                      value: _StatusFilter.actionable,
                      label: Text('Por hacer'),
                      icon: Icon(Icons.play_arrow, size: 16),
                    ),
                    ButtonSegment(
                      value: _StatusFilter.waiting,
                      label: Text('En curso'),
                      icon: Icon(Icons.hourglass_top, size: 16),
                    ),
                    ButtonSegment(
                      value: _StatusFilter.all,
                      label: Text('Todos'),
                    ),
                  ],
                  selected: {_statusFilter},
                  onSelectionChanged: (selection) {
                    setState(() => _statusFilter = selection.first);
                  },
                ),
                const SizedBox(height: 12),
                if (_syncError != null)
                  Card(
                    color: Colors.red.withValues(alpha: 0.15),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text(_syncError!, style: const TextStyle(color: Colors.redAccent)),
                    ),
                  ),
                if (routines.isEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 48),
                    child: Center(
                      child: Text(
                        _emptyMessage(),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
                ...routines.map((routine) {
                  final payload = _decodePayload(routine.payloadJson);
                  final ctx = RoutineContext.fromPayload(payload, fallbackId: routine.id);
                  final serverStatus = routine.status;
                  final scheduled = payload['scheduled_at']?.toString();
                  final statusColor = routineStatusColor(serverStatus);
                  final rejection = _rejectionReason(payload);
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () => context.push('/routines/${routine.id}'),
                      child: IntrinsicHeight(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Container(width: 6, color: statusColor),
                            Expanded(
                              child: Padding(
                                padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            ctx.title,
                                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                                  fontWeight: FontWeight.w700,
                                                ),
                                          ),
                                        ),
                                        const Icon(Icons.chevron_right, size: 20),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    Wrap(
                                      spacing: 8,
                                      runSpacing: 6,
                                      crossAxisAlignment: WrapCrossAlignment.center,
                                      children: [
                                        RoutineStatusChip(status: serverStatus, compact: true),
                                        _SyncBadge(status: routine.localSyncStatus),
                                      ],
                                    ),
                                    if (ctx.listSubtitles.isNotEmpty) ...[
                                      const SizedBox(height: 8),
                                      ...ctx.listSubtitles.map(
                                        (line) => Padding(
                                          padding: const EdgeInsets.only(bottom: 3),
                                          child: Row(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Icon(
                                                line.startsWith('Sitio')
                                                    ? Icons.place_outlined
                                                    : line.startsWith('Ubicación')
                                                        ? Icons.my_location_outlined
                                                        : line.startsWith('Activo') ||
                                                                line.startsWith('Artículo')
                                                            ? Icons.inventory_2_outlined
                                                            : line.startsWith('Cliente')
                                                                ? Icons.business_outlined
                                                                : Icons.circle,
                                                size: 14,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .onSurface
                                                    .withValues(alpha: 0.55),
                                              ),
                                              const SizedBox(width: 6),
                                              Expanded(
                                                child: Text(
                                                  line,
                                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                                        color: Theme.of(context)
                                                            .colorScheme
                                                            .onSurface
                                                            .withValues(alpha: 0.78),
                                                      ),
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ],
                                    const SizedBox(height: 6),
                                    Text(
                                      rejection != null
                                          ? 'Rechazada antes: revisa el motivo en el detalle'
                                          : routineStatusHint(serverStatus),
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                            color: rejection != null
                                                ? const Color(0xFFF87171)
                                                : Theme.of(context)
                                                    .colorScheme
                                                    .onSurface
                                                    .withValues(alpha: 0.72),
                                          ),
                                    ),
                                    if (scheduled != null && scheduled.isNotEmpty) ...[
                                      const SizedBox(height: 4),
                                      Text(
                                        'Programada: ${_formatScheduled(scheduled)}',
                                        style: Theme.of(context).textTheme.labelMedium,
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }

  String _emptyMessage() {
    if (_todayOnly && _statusFilter == _StatusFilter.actionable) {
      return 'No hay servicios por hacer hoy. Prueba «En curso», «Todos» o sincroniza.';
    }
    if (_todayOnly) {
      return 'No hay servicios en este filtro para hoy.';
    }
    return 'No hay servicios en este filtro. Desliza para sincronizar.';
  }

  Map<String, dynamic> _decodePayload(String payloadJson) {
    try {
      return jsonDecode(payloadJson) as Map<String, dynamic>;
    } catch (_) {
      return {};
    }
  }

  String _formatScheduled(String iso) {
    final parsed = DateTime.tryParse(iso);
    if (parsed == null) {
      return iso;
    }
    final local = parsed.toLocal();
    final two = (int n) => n.toString().padLeft(2, '0');
    return '${two(local.day)}/${two(local.month)}/${local.year} ${two(local.hour)}:${two(local.minute)}';
  }
}

class _SyncBadge extends StatelessWidget {
  const _SyncBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    late Color color;
    late String label;

    switch (status) {
      case 'synced':
        color = Colors.greenAccent;
        label = localSyncStatusLabel(status);
      case 'pending_upload':
        color = Colors.amber;
        label = localSyncStatusLabel(status);
      case 'sync_error':
        color = Colors.redAccent;
        label = localSyncStatusLabel(status);
      default:
        color = Colors.blueGrey;
        label = localSyncStatusLabel(status);
    }

    return Container(
      margin: const EdgeInsets.only(right: 4),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.5)),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 11)),
    );
  }
}
