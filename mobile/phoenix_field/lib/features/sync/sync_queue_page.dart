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

  Future<void> _discard(OutboxEvent event) async {
    await ref.read(syncRepositoryProvider).discardOutboxEvent(event.eventId);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Evento descartado. Corrige consumos e intenta enviar de nuevo.')),
      );
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
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      event.eventType,
                                      style: const TextStyle(fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                  _statusChip(event.status),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${event.eventId}\n${dateFormat.format(event.createdAt)}',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              if (event.errorMessage != null && event.errorMessage!.trim().isNotEmpty) ...[
                                const SizedBox(height: 8),
                                Text(
                                  event.errorMessage!,
                                  style: TextStyle(
                                    color: Theme.of(context).colorScheme.error,
                                    fontSize: 13,
                                  ),
                                ),
                              ],
                              if (event.status == 'error') ...[
                                const SizedBox(height: 8),
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: TextButton(
                                    onPressed: _syncing ? null : () => _discard(event),
                                    child: const Text('Descartar'),
                                  ),
                                ),
                              ],
                            ],
                          ),
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
                          title: Text('${row.fieldKey} · servicio #${row.routineId}'),
                          subtitle: Text(
                            [
                              row.localPath,
                              if (row.errorMessage != null && row.errorMessage!.trim().isNotEmpty)
                                row.errorMessage!,
                            ].join('\n'),
                          ),
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
