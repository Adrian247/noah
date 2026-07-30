import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'app_database.g.dart';

class LocalRoutines extends Table {
  IntColumn get id => integer()();
  TextColumn get status => text()();
  TextColumn get payloadJson => text()();
  TextColumn get localSyncStatus =>
      text().withDefault(const Constant('synced'))();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

class OutboxEvents extends Table {
  TextColumn get eventId => text()();
  TextColumn get eventType => text()();
  TextColumn get payloadJson => text()();
  TextColumn get status => text()();
  TextColumn get errorMessage => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {eventId};
}

class ExecutionDrafts extends Table {
  IntColumn get routineId => integer()();
  TextColumn get responsesJson => text()();
  TextColumn get comments => text().nullable()();
  IntColumn get durationMinutes => integer().nullable()();
  TextColumn get signatureLocalId => text().nullable()();
  TextColumn get consumptionsJson =>
      text().withDefault(const Constant('[]'))();

  @override
  Set<Column<Object>> get primaryKey => {routineId};
}

class PendingMedia extends Table {
  TextColumn get id => text()();
  IntColumn get routineId => integer()();
  TextColumn get fieldKey => text()();
  TextColumn get localPath => text()();
  TextColumn get caption => text().nullable()();
  TextColumn get status => text()();
  TextColumn get serverPath => text().nullable()();
  TextColumn get errorMessage => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

class CachedMeta extends Table {
  TextColumn get key => text()();
  TextColumn get payloadJson => text()();

  @override
  Set<Column<Object>> get primaryKey => {key};
}

@DriftDatabase(
  tables: [
    LocalRoutines,
    OutboxEvents,
    ExecutionDrafts,
    PendingMedia,
    CachedMeta,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  @override
  int get schemaVersion => 3;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        onCreate: (m) async {
          await m.createAll();
        },
        onUpgrade: (m, from, to) async {
          if (from < 2) {
            await m.createTable(pendingMedia);
            await m.addColumn(
              executionDrafts,
              executionDrafts.signatureLocalId,
            );
          }
          if (from < 3) {
            await m.addColumn(
              executionDrafts,
              executionDrafts.consumptionsJson,
            );
          }
        },
      );

  static QueryExecutor _openConnection() {
    return driftDatabase(name: 'phoenix_field');
  }
}
