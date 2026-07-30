import 'package:phoenix_field/core/network/authenticated_dio.dart';
import 'package:phoenix_field/data/api/media_api.dart';
import 'package:phoenix_field/data/api/sync_api.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/data/session/session_store.dart';

/// Ejecuta sync fuera del árbol Riverpod (background worker / lifecycle).
Future<bool> runBackgroundSyncIfAuthenticated() async {
  final session = SessionStore();
  await session.load();

  if (!session.isAuthenticated) {
    return false;
  }

  final db = AppDatabase();
  try {
    final dio = createAuthenticatedDio(session);
    final sync = SyncRepository(
      db: db,
      syncApi: SyncApi(dio),
      session: session,
      media: MediaRepository(db: db, mediaApi: MediaApi(dio)),
    );
    await sync.syncNow();
    return true;
  } catch (_) {
    return false;
  } finally {
    await db.close();
  }
}
