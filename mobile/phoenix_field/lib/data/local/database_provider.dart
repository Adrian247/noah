import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/data/local/app_database.dart';

final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});
