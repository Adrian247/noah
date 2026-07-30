import 'dart:convert';
import 'dart:io';

import 'package:drift/drift.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:phoenix_field/data/api/media_api.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/local/database_provider.dart';
import 'package:phoenix_field/shared/media/image_compression_service.dart';
import 'package:uuid/uuid.dart';

const localMediaPrefix = 'local:';

class MediaRepository {
  MediaRepository({
    required AppDatabase db,
    required MediaApi mediaApi,
  })  : _db = db,
        _mediaApi = mediaApi;

  final AppDatabase _db;
  final MediaApi _mediaApi;
  final _uuid = const Uuid();

  Stream<List<PendingMediaData>> watchPending() {
    return (_db.select(_db.pendingMedia)
          ..orderBy([(t) => OrderingTerm.desc(t.createdAt)]))
        .watch();
  }

  Future<int> countPending() async {
    final rows = await (_db.select(_db.pendingMedia)
          ..where((t) => t.status.equals('pending')))
        .get();
    return rows.length;
  }

  Future<String> saveLocalPhoto({
    required int routineId,
    required String fieldKey,
    required String sourcePath,
    String? caption,
  }) async {
    final id = _uuid.v4();
    final target = await _targetFile(routineId, id, sourcePath);
    final storedPath = await ImageCompressionService.compressToFile(
      sourcePath: sourcePath,
      targetPath: target.path,
    );

    await _db.into(_db.pendingMedia).insert(
          PendingMediaCompanion.insert(
            id: id,
            routineId: routineId,
            fieldKey: fieldKey,
            localPath: storedPath,
            caption: Value(caption),
            status: 'pending',
            createdAt: DateTime.now(),
          ),
        );

    return '$localMediaPrefix$id';
  }

  Future<void> updatePendingCaption(String localRef, String caption) async {
    if (!localRef.startsWith(localMediaPrefix)) {
      return;
    }
    final id = localRef.substring(localMediaPrefix.length);
    await (_db.update(_db.pendingMedia)..where((t) => t.id.equals(id))).write(
      PendingMediaCompanion(caption: Value(caption)),
    );
  }

  Future<File> _targetFile(int routineId, String id, String sourcePath) async {
    final docs = await getApplicationDocumentsDirectory();
    final dir = Directory(p.join(docs.path, 'media', routineId.toString()));
    if (!dir.existsSync()) {
      dir.createSync(recursive: true);
    }
    final ext = p.extension(sourcePath).isEmpty ? '.jpg' : p.extension(sourcePath);
    return File(p.join(dir.path, '$id${ext == '.png' ? '.jpg' : ext}'));
  }

  Future<String?> resolveLocalPath(String localRef) async {
    if (!localRef.startsWith(localMediaPrefix)) {
      return null;
    }
    final id = localRef.substring(localMediaPrefix.length);
    final row = await (_db.select(_db.pendingMedia)..where((t) => t.id.equals(id)))
        .getSingleOrNull();
    return row?.localPath;
  }

  Future<Map<String, dynamic>> uploadPendingForRoutine(int routineId) async {
    var uploaded = 0;
    var failed = 0;

    final pending = await (_db.select(_db.pendingMedia)
          ..where(
            (t) => t.routineId.equals(routineId) & t.status.equals('pending'),
          ))
        .get();

    for (final row in pending) {
      try {
        final result = await _mediaApi.uploadFormField(
          routineId: routineId,
          fieldKey: row.fieldKey,
          filePath: row.localPath,
        );
        final serverPath = result['path']?.toString();
        if (serverPath == null || serverPath.isEmpty) {
          throw StateError('path vacío');
        }

        await (_db.update(_db.pendingMedia)..where((t) => t.id.equals(row.id))).write(
              PendingMediaCompanion(
                status: const Value('uploaded'),
                serverPath: Value(serverPath),
                errorMessage: const Value(null),
              ),
            );
        uploaded++;
      } catch (e) {
        failed++;
        await (_db.update(_db.pendingMedia)..where((t) => t.id.equals(row.id))).write(
              PendingMediaCompanion(
                status: const Value('error'),
                errorMessage: Value(e.toString()),
              ),
            );
      }
    }

    return {'uploaded': uploaded, 'failed': failed};
  }

  /// Sustituye referencias `local:*` por rutas del servidor en [responses].
  Future<Map<String, dynamic>> resolveResponsePaths(
    Map<String, dynamic> responses,
  ) async {
    final resolved = Map<String, dynamic>.from(responses);

    for (final entry in responses.entries) {
      final value = entry.value;
      if (value is String && value.startsWith(localMediaPrefix)) {
        final serverPath = await _serverPathForLocalRef(value);
        if (serverPath != null) {
          resolved[entry.key] = serverPath;
        }
      } else if (value is List) {
        resolved[entry.key] = await _resolveList(value);
      } else if (value is Map) {
        final path = value['path'];
        if (path is String && path.startsWith(localMediaPrefix)) {
          final serverPath = await _serverPathForLocalRef(path);
          if (serverPath != null) {
            resolved[entry.key] = {
              ...Map<String, dynamic>.from(value),
              'path': serverPath,
            };
          }
        }
      }
    }

    return resolved;
  }

  Future<List<dynamic>> _resolveList(List<dynamic> items) async {
    final out = <dynamic>[];
    for (final item in items) {
      if (item is String && item.startsWith(localMediaPrefix)) {
        out.add(await _serverPathForLocalRef(item) ?? item);
      } else if (item is Map) {
        final path = item['path'];
        if (path is String && path.startsWith(localMediaPrefix)) {
          final serverPath = await _serverPathForLocalRef(path);
          out.add({
            ...Map<String, dynamic>.from(item),
            'path': serverPath ?? path,
          });
        } else {
          out.add(item);
        }
      } else {
        out.add(item);
      }
    }
    return out;
  }

  Future<String?> _serverPathForLocalRef(String localRef) async {
    final id = localRef.substring(localMediaPrefix.length);
    final row = await (_db.select(_db.pendingMedia)..where((t) => t.id.equals(id)))
        .getSingleOrNull();
    return row?.serverPath;
  }

  Future<bool> hasUnresolvedLocalPaths(Map<String, dynamic> responses) async {
    final encoded = jsonEncode(responses);
    return encoded.contains(localMediaPrefix);
  }

  Future<String> saveSignaturePng({
    required int routineId,
    required List<int> pngBytes,
  }) async {
    final id = _uuid.v4();
    final docs = await getApplicationDocumentsDirectory();
    final dir = Directory(p.join(docs.path, 'media', routineId.toString()));
    if (!dir.existsSync()) {
      dir.createSync(recursive: true);
    }
    final file = File(p.join(dir.path, 'signature-$id.png'));
    await file.writeAsBytes(pngBytes);

    await _db.into(_db.pendingMedia).insert(
          PendingMediaCompanion.insert(
            id: id,
            routineId: routineId,
            fieldKey: 'technician_signature',
            localPath: file.path,
            status: 'pending',
            createdAt: DateTime.now(),
          ),
        );

    return '$localMediaPrefix$id';
  }

  Future<void> deleteAllLocalFiles() async {
    final rows = await _db.select(_db.pendingMedia).get();
    for (final row in rows) {
      final file = File(row.localPath);
      if (file.existsSync()) {
        await file.delete();
      }
    }

    final docs = await getApplicationDocumentsDirectory();
    final mediaDir = Directory(p.join(docs.path, 'media'));
    if (mediaDir.existsSync()) {
      await mediaDir.delete(recursive: true);
    }
  }
}

final mediaRepositoryProvider = Provider<MediaRepository>((ref) {
  return MediaRepository(
    db: ref.watch(appDatabaseProvider),
    mediaApi: ref.watch(mediaApiProvider),
  );
});
