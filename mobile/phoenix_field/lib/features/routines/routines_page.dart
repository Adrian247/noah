import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/shared/routine/routine_schedule_filter.dart';
import 'package:phoenix_field/shared/routine/routine_status_labels.dart';

class RoutinesPage extends ConsumerStatefulWidget {
  const RoutinesPage({super.key});

  @override
  ConsumerState<RoutinesPage> createState() => _RoutinesPageState();
}

class _RoutinesPageState extends ConsumerState<RoutinesPage> {
  bool _syncing = false;
  String? _syncError;
  bool _todayOnly = true;

  Future<void> _refresh() async {
    setState(() {
      _syncing = true;
      _syncError = null;
    });
    try {
      await ref.read(syncRepositoryProvider).syncNow();
    } catch (e) {
      setState(() => _syncError = e.toString());
    } finally {
      if (mounted) {
        setState(() => _syncing = false);
      }
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  @override
  Widget build(BuildContext context) {
    final routinesStream = ref.watch(syncRepositoryProvider).watchRoutines();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rutinas de hoy'),
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
          final routines = _todayOnly
              ? allRoutines.where((routine) {
                  final payload = _decodePayload(routine.payloadJson);
                  return RoutineScheduleFilter.isScheduledToday(payload, DateTime.now());
                }).toList()
              : allRoutines;

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
                        _todayOnly
                            ? 'No hay rutinas programadas para hoy. Prueba «Todas» o sincroniza.'
                            : 'No hay rutinas asignadas. Desliza para sincronizar.',
                      ),
                    ),
                  ),
                ...routines.map((routine) {
                  final payload = _decodePayload(routine.payloadJson);
                  final serverStatus = routine.status;
                  final scheduled = payload['scheduled_at']?.toString();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(_routineTitle(payload, routine.id)),
                      subtitle: Text(
                        [
                          'Rutina: ${routineStatusLabel(serverStatus)}',
                          if (scheduled != null && scheduled.isNotEmpty)
                            'Programada: ${_formatScheduled(scheduled)}',
                        ].join('\n'),
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _SyncBadge(status: routine.localSyncStatus),
                          const Icon(Icons.chevron_right),
                        ],
                      ),
                      onTap: () => context.push('/routines/${routine.id}'),
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

  Map<String, dynamic> _decodePayload(String payloadJson) {
    try {
      return jsonDecode(payloadJson) as Map<String, dynamic>;
    } catch (_) {
      return {};
    }
  }

  String _routineTitle(Map<String, dynamic> map, int fallbackId) {
    final type = map['routine_type']?['name']?.toString();
    final asset = map['asset']?['tag']?.toString();
    if (type != null && asset != null) {
      return '$type — $asset';
    }
    return 'Rutina #${map['id'] ?? fallbackId}';
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
