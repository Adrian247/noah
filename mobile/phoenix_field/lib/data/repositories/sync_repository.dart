import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/data/api/sync_api.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/local/database_provider.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/data/session/session_store.dart';
import 'package:uuid/uuid.dart';

class SyncRepository {
  SyncRepository({
    required AppDatabase db,
    required SyncApi syncApi,
    required SessionStore session,
    required MediaRepository media,
  })  : _db = db,
        _syncApi = syncApi,
        _session = session,
        _media = media;

  final AppDatabase _db;
  final SyncApi _syncApi;
  final SessionStore _session;
  final MediaRepository _media;
  final _uuid = const Uuid();

  Stream<List<OutboxEvent>> watchOutbox() {
    return (_db.select(_db.outboxEvents)
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .watch();
  }

  Stream<List<LocalRoutine>> watchRoutines() {
    return (_db.select(_db.localRoutines)
          ..orderBy([(t) => OrderingTerm.desc(t.id)]))
        .watch();
  }

  Stream<List<PendingMediaData>> watchPendingMedia() => _media.watchPending();

  Future<LocalRoutine?> getLocalRoutine(int id) async {
    return (_db.select(_db.localRoutines)
          ..where((t) => t.id.equals(id)))
        .getSingleOrNull();
  }

  Future<Map<String, dynamic>?> getRoutine(int id) async {
    final row = await getLocalRoutine(id);
    if (row == null) {
      return null;
    }
    return jsonDecode(row.payloadJson) as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> getOptionCatalogs() async {
    final row = await (_db.select(_db.cachedMeta)
          ..where((t) => t.key.equals('option_catalogs')))
        .getSingleOrNull();
    if (row == null) {
      return [];
    }
    final decoded = jsonDecode(row.payloadJson);
    if (decoded is List) {
      return decoded.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    }
    return [];
  }

  Future<List<Map<String, dynamic>>> getSupplyItems() async {
    final row = await (_db.select(_db.cachedMeta)
          ..where((t) => t.key.equals('supply_items')))
        .getSingleOrNull();
    if (row == null) {
      return [];
    }
    final decoded = jsonDecode(row.payloadJson);
    if (decoded is! List) {
      return [];
    }
    return decoded
        .whereType<Map>()
        .map((e) {
          final map = Map<String, dynamic>.from(e);
          final id = map['id'];
          if (id is num) {
            map['id'] = id.toInt();
          } else if (id is String) {
            final parsed = int.tryParse(id);
            if (parsed != null) {
              map['id'] = parsed;
            }
          }
          return map;
        })
        .where((e) => e['id'] is int)
        .toList();
  }

  Future<ExecutionDraft?> getDraft(int routineId) {
    return (_db.select(_db.executionDrafts)
          ..where((t) => t.routineId.equals(routineId)))
        .getSingleOrNull();
  }

  Future<void> saveDraft({
    required int routineId,
    required Map<String, dynamic> responses,
    String? comments,
    int? durationMinutes,
    String? signatureLocalId,
    List<Map<String, dynamic>> consumptions = const [],
  }) async {
    await _db.into(_db.executionDrafts).insertOnConflictUpdate(
          ExecutionDraftsCompanion(
            routineId: Value(routineId),
            responsesJson: Value(jsonEncode(responses)),
            comments: Value(comments),
            durationMinutes: Value(durationMinutes),
            signatureLocalId: Value(signatureLocalId),
            consumptionsJson: Value(jsonEncode(consumptions)),
          ),
        );
  }

  Future<String> submitExecution({
    required int routineId,
    required Map<String, dynamic> responses,
    String? comments,
    int? durationMinutes,
    String? signatureLocalId,
    List<Map<String, dynamic>> consumptions = const [],
  }) async {
    final stockError = await _validateConsumptionsStock(consumptions);
    if (stockError != null) {
      throw StateError(stockError);
    }

    // Sustituir envíos fallidos/pendientes previos del mismo servicio.
    final previous = await (_db.select(_db.outboxEvents)
          ..where((t) => t.eventType.equals('execution.submitted')))
        .get();
    for (final row in previous) {
      if (row.status == 'synced') {
        continue;
      }
      try {
        final payload = jsonDecode(row.payloadJson);
        if (payload is Map && _routineIdFromPayload(Map<String, dynamic>.from(payload)) == routineId) {
          await (_db.delete(_db.outboxEvents)..where((t) => t.eventId.equals(row.eventId))).go();
        }
      } catch (_) {
        // ignore corrupt payload
      }
    }

    final eventId = 'evt-${_uuid.v4()}';
    final payload = {
      'routine_id': routineId,
      'technician_comments': comments,
      'duration_minutes': durationMinutes,
      'responses': responses,
      'consumptions': consumptions,
    };

    await _db.into(_db.outboxEvents).insert(
          OutboxEventsCompanion.insert(
            eventId: eventId,
            eventType: 'execution.submitted',
            payloadJson: jsonEncode(payload),
            status: 'pending',
            createdAt: DateTime.now(),
          ),
        );

    await (_db.update(_db.localRoutines)..where((t) => t.id.equals(routineId)))
        .write(
          LocalRoutinesCompanion(
            status: const Value('pending_sync'),
            localSyncStatus: const Value('pending_upload'),
          ),
        );

    // Mantener payload coherente para la UI de estado.
    final row = await (_db.select(_db.localRoutines)
          ..where((t) => t.id.equals(routineId)))
        .getSingleOrNull();
    if (row != null) {
      try {
        final payload = Map<String, dynamic>.from(
          jsonDecode(row.payloadJson) as Map,
        );
        payload['status'] = 'pending_sync';
        await (_db.update(_db.localRoutines)..where((t) => t.id.equals(routineId)))
            .write(LocalRoutinesCompanion(payloadJson: Value(jsonEncode(payload))));
      } catch (_) {
        // Si el payload está corrupto, el status de columna ya basta.
      }
    }

    await saveDraft(
      routineId: routineId,
      responses: responses,
      comments: comments,
      durationMinutes: durationMinutes,
      signatureLocalId: signatureLocalId,
      consumptions: consumptions,
    );

    return eventId;
  }

  Future<String?> _validateConsumptionsStock(
    List<Map<String, dynamic>> consumptions,
  ) async {
    if (consumptions.isEmpty) {
      return null;
    }
    final supplies = await getSupplyItems();
    final byId = <int, Map<String, dynamic>>{};
    for (final item in supplies) {
      final id = item['id'];
      final parsed = id is int ? id : (id is num ? id.toInt() : int.tryParse('$id'));
      if (parsed != null) {
        byId[parsed] = item;
      }
    }

    for (final line in consumptions) {
      final supplyIdRaw = line['supply_item_id'];
      final supplyId = supplyIdRaw is int
          ? supplyIdRaw
          : (supplyIdRaw is num ? supplyIdRaw.toInt() : int.tryParse('$supplyIdRaw'));
      final qty = line['quantity'];
      final quantity = qty is num ? qty.toDouble() : double.tryParse('$qty') ?? 0;
      final usage = line['usage_type']?.toString() ?? 'out';
      if (supplyId == null || quantity <= 0) {
        continue;
      }
      if (!const {'out', 'consignment', 'write_off'}.contains(usage)) {
        continue;
      }
      final supply = byId[supplyId];
      if (supply == null) {
        return 'Insumo #$supplyId no está en caché local. Sincroniza e intenta de nuevo.';
      }
      final stockRaw = supply['quantity_on_hand'];
      final stock = stockRaw is num ? stockRaw.toDouble() : double.tryParse('$stockRaw') ?? 0;
      if (quantity > stock) {
        final label = '${supply['sku'] ?? ''} ${supply['name'] ?? 'insumo'}'.trim();
        return 'Stock insuficiente de «$label». Disponible: $stock, solicitado: $quantity.';
      }
    }
    return null;
  }

  Future<SyncResult> syncNow() async {
    final deviceId = _session.deviceId;
    if (deviceId == null || deviceId.isEmpty) {
      throw StateError('device_id no configurado');
    }

    // Reintentar eventos previamente rechazados junto con los pendientes.
    await (_db.update(_db.outboxEvents)..where((t) => t.status.equals('error')))
        .write(
      const OutboxEventsCompanion(
        status: Value('pending'),
        errorMessage: Value(null),
      ),
    );

    // Reintentar fotos marcadas en error.
    await (_db.update(_db.pendingMedia)..where((t) => t.status.equals('error')))
        .write(
      const PendingMediaCompanion(
        status: Value('pending'),
        errorMessage: Value(null),
      ),
    );

    await _preparePendingEvents();

    final pending = await (_db.select(_db.outboxEvents)
          ..where((t) => t.status.equals('pending')))
        .get();

    final events = <Map<String, dynamic>>[];
    for (final row in pending) {
      final payload = jsonDecode(row.payloadJson);
      if (payload is! Map) {
        continue;
      }
      // No empujar ejecuciones con fotos locales sin resolver.
      if (row.eventType == 'execution.submitted') {
        final responses = payload['responses'];
        if (responses is Map &&
            await _media.hasUnresolvedLocalPaths(Map<String, dynamic>.from(responses))) {
          continue;
        }
      }
      events.add({
        'event_id': row.eventId,
        'event_type': row.eventType,
        'payload': payload,
      });
    }

    final result = await _syncApi.sync(
      deviceId: deviceId,
      events: events,
      pull: true,
    );

    final push = result['push'];
    if (push is Map<String, dynamic>) {
      final accepted = (push['accepted'] as List<dynamic>? ?? [])
          .map((e) => e.toString())
          .toList();
      final rejected = push['rejected'] as List<dynamic>? ?? [];

      for (final eventId in accepted) {
        await (_db.update(_db.outboxEvents)..where((t) => t.eventId.equals(eventId)))
            .write(const OutboxEventsCompanion(status: Value('synced')));

        await _markRoutineSyncedForEvent(eventId);
      }

      for (final item in rejected) {
        if (item is Map) {
          final eventId = item['event_id']?.toString() ?? '';
          final reason = item['reason']?.toString() ?? 'rejected';
          if (eventId.isNotEmpty) {
            await (_db.update(_db.outboxEvents)..where((t) => t.eventId.equals(eventId)))
                .write(
              OutboxEventsCompanion(
                status: const Value('error'),
                errorMessage: Value(reason),
              ),
            );
            await _markRoutineSyncErrorForEvent(eventId);
          }
        }
      }
    }

    final pull = result['pull'];
    if (pull is Map<String, dynamic>) {
      await _persistPull(pull);
      final mobilePolicy = pull['mobile_policy'];
      if (mobilePolicy is Map) {
        await _session.updateCurrentCompanyMobilePolicy(
          Map<String, dynamic>.from(mobilePolicy),
        );
      }
    }

    final mediaPending = await _media.countPending();

    return SyncResult(
      accepted: (push?['accepted'] as List<dynamic>? ?? []).length,
      rejected: (push?['rejected'] as List<dynamic>? ?? []).length,
      routinesPulled: (pull?['routines'] as List<dynamic>? ?? []).length,
      mediaPending: mediaPending,
    );
  }

  Future<void> retryFailedOutbox() async {
    await (_db.update(_db.outboxEvents)..where((t) => t.status.equals('error')))
        .write(
      const OutboxEventsCompanion(
        status: Value('pending'),
        errorMessage: Value(null),
      ),
    );
    await syncNow();
  }

  Future<void> discardOutboxEvent(String eventId) async {
    final row = await (_db.select(_db.outboxEvents)
          ..where((t) => t.eventId.equals(eventId)))
        .getSingleOrNull();
    if (row == null) {
      return;
    }
    final payload = jsonDecode(row.payloadJson);
    await (_db.delete(_db.outboxEvents)..where((t) => t.eventId.equals(eventId))).go();
    if (payload is Map) {
      final routineId = payload['routine_id'];
      final id = routineId is int
          ? routineId
          : (routineId is num ? routineId.toInt() : int.tryParse('$routineId'));
      if (id != null) {
        await (_db.update(_db.localRoutines)..where((t) => t.id.equals(id))).write(
          const LocalRoutinesCompanion(localSyncStatus: Value('synced')),
        );
      }
    }
  }

  Future<int> countPendingOutbox() async {
    final rows = await (_db.select(_db.outboxEvents)
          ..where((t) => t.status.equals('pending')))
        .get();
    return rows.length;
  }

  Future<void> resetLocalData() async {
    await _db.delete(_db.localRoutines).go();
    await _db.delete(_db.outboxEvents).go();
    await _db.delete(_db.executionDrafts).go();
    await _db.delete(_db.pendingMedia).go();
    await _db.delete(_db.cachedMeta).go();
  }

  Future<void> purgeAllLocalData() async {
    await _media.deleteAllLocalFiles();
    await resetLocalData();
  }

  Future<void> _preparePendingEvents() async {
    final pending = await (_db.select(_db.outboxEvents)
          ..where((t) => t.status.equals('pending')))
        .get();

    for (final row in pending) {
      if (row.eventType != 'execution.submitted') {
        continue;
      }

      final payload = jsonDecode(row.payloadJson) as Map<String, dynamic>;
      final routineId = _routineIdFromPayload(payload);
      if (routineId == null) {
        continue;
      }

      await _media.uploadPendingForRoutine(routineId);

      final responses = payload['responses'];
      if (responses is! Map) {
        continue;
      }

      final resolved = await _media.resolveResponsePaths(
        Map<String, dynamic>.from(responses),
      );

      if (await _media.hasUnresolvedLocalPaths(resolved)) {
        continue;
      }

      payload['responses'] = resolved;
      await (_db.update(_db.outboxEvents)..where((t) => t.eventId.equals(row.eventId)))
          .write(
        OutboxEventsCompanion(payloadJson: Value(jsonEncode(payload))),
      );
    }
  }

  Future<void> _markRoutineSyncedForEvent(String eventId) async {
    final row = await (_db.select(_db.outboxEvents)
          ..where((t) => t.eventId.equals(eventId)))
        .getSingleOrNull();
    if (row == null) {
      return;
    }
    final payload = jsonDecode(row.payloadJson) as Map<String, dynamic>;
    final routineId = _routineIdFromPayload(payload);
    if (routineId != null) {
      await (_db.update(_db.localRoutines)..where((t) => t.id.equals(routineId)))
          .write(const LocalRoutinesCompanion(localSyncStatus: Value('synced')));
    }
  }

  Future<void> _markRoutineSyncErrorForEvent(String eventId) async {
    final row = await (_db.select(_db.outboxEvents)
          ..where((t) => t.eventId.equals(eventId)))
        .getSingleOrNull();
    if (row == null) {
      return;
    }
    final payload = jsonDecode(row.payloadJson) as Map<String, dynamic>;
    final routineId = _routineIdFromPayload(payload);
    if (routineId != null) {
      await (_db.update(_db.localRoutines)..where((t) => t.id.equals(routineId)))
          .write(const LocalRoutinesCompanion(localSyncStatus: Value('sync_error')));
    }
  }

  int? _routineIdFromPayload(Map<String, dynamic> payload) {
    final routineId = payload['routine_id'];
    if (routineId is int) {
      return routineId;
    }
    if (routineId is num) {
      return routineId.toInt();
    }
    return int.tryParse(routineId?.toString() ?? '');
  }

  Future<void> _persistPull(Map<String, dynamic> pull) async {
    final routines = pull['routines'] as List<dynamic>? ?? [];
    final pulledIds = <int>{};

    for (final item in routines) {
      if (item is! Map) {
        continue;
      }
      final map = Map<String, dynamic>.from(item);
      final id = map['id'];
      if (id is! int) {
        continue;
      }
      pulledIds.add(id);

      final existing = await (_db.select(_db.localRoutines)
            ..where((t) => t.id.equals(id)))
          .getSingleOrNull();

      final localStatus = existing?.localSyncStatus ?? 'synced';
      await _db.into(_db.localRoutines).insertOnConflictUpdate(
            LocalRoutinesCompanion(
              id: Value(id),
              status: Value(map['status']?.toString() ?? 'assigned'),
              payloadJson: Value(jsonEncode(map)),
              localSyncStatus: Value(localStatus),
            ),
          );

      await _seedDraftFromLatestExecutionIfNeeded(id, map);
    }

    final stale = await _db.select(_db.localRoutines).get();
    for (final row in stale) {
      if (pulledIds.contains(row.id)) {
        continue;
      }
      await (_db.delete(_db.localRoutines)..where((t) => t.id.equals(row.id))).go();
      await (_db.delete(_db.executionDrafts)
            ..where((t) => t.routineId.equals(row.id)))
          .go();
    }

    final catalogs = pull['option_catalogs'] as List<dynamic>? ?? [];
    await _db.into(_db.cachedMeta).insertOnConflictUpdate(
          CachedMetaCompanion(
            key: const Value('option_catalogs'),
            payloadJson: Value(jsonEncode(catalogs)),
          ),
        );

    final supplies = pull['supply_items'] as List<dynamic>? ?? [];
    await _db.into(_db.cachedMeta).insertOnConflictUpdate(
          CachedMetaCompanion(
            key: const Value('supply_items'),
            payloadJson: Value(jsonEncode(supplies)),
          ),
        );
  }

  /// Si hay ejecución previa (p. ej. rechazada) y el borrador local está vacío, lo rellena.
  Future<void> _seedDraftFromLatestExecutionIfNeeded(
    int routineId,
    Map<String, dynamic> routinePayload,
  ) async {
    try {
      await _seedDraftFromLatestExecution(routineId, routinePayload);
    } catch (_) {
      // No tumbar el sync completo si falla la hidratación de borrador/fotos.
    }
  }

  Future<void> _seedDraftFromLatestExecution(
    int routineId,
    Map<String, dynamic> routinePayload,
  ) async {
    final execution = routinePayload['latest_execution'];
    if (execution is! Map) {
      return;
    }

    final responses = execution['responses'];
    if (responses is! Map || responses.isEmpty) {
      return;
    }

    final existing = await getDraft(routineId);
    if (existing != null) {
      final decoded = jsonDecode(existing.responsesJson);
      if (decoded is Map && decoded.isNotEmpty) {
        // Aun con borrador, materializar fotos remotas que hayan quedado.
        final materialized = await _media.materializeRemotePhotosInResponses(
          routineId: routineId,
          responses: Map<String, dynamic>.from(decoded),
        );
        await saveDraft(
          routineId: routineId,
          responses: materialized,
          comments: existing.comments,
          durationMinutes: existing.durationMinutes,
          signatureLocalId: existing.signatureLocalId,
          consumptions: _decodeConsumptionsJson(existing.consumptionsJson),
        );
        return;
      }
    }

    final cleaned = Map<String, dynamic>.from(responses)
      ..remove('technician_signature');
    final materialized = await _media.materializeRemotePhotosInResponses(
      routineId: routineId,
      responses: cleaned,
    );
    final comments = execution['technician_comments']?.toString();
    final duration = execution['duration_minutes'];

    await saveDraft(
      routineId: routineId,
      responses: materialized,
      comments: (comments != null && comments.trim().isNotEmpty) ? comments : null,
      durationMinutes: duration is num ? duration.toInt() : null,
      signatureLocalId: null,
      consumptions: const [],
    );
  }

  List<Map<String, dynamic>> _decodeConsumptionsJson(String raw) {
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) {
        return const [];
      }
      return decoded
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } catch (_) {
      return const [];
    }
  }
}

class SyncResult {
  const SyncResult({
    required this.accepted,
    required this.rejected,
    required this.routinesPulled,
    required this.mediaPending,
  });

  final int accepted;
  final int rejected;
  final int routinesPulled;
  final int mediaPending;
}

final syncRepositoryProvider = Provider<SyncRepository>((ref) {
  return SyncRepository(
    db: ref.watch(appDatabaseProvider),
    syncApi: ref.watch(syncApiProvider),
    session: ref.watch(sessionStoreProvider),
    media: ref.watch(mediaRepositoryProvider),
  );
});
