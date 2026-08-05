import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';

class SyncStatusBanner extends ConsumerWidget {
  const SyncStatusBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(syncRepositoryProvider);

    return StreamBuilder(
      stream: repo.watchOutbox(),
      builder: (context, outboxSnap) {
        final outbox = outboxSnap.data ?? [];
        final pendingEvents = outbox.where((e) => e.status == 'pending').length;
        final errorEvents = outbox.where((e) => e.status == 'error').length;

        return StreamBuilder(
          stream: repo.watchPendingMedia(),
          builder: (context, mediaSnap) {
            final media = mediaSnap.data ?? [];
            final pendingMedia = media.where((m) => m.status == 'pending').length;

            if (pendingEvents == 0 && errorEvents == 0 && pendingMedia == 0) {
              return const SizedBox.shrink();
            }

            Color bg;
            IconData icon;
            String message;

            if (errorEvents > 0) {
              bg = Colors.red.withValues(alpha: 0.2);
              icon = Icons.error_outline;
              final errorMessages = outbox
                  .where((e) => e.status == 'error' && (e.errorMessage?.trim().isNotEmpty ?? false))
                  .map((e) => e.errorMessage!.trim())
                  .toList();
              message = errorMessages.isNotEmpty
                  ? 'Sync con errores: ${errorMessages.first}'
                  : 'Sync con errores ($errorEvents). Revisa la cola.';
            } else {
              bg = Colors.amber.withValues(alpha: 0.18);
              icon = Icons.cloud_upload_outlined;
              message = 'Pendiente: $pendingEvents evento(s), $pendingMedia foto(s)';
            }

            return Material(
              color: bg,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                child: Row(
                  children: [
                    Icon(icon, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(message, style: const TextStyle(fontSize: 13))),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}
