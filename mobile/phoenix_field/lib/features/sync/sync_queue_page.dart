import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';

class SyncQueuePage extends ConsumerStatefulWidget {
  const SyncQueuePage({super.key});

  @override
  ConsumerState<SyncQueuePage> createState() => _SyncQueuePageState();
}

class _SyncQueuePageState extends ConsumerState<SyncQueuePage> {
  bool _syncing = false;

  Future<void> _sync() async {
    setState(() => _syncing = true);
    try {
      final result = await ref.read(syncRepositoryProvider).syncNow();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Sync: ${result.accepted} aceptados, ${result.rejected} rechazados, '
              '${result.mediaPending} foto(s) pendientes',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error de sync: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _syncing = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = ref.watch(syncRepositoryProvider);
    final outboxStream = repo.watchOutbox();
    final mediaStream = repo.watchPendingMedia();
    final dateFormat = DateFormat('dd/MM HH:mm');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Cola de sync'),
        actions: [
          TextButton(
            onPressed: _syncing ? null : _sync,
            child: _syncing ? const Text('...') : const Text('Sincronizar'),
          ),
        ],
      ),
      body: StreamBuilder<List<OutboxEvent>>(
        stream: outboxStream,
        builder: (context, outboxSnap) {
          final events = outboxSnap.data ?? [];

          return StreamBuilder<List<PendingMediaData>>(
            stream: mediaStream,
            builder: (context, mediaSnap) {
              final media = mediaSnap.data ?? [];

              if (events.isEmpty && media.isEmpty) {
                return const Center(child: Text('No hay eventos ni fotos en cola'));
              }

              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (events.isNotEmpty) ...[
                    Text('Eventos', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    ...events.map(
                      (event) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          title: Text(event.eventType),
                          subtitle: Text(
                            '${event.eventId}\n${dateFormat.format(event.createdAt)}',
                          ),
                          trailing: _statusChip(event.status),
                        ),
                      ),
                    ),
                  ],
                  if (media.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Text('Fotos / firmas', style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    ...media.map(
                      (row) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          title: Text('${row.fieldKey} · rutina #${row.routineId}'),
                          subtitle: Text(row.localPath),
                          trailing: _statusChip(row.status),
                        ),
                      ),
                    ),
                  ],
                ],
              );
            },
          );
        },
      ),
    );
  }

  Widget _statusChip(String status) {
    Color color;
    switch (status) {
      case 'synced':
      case 'uploaded':
        color = Colors.greenAccent;
      case 'error':
        color = Colors.redAccent;
      default:
        color = Colors.amber;
    }
    return Chip(
      label: Text(status, style: const TextStyle(fontSize: 11)),
      backgroundColor: color.withValues(alpha: 0.2),
    );
  }
}
