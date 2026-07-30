// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $LocalRoutinesTable extends LocalRoutines
    with TableInfo<$LocalRoutinesTable, LocalRoutine> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalRoutinesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadJsonMeta = const VerificationMeta(
    'payloadJson',
  );
  @override
  late final GeneratedColumn<String> payloadJson = GeneratedColumn<String>(
    'payload_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _localSyncStatusMeta = const VerificationMeta(
    'localSyncStatus',
  );
  @override
  late final GeneratedColumn<String> localSyncStatus = GeneratedColumn<String>(
    'local_sync_status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('synced'),
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    status,
    payloadJson,
    localSyncStatus,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_routines';
  @override
  VerificationContext validateIntegrity(
    Insertable<LocalRoutine> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    } else if (isInserting) {
      context.missing(_statusMeta);
    }
    if (data.containsKey('payload_json')) {
      context.handle(
        _payloadJsonMeta,
        payloadJson.isAcceptableOrUnknown(
          data['payload_json']!,
          _payloadJsonMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_payloadJsonMeta);
    }
    if (data.containsKey('local_sync_status')) {
      context.handle(
        _localSyncStatusMeta,
        localSyncStatus.isAcceptableOrUnknown(
          data['local_sync_status']!,
          _localSyncStatusMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  LocalRoutine map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalRoutine(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}id'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      payloadJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload_json'],
      )!,
      localSyncStatus: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_sync_status'],
      )!,
    );
  }

  @override
  $LocalRoutinesTable createAlias(String alias) {
    return $LocalRoutinesTable(attachedDatabase, alias);
  }
}

class LocalRoutine extends DataClass implements Insertable<LocalRoutine> {
  final int id;
  final String status;
  final String payloadJson;
  final String localSyncStatus;
  const LocalRoutine({
    required this.id,
    required this.status,
    required this.payloadJson,
    required this.localSyncStatus,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['status'] = Variable<String>(status);
    map['payload_json'] = Variable<String>(payloadJson);
    map['local_sync_status'] = Variable<String>(localSyncStatus);
    return map;
  }

  LocalRoutinesCompanion toCompanion(bool nullToAbsent) {
    return LocalRoutinesCompanion(
      id: Value(id),
      status: Value(status),
      payloadJson: Value(payloadJson),
      localSyncStatus: Value(localSyncStatus),
    );
  }

  factory LocalRoutine.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalRoutine(
      id: serializer.fromJson<int>(json['id']),
      status: serializer.fromJson<String>(json['status']),
      payloadJson: serializer.fromJson<String>(json['payloadJson']),
      localSyncStatus: serializer.fromJson<String>(json['localSyncStatus']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'status': serializer.toJson<String>(status),
      'payloadJson': serializer.toJson<String>(payloadJson),
      'localSyncStatus': serializer.toJson<String>(localSyncStatus),
    };
  }

  LocalRoutine copyWith({
    int? id,
    String? status,
    String? payloadJson,
    String? localSyncStatus,
  }) => LocalRoutine(
    id: id ?? this.id,
    status: status ?? this.status,
    payloadJson: payloadJson ?? this.payloadJson,
    localSyncStatus: localSyncStatus ?? this.localSyncStatus,
  );
  LocalRoutine copyWithCompanion(LocalRoutinesCompanion data) {
    return LocalRoutine(
      id: data.id.present ? data.id.value : this.id,
      status: data.status.present ? data.status.value : this.status,
      payloadJson: data.payloadJson.present
          ? data.payloadJson.value
          : this.payloadJson,
      localSyncStatus: data.localSyncStatus.present
          ? data.localSyncStatus.value
          : this.localSyncStatus,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalRoutine(')
          ..write('id: $id, ')
          ..write('status: $status, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('localSyncStatus: $localSyncStatus')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, status, payloadJson, localSyncStatus);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalRoutine &&
          other.id == this.id &&
          other.status == this.status &&
          other.payloadJson == this.payloadJson &&
          other.localSyncStatus == this.localSyncStatus);
}

class LocalRoutinesCompanion extends UpdateCompanion<LocalRoutine> {
  final Value<int> id;
  final Value<String> status;
  final Value<String> payloadJson;
  final Value<String> localSyncStatus;
  const LocalRoutinesCompanion({
    this.id = const Value.absent(),
    this.status = const Value.absent(),
    this.payloadJson = const Value.absent(),
    this.localSyncStatus = const Value.absent(),
  });
  LocalRoutinesCompanion.insert({
    this.id = const Value.absent(),
    required String status,
    required String payloadJson,
    this.localSyncStatus = const Value.absent(),
  }) : status = Value(status),
       payloadJson = Value(payloadJson);
  static Insertable<LocalRoutine> custom({
    Expression<int>? id,
    Expression<String>? status,
    Expression<String>? payloadJson,
    Expression<String>? localSyncStatus,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (status != null) 'status': status,
      if (payloadJson != null) 'payload_json': payloadJson,
      if (localSyncStatus != null) 'local_sync_status': localSyncStatus,
    });
  }

  LocalRoutinesCompanion copyWith({
    Value<int>? id,
    Value<String>? status,
    Value<String>? payloadJson,
    Value<String>? localSyncStatus,
  }) {
    return LocalRoutinesCompanion(
      id: id ?? this.id,
      status: status ?? this.status,
      payloadJson: payloadJson ?? this.payloadJson,
      localSyncStatus: localSyncStatus ?? this.localSyncStatus,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (payloadJson.present) {
      map['payload_json'] = Variable<String>(payloadJson.value);
    }
    if (localSyncStatus.present) {
      map['local_sync_status'] = Variable<String>(localSyncStatus.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalRoutinesCompanion(')
          ..write('id: $id, ')
          ..write('status: $status, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('localSyncStatus: $localSyncStatus')
          ..write(')'))
        .toString();
  }
}

class $OutboxEventsTable extends OutboxEvents
    with TableInfo<$OutboxEventsTable, OutboxEvent> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $OutboxEventsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _eventIdMeta = const VerificationMeta(
    'eventId',
  );
  @override
  late final GeneratedColumn<String> eventId = GeneratedColumn<String>(
    'event_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _eventTypeMeta = const VerificationMeta(
    'eventType',
  );
  @override
  late final GeneratedColumn<String> eventType = GeneratedColumn<String>(
    'event_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadJsonMeta = const VerificationMeta(
    'payloadJson',
  );
  @override
  late final GeneratedColumn<String> payloadJson = GeneratedColumn<String>(
    'payload_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _errorMessageMeta = const VerificationMeta(
    'errorMessage',
  );
  @override
  late final GeneratedColumn<String> errorMessage = GeneratedColumn<String>(
    'error_message',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    eventId,
    eventType,
    payloadJson,
    status,
    errorMessage,
    createdAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'outbox_events';
  @override
  VerificationContext validateIntegrity(
    Insertable<OutboxEvent> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('event_id')) {
      context.handle(
        _eventIdMeta,
        eventId.isAcceptableOrUnknown(data['event_id']!, _eventIdMeta),
      );
    } else if (isInserting) {
      context.missing(_eventIdMeta);
    }
    if (data.containsKey('event_type')) {
      context.handle(
        _eventTypeMeta,
        eventType.isAcceptableOrUnknown(data['event_type']!, _eventTypeMeta),
      );
    } else if (isInserting) {
      context.missing(_eventTypeMeta);
    }
    if (data.containsKey('payload_json')) {
      context.handle(
        _payloadJsonMeta,
        payloadJson.isAcceptableOrUnknown(
          data['payload_json']!,
          _payloadJsonMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_payloadJsonMeta);
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    } else if (isInserting) {
      context.missing(_statusMeta);
    }
    if (data.containsKey('error_message')) {
      context.handle(
        _errorMessageMeta,
        errorMessage.isAcceptableOrUnknown(
          data['error_message']!,
          _errorMessageMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {eventId};
  @override
  OutboxEvent map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return OutboxEvent(
      eventId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}event_id'],
      )!,
      eventType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}event_type'],
      )!,
      payloadJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload_json'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      errorMessage: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}error_message'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
    );
  }

  @override
  $OutboxEventsTable createAlias(String alias) {
    return $OutboxEventsTable(attachedDatabase, alias);
  }
}

class OutboxEvent extends DataClass implements Insertable<OutboxEvent> {
  final String eventId;
  final String eventType;
  final String payloadJson;
  final String status;
  final String? errorMessage;
  final DateTime createdAt;
  const OutboxEvent({
    required this.eventId,
    required this.eventType,
    required this.payloadJson,
    required this.status,
    this.errorMessage,
    required this.createdAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['event_id'] = Variable<String>(eventId);
    map['event_type'] = Variable<String>(eventType);
    map['payload_json'] = Variable<String>(payloadJson);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || errorMessage != null) {
      map['error_message'] = Variable<String>(errorMessage);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    return map;
  }

  OutboxEventsCompanion toCompanion(bool nullToAbsent) {
    return OutboxEventsCompanion(
      eventId: Value(eventId),
      eventType: Value(eventType),
      payloadJson: Value(payloadJson),
      status: Value(status),
      errorMessage: errorMessage == null && nullToAbsent
          ? const Value.absent()
          : Value(errorMessage),
      createdAt: Value(createdAt),
    );
  }

  factory OutboxEvent.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return OutboxEvent(
      eventId: serializer.fromJson<String>(json['eventId']),
      eventType: serializer.fromJson<String>(json['eventType']),
      payloadJson: serializer.fromJson<String>(json['payloadJson']),
      status: serializer.fromJson<String>(json['status']),
      errorMessage: serializer.fromJson<String?>(json['errorMessage']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'eventId': serializer.toJson<String>(eventId),
      'eventType': serializer.toJson<String>(eventType),
      'payloadJson': serializer.toJson<String>(payloadJson),
      'status': serializer.toJson<String>(status),
      'errorMessage': serializer.toJson<String?>(errorMessage),
      'createdAt': serializer.toJson<DateTime>(createdAt),
    };
  }

  OutboxEvent copyWith({
    String? eventId,
    String? eventType,
    String? payloadJson,
    String? status,
    Value<String?> errorMessage = const Value.absent(),
    DateTime? createdAt,
  }) => OutboxEvent(
    eventId: eventId ?? this.eventId,
    eventType: eventType ?? this.eventType,
    payloadJson: payloadJson ?? this.payloadJson,
    status: status ?? this.status,
    errorMessage: errorMessage.present ? errorMessage.value : this.errorMessage,
    createdAt: createdAt ?? this.createdAt,
  );
  OutboxEvent copyWithCompanion(OutboxEventsCompanion data) {
    return OutboxEvent(
      eventId: data.eventId.present ? data.eventId.value : this.eventId,
      eventType: data.eventType.present ? data.eventType.value : this.eventType,
      payloadJson: data.payloadJson.present
          ? data.payloadJson.value
          : this.payloadJson,
      status: data.status.present ? data.status.value : this.status,
      errorMessage: data.errorMessage.present
          ? data.errorMessage.value
          : this.errorMessage,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('OutboxEvent(')
          ..write('eventId: $eventId, ')
          ..write('eventType: $eventType, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('status: $status, ')
          ..write('errorMessage: $errorMessage, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    eventId,
    eventType,
    payloadJson,
    status,
    errorMessage,
    createdAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is OutboxEvent &&
          other.eventId == this.eventId &&
          other.eventType == this.eventType &&
          other.payloadJson == this.payloadJson &&
          other.status == this.status &&
          other.errorMessage == this.errorMessage &&
          other.createdAt == this.createdAt);
}

class OutboxEventsCompanion extends UpdateCompanion<OutboxEvent> {
  final Value<String> eventId;
  final Value<String> eventType;
  final Value<String> payloadJson;
  final Value<String> status;
  final Value<String?> errorMessage;
  final Value<DateTime> createdAt;
  final Value<int> rowid;
  const OutboxEventsCompanion({
    this.eventId = const Value.absent(),
    this.eventType = const Value.absent(),
    this.payloadJson = const Value.absent(),
    this.status = const Value.absent(),
    this.errorMessage = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  OutboxEventsCompanion.insert({
    required String eventId,
    required String eventType,
    required String payloadJson,
    required String status,
    this.errorMessage = const Value.absent(),
    required DateTime createdAt,
    this.rowid = const Value.absent(),
  }) : eventId = Value(eventId),
       eventType = Value(eventType),
       payloadJson = Value(payloadJson),
       status = Value(status),
       createdAt = Value(createdAt);
  static Insertable<OutboxEvent> custom({
    Expression<String>? eventId,
    Expression<String>? eventType,
    Expression<String>? payloadJson,
    Expression<String>? status,
    Expression<String>? errorMessage,
    Expression<DateTime>? createdAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (eventId != null) 'event_id': eventId,
      if (eventType != null) 'event_type': eventType,
      if (payloadJson != null) 'payload_json': payloadJson,
      if (status != null) 'status': status,
      if (errorMessage != null) 'error_message': errorMessage,
      if (createdAt != null) 'created_at': createdAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  OutboxEventsCompanion copyWith({
    Value<String>? eventId,
    Value<String>? eventType,
    Value<String>? payloadJson,
    Value<String>? status,
    Value<String?>? errorMessage,
    Value<DateTime>? createdAt,
    Value<int>? rowid,
  }) {
    return OutboxEventsCompanion(
      eventId: eventId ?? this.eventId,
      eventType: eventType ?? this.eventType,
      payloadJson: payloadJson ?? this.payloadJson,
      status: status ?? this.status,
      errorMessage: errorMessage ?? this.errorMessage,
      createdAt: createdAt ?? this.createdAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (eventId.present) {
      map['event_id'] = Variable<String>(eventId.value);
    }
    if (eventType.present) {
      map['event_type'] = Variable<String>(eventType.value);
    }
    if (payloadJson.present) {
      map['payload_json'] = Variable<String>(payloadJson.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (errorMessage.present) {
      map['error_message'] = Variable<String>(errorMessage.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('OutboxEventsCompanion(')
          ..write('eventId: $eventId, ')
          ..write('eventType: $eventType, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('status: $status, ')
          ..write('errorMessage: $errorMessage, ')
          ..write('createdAt: $createdAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $ExecutionDraftsTable extends ExecutionDrafts
    with TableInfo<$ExecutionDraftsTable, ExecutionDraft> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ExecutionDraftsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _routineIdMeta = const VerificationMeta(
    'routineId',
  );
  @override
  late final GeneratedColumn<int> routineId = GeneratedColumn<int>(
    'routine_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _responsesJsonMeta = const VerificationMeta(
    'responsesJson',
  );
  @override
  late final GeneratedColumn<String> responsesJson = GeneratedColumn<String>(
    'responses_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _commentsMeta = const VerificationMeta(
    'comments',
  );
  @override
  late final GeneratedColumn<String> comments = GeneratedColumn<String>(
    'comments',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _durationMinutesMeta = const VerificationMeta(
    'durationMinutes',
  );
  @override
  late final GeneratedColumn<int> durationMinutes = GeneratedColumn<int>(
    'duration_minutes',
    aliasedName,
    true,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _signatureLocalIdMeta = const VerificationMeta(
    'signatureLocalId',
  );
  @override
  late final GeneratedColumn<String> signatureLocalId = GeneratedColumn<String>(
    'signature_local_id',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  @override
  List<GeneratedColumn> get $columns => [
    routineId,
    responsesJson,
    comments,
    durationMinutes,
    signatureLocalId,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'execution_drafts';
  @override
  VerificationContext validateIntegrity(
    Insertable<ExecutionDraft> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('routine_id')) {
      context.handle(
        _routineIdMeta,
        routineId.isAcceptableOrUnknown(data['routine_id']!, _routineIdMeta),
      );
    }
    if (data.containsKey('responses_json')) {
      context.handle(
        _responsesJsonMeta,
        responsesJson.isAcceptableOrUnknown(
          data['responses_json']!,
          _responsesJsonMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_responsesJsonMeta);
    }
    if (data.containsKey('comments')) {
      context.handle(
        _commentsMeta,
        comments.isAcceptableOrUnknown(data['comments']!, _commentsMeta),
      );
    }
    if (data.containsKey('duration_minutes')) {
      context.handle(
        _durationMinutesMeta,
        durationMinutes.isAcceptableOrUnknown(
          data['duration_minutes']!,
          _durationMinutesMeta,
        ),
      );
    }
    if (data.containsKey('signature_local_id')) {
      context.handle(
        _signatureLocalIdMeta,
        signatureLocalId.isAcceptableOrUnknown(
          data['signature_local_id']!,
          _signatureLocalIdMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {routineId};
  @override
  ExecutionDraft map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return ExecutionDraft(
      routineId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}routine_id'],
      )!,
      responsesJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}responses_json'],
      )!,
      comments: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}comments'],
      ),
      durationMinutes: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}duration_minutes'],
      ),
      signatureLocalId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}signature_local_id'],
      ),
    );
  }

  @override
  $ExecutionDraftsTable createAlias(String alias) {
    return $ExecutionDraftsTable(attachedDatabase, alias);
  }
}

class ExecutionDraft extends DataClass implements Insertable<ExecutionDraft> {
  final int routineId;
  final String responsesJson;
  final String? comments;
  final int? durationMinutes;
  final String? signatureLocalId;
  const ExecutionDraft({
    required this.routineId,
    required this.responsesJson,
    this.comments,
    this.durationMinutes,
    this.signatureLocalId,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['routine_id'] = Variable<int>(routineId);
    map['responses_json'] = Variable<String>(responsesJson);
    if (!nullToAbsent || comments != null) {
      map['comments'] = Variable<String>(comments);
    }
    if (!nullToAbsent || durationMinutes != null) {
      map['duration_minutes'] = Variable<int>(durationMinutes);
    }
    if (!nullToAbsent || signatureLocalId != null) {
      map['signature_local_id'] = Variable<String>(signatureLocalId);
    }
    return map;
  }

  ExecutionDraftsCompanion toCompanion(bool nullToAbsent) {
    return ExecutionDraftsCompanion(
      routineId: Value(routineId),
      responsesJson: Value(responsesJson),
      comments: comments == null && nullToAbsent
          ? const Value.absent()
          : Value(comments),
      durationMinutes: durationMinutes == null && nullToAbsent
          ? const Value.absent()
          : Value(durationMinutes),
      signatureLocalId: signatureLocalId == null && nullToAbsent
          ? const Value.absent()
          : Value(signatureLocalId),
    );
  }

  factory ExecutionDraft.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return ExecutionDraft(
      routineId: serializer.fromJson<int>(json['routineId']),
      responsesJson: serializer.fromJson<String>(json['responsesJson']),
      comments: serializer.fromJson<String?>(json['comments']),
      durationMinutes: serializer.fromJson<int?>(json['durationMinutes']),
      signatureLocalId: serializer.fromJson<String?>(json['signatureLocalId']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'routineId': serializer.toJson<int>(routineId),
      'responsesJson': serializer.toJson<String>(responsesJson),
      'comments': serializer.toJson<String?>(comments),
      'durationMinutes': serializer.toJson<int?>(durationMinutes),
      'signatureLocalId': serializer.toJson<String?>(signatureLocalId),
    };
  }

  ExecutionDraft copyWith({
    int? routineId,
    String? responsesJson,
    Value<String?> comments = const Value.absent(),
    Value<int?> durationMinutes = const Value.absent(),
    Value<String?> signatureLocalId = const Value.absent(),
  }) => ExecutionDraft(
    routineId: routineId ?? this.routineId,
    responsesJson: responsesJson ?? this.responsesJson,
    comments: comments.present ? comments.value : this.comments,
    durationMinutes: durationMinutes.present
        ? durationMinutes.value
        : this.durationMinutes,
    signatureLocalId: signatureLocalId.present
        ? signatureLocalId.value
        : this.signatureLocalId,
  );
  ExecutionDraft copyWithCompanion(ExecutionDraftsCompanion data) {
    return ExecutionDraft(
      routineId: data.routineId.present ? data.routineId.value : this.routineId,
      responsesJson: data.responsesJson.present
          ? data.responsesJson.value
          : this.responsesJson,
      comments: data.comments.present ? data.comments.value : this.comments,
      durationMinutes: data.durationMinutes.present
          ? data.durationMinutes.value
          : this.durationMinutes,
      signatureLocalId: data.signatureLocalId.present
          ? data.signatureLocalId.value
          : this.signatureLocalId,
    );
  }

  @override
  String toString() {
    return (StringBuffer('ExecutionDraft(')
          ..write('routineId: $routineId, ')
          ..write('responsesJson: $responsesJson, ')
          ..write('comments: $comments, ')
          ..write('durationMinutes: $durationMinutes, ')
          ..write('signatureLocalId: $signatureLocalId')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    routineId,
    responsesJson,
    comments,
    durationMinutes,
    signatureLocalId,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ExecutionDraft &&
          other.routineId == this.routineId &&
          other.responsesJson == this.responsesJson &&
          other.comments == this.comments &&
          other.durationMinutes == this.durationMinutes &&
          other.signatureLocalId == this.signatureLocalId);
}

class ExecutionDraftsCompanion extends UpdateCompanion<ExecutionDraft> {
  final Value<int> routineId;
  final Value<String> responsesJson;
  final Value<String?> comments;
  final Value<int?> durationMinutes;
  final Value<String?> signatureLocalId;
  const ExecutionDraftsCompanion({
    this.routineId = const Value.absent(),
    this.responsesJson = const Value.absent(),
    this.comments = const Value.absent(),
    this.durationMinutes = const Value.absent(),
    this.signatureLocalId = const Value.absent(),
  });
  ExecutionDraftsCompanion.insert({
    this.routineId = const Value.absent(),
    required String responsesJson,
    this.comments = const Value.absent(),
    this.durationMinutes = const Value.absent(),
    this.signatureLocalId = const Value.absent(),
  }) : responsesJson = Value(responsesJson);
  static Insertable<ExecutionDraft> custom({
    Expression<int>? routineId,
    Expression<String>? responsesJson,
    Expression<String>? comments,
    Expression<int>? durationMinutes,
    Expression<String>? signatureLocalId,
  }) {
    return RawValuesInsertable({
      if (routineId != null) 'routine_id': routineId,
      if (responsesJson != null) 'responses_json': responsesJson,
      if (comments != null) 'comments': comments,
      if (durationMinutes != null) 'duration_minutes': durationMinutes,
      if (signatureLocalId != null) 'signature_local_id': signatureLocalId,
    });
  }

  ExecutionDraftsCompanion copyWith({
    Value<int>? routineId,
    Value<String>? responsesJson,
    Value<String?>? comments,
    Value<int?>? durationMinutes,
    Value<String?>? signatureLocalId,
  }) {
    return ExecutionDraftsCompanion(
      routineId: routineId ?? this.routineId,
      responsesJson: responsesJson ?? this.responsesJson,
      comments: comments ?? this.comments,
      durationMinutes: durationMinutes ?? this.durationMinutes,
      signatureLocalId: signatureLocalId ?? this.signatureLocalId,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (routineId.present) {
      map['routine_id'] = Variable<int>(routineId.value);
    }
    if (responsesJson.present) {
      map['responses_json'] = Variable<String>(responsesJson.value);
    }
    if (comments.present) {
      map['comments'] = Variable<String>(comments.value);
    }
    if (durationMinutes.present) {
      map['duration_minutes'] = Variable<int>(durationMinutes.value);
    }
    if (signatureLocalId.present) {
      map['signature_local_id'] = Variable<String>(signatureLocalId.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ExecutionDraftsCompanion(')
          ..write('routineId: $routineId, ')
          ..write('responsesJson: $responsesJson, ')
          ..write('comments: $comments, ')
          ..write('durationMinutes: $durationMinutes, ')
          ..write('signatureLocalId: $signatureLocalId')
          ..write(')'))
        .toString();
  }
}

class $PendingMediaTable extends PendingMedia
    with TableInfo<$PendingMediaTable, PendingMediaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingMediaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
    'id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _routineIdMeta = const VerificationMeta(
    'routineId',
  );
  @override
  late final GeneratedColumn<int> routineId = GeneratedColumn<int>(
    'routine_id',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _fieldKeyMeta = const VerificationMeta(
    'fieldKey',
  );
  @override
  late final GeneratedColumn<String> fieldKey = GeneratedColumn<String>(
    'field_key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _localPathMeta = const VerificationMeta(
    'localPath',
  );
  @override
  late final GeneratedColumn<String> localPath = GeneratedColumn<String>(
    'local_path',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _captionMeta = const VerificationMeta(
    'caption',
  );
  @override
  late final GeneratedColumn<String> caption = GeneratedColumn<String>(
    'caption',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _serverPathMeta = const VerificationMeta(
    'serverPath',
  );
  @override
  late final GeneratedColumn<String> serverPath = GeneratedColumn<String>(
    'server_path',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _errorMessageMeta = const VerificationMeta(
    'errorMessage',
  );
  @override
  late final GeneratedColumn<String> errorMessage = GeneratedColumn<String>(
    'error_message',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    id,
    routineId,
    fieldKey,
    localPath,
    caption,
    status,
    serverPath,
    errorMessage,
    createdAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_media';
  @override
  VerificationContext validateIntegrity(
    Insertable<PendingMediaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('routine_id')) {
      context.handle(
        _routineIdMeta,
        routineId.isAcceptableOrUnknown(data['routine_id']!, _routineIdMeta),
      );
    } else if (isInserting) {
      context.missing(_routineIdMeta);
    }
    if (data.containsKey('field_key')) {
      context.handle(
        _fieldKeyMeta,
        fieldKey.isAcceptableOrUnknown(data['field_key']!, _fieldKeyMeta),
      );
    } else if (isInserting) {
      context.missing(_fieldKeyMeta);
    }
    if (data.containsKey('local_path')) {
      context.handle(
        _localPathMeta,
        localPath.isAcceptableOrUnknown(data['local_path']!, _localPathMeta),
      );
    } else if (isInserting) {
      context.missing(_localPathMeta);
    }
    if (data.containsKey('caption')) {
      context.handle(
        _captionMeta,
        caption.isAcceptableOrUnknown(data['caption']!, _captionMeta),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    } else if (isInserting) {
      context.missing(_statusMeta);
    }
    if (data.containsKey('server_path')) {
      context.handle(
        _serverPathMeta,
        serverPath.isAcceptableOrUnknown(data['server_path']!, _serverPathMeta),
      );
    }
    if (data.containsKey('error_message')) {
      context.handle(
        _errorMessageMeta,
        errorMessage.isAcceptableOrUnknown(
          data['error_message']!,
          _errorMessageMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  PendingMediaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingMediaData(
      id: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}id'],
      )!,
      routineId: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}routine_id'],
      )!,
      fieldKey: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}field_key'],
      )!,
      localPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_path'],
      )!,
      caption: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}caption'],
      ),
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      serverPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}server_path'],
      ),
      errorMessage: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}error_message'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
    );
  }

  @override
  $PendingMediaTable createAlias(String alias) {
    return $PendingMediaTable(attachedDatabase, alias);
  }
}

class PendingMediaData extends DataClass
    implements Insertable<PendingMediaData> {
  final String id;
  final int routineId;
  final String fieldKey;
  final String localPath;
  final String? caption;
  final String status;
  final String? serverPath;
  final String? errorMessage;
  final DateTime createdAt;
  const PendingMediaData({
    required this.id,
    required this.routineId,
    required this.fieldKey,
    required this.localPath,
    this.caption,
    required this.status,
    this.serverPath,
    this.errorMessage,
    required this.createdAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['routine_id'] = Variable<int>(routineId);
    map['field_key'] = Variable<String>(fieldKey);
    map['local_path'] = Variable<String>(localPath);
    if (!nullToAbsent || caption != null) {
      map['caption'] = Variable<String>(caption);
    }
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || serverPath != null) {
      map['server_path'] = Variable<String>(serverPath);
    }
    if (!nullToAbsent || errorMessage != null) {
      map['error_message'] = Variable<String>(errorMessage);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    return map;
  }

  PendingMediaCompanion toCompanion(bool nullToAbsent) {
    return PendingMediaCompanion(
      id: Value(id),
      routineId: Value(routineId),
      fieldKey: Value(fieldKey),
      localPath: Value(localPath),
      caption: caption == null && nullToAbsent
          ? const Value.absent()
          : Value(caption),
      status: Value(status),
      serverPath: serverPath == null && nullToAbsent
          ? const Value.absent()
          : Value(serverPath),
      errorMessage: errorMessage == null && nullToAbsent
          ? const Value.absent()
          : Value(errorMessage),
      createdAt: Value(createdAt),
    );
  }

  factory PendingMediaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingMediaData(
      id: serializer.fromJson<String>(json['id']),
      routineId: serializer.fromJson<int>(json['routineId']),
      fieldKey: serializer.fromJson<String>(json['fieldKey']),
      localPath: serializer.fromJson<String>(json['localPath']),
      caption: serializer.fromJson<String?>(json['caption']),
      status: serializer.fromJson<String>(json['status']),
      serverPath: serializer.fromJson<String?>(json['serverPath']),
      errorMessage: serializer.fromJson<String?>(json['errorMessage']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'routineId': serializer.toJson<int>(routineId),
      'fieldKey': serializer.toJson<String>(fieldKey),
      'localPath': serializer.toJson<String>(localPath),
      'caption': serializer.toJson<String?>(caption),
      'status': serializer.toJson<String>(status),
      'serverPath': serializer.toJson<String?>(serverPath),
      'errorMessage': serializer.toJson<String?>(errorMessage),
      'createdAt': serializer.toJson<DateTime>(createdAt),
    };
  }

  PendingMediaData copyWith({
    String? id,
    int? routineId,
    String? fieldKey,
    String? localPath,
    Value<String?> caption = const Value.absent(),
    String? status,
    Value<String?> serverPath = const Value.absent(),
    Value<String?> errorMessage = const Value.absent(),
    DateTime? createdAt,
  }) => PendingMediaData(
    id: id ?? this.id,
    routineId: routineId ?? this.routineId,
    fieldKey: fieldKey ?? this.fieldKey,
    localPath: localPath ?? this.localPath,
    caption: caption.present ? caption.value : this.caption,
    status: status ?? this.status,
    serverPath: serverPath.present ? serverPath.value : this.serverPath,
    errorMessage: errorMessage.present ? errorMessage.value : this.errorMessage,
    createdAt: createdAt ?? this.createdAt,
  );
  PendingMediaData copyWithCompanion(PendingMediaCompanion data) {
    return PendingMediaData(
      id: data.id.present ? data.id.value : this.id,
      routineId: data.routineId.present ? data.routineId.value : this.routineId,
      fieldKey: data.fieldKey.present ? data.fieldKey.value : this.fieldKey,
      localPath: data.localPath.present ? data.localPath.value : this.localPath,
      caption: data.caption.present ? data.caption.value : this.caption,
      status: data.status.present ? data.status.value : this.status,
      serverPath: data.serverPath.present
          ? data.serverPath.value
          : this.serverPath,
      errorMessage: data.errorMessage.present
          ? data.errorMessage.value
          : this.errorMessage,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingMediaData(')
          ..write('id: $id, ')
          ..write('routineId: $routineId, ')
          ..write('fieldKey: $fieldKey, ')
          ..write('localPath: $localPath, ')
          ..write('caption: $caption, ')
          ..write('status: $status, ')
          ..write('serverPath: $serverPath, ')
          ..write('errorMessage: $errorMessage, ')
          ..write('createdAt: $createdAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    id,
    routineId,
    fieldKey,
    localPath,
    caption,
    status,
    serverPath,
    errorMessage,
    createdAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingMediaData &&
          other.id == this.id &&
          other.routineId == this.routineId &&
          other.fieldKey == this.fieldKey &&
          other.localPath == this.localPath &&
          other.caption == this.caption &&
          other.status == this.status &&
          other.serverPath == this.serverPath &&
          other.errorMessage == this.errorMessage &&
          other.createdAt == this.createdAt);
}

class PendingMediaCompanion extends UpdateCompanion<PendingMediaData> {
  final Value<String> id;
  final Value<int> routineId;
  final Value<String> fieldKey;
  final Value<String> localPath;
  final Value<String?> caption;
  final Value<String> status;
  final Value<String?> serverPath;
  final Value<String?> errorMessage;
  final Value<DateTime> createdAt;
  final Value<int> rowid;
  const PendingMediaCompanion({
    this.id = const Value.absent(),
    this.routineId = const Value.absent(),
    this.fieldKey = const Value.absent(),
    this.localPath = const Value.absent(),
    this.caption = const Value.absent(),
    this.status = const Value.absent(),
    this.serverPath = const Value.absent(),
    this.errorMessage = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PendingMediaCompanion.insert({
    required String id,
    required int routineId,
    required String fieldKey,
    required String localPath,
    this.caption = const Value.absent(),
    required String status,
    this.serverPath = const Value.absent(),
    this.errorMessage = const Value.absent(),
    required DateTime createdAt,
    this.rowid = const Value.absent(),
  }) : id = Value(id),
       routineId = Value(routineId),
       fieldKey = Value(fieldKey),
       localPath = Value(localPath),
       status = Value(status),
       createdAt = Value(createdAt);
  static Insertable<PendingMediaData> custom({
    Expression<String>? id,
    Expression<int>? routineId,
    Expression<String>? fieldKey,
    Expression<String>? localPath,
    Expression<String>? caption,
    Expression<String>? status,
    Expression<String>? serverPath,
    Expression<String>? errorMessage,
    Expression<DateTime>? createdAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (routineId != null) 'routine_id': routineId,
      if (fieldKey != null) 'field_key': fieldKey,
      if (localPath != null) 'local_path': localPath,
      if (caption != null) 'caption': caption,
      if (status != null) 'status': status,
      if (serverPath != null) 'server_path': serverPath,
      if (errorMessage != null) 'error_message': errorMessage,
      if (createdAt != null) 'created_at': createdAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PendingMediaCompanion copyWith({
    Value<String>? id,
    Value<int>? routineId,
    Value<String>? fieldKey,
    Value<String>? localPath,
    Value<String?>? caption,
    Value<String>? status,
    Value<String?>? serverPath,
    Value<String?>? errorMessage,
    Value<DateTime>? createdAt,
    Value<int>? rowid,
  }) {
    return PendingMediaCompanion(
      id: id ?? this.id,
      routineId: routineId ?? this.routineId,
      fieldKey: fieldKey ?? this.fieldKey,
      localPath: localPath ?? this.localPath,
      caption: caption ?? this.caption,
      status: status ?? this.status,
      serverPath: serverPath ?? this.serverPath,
      errorMessage: errorMessage ?? this.errorMessage,
      createdAt: createdAt ?? this.createdAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (routineId.present) {
      map['routine_id'] = Variable<int>(routineId.value);
    }
    if (fieldKey.present) {
      map['field_key'] = Variable<String>(fieldKey.value);
    }
    if (localPath.present) {
      map['local_path'] = Variable<String>(localPath.value);
    }
    if (caption.present) {
      map['caption'] = Variable<String>(caption.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (serverPath.present) {
      map['server_path'] = Variable<String>(serverPath.value);
    }
    if (errorMessage.present) {
      map['error_message'] = Variable<String>(errorMessage.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingMediaCompanion(')
          ..write('id: $id, ')
          ..write('routineId: $routineId, ')
          ..write('fieldKey: $fieldKey, ')
          ..write('localPath: $localPath, ')
          ..write('caption: $caption, ')
          ..write('status: $status, ')
          ..write('serverPath: $serverPath, ')
          ..write('errorMessage: $errorMessage, ')
          ..write('createdAt: $createdAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $CachedMetaTable extends CachedMeta
    with TableInfo<$CachedMetaTable, CachedMetaData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedMetaTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _keyMeta = const VerificationMeta('key');
  @override
  late final GeneratedColumn<String> key = GeneratedColumn<String>(
    'key',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadJsonMeta = const VerificationMeta(
    'payloadJson',
  );
  @override
  late final GeneratedColumn<String> payloadJson = GeneratedColumn<String>(
    'payload_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [key, payloadJson];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_meta';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedMetaData> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('key')) {
      context.handle(
        _keyMeta,
        key.isAcceptableOrUnknown(data['key']!, _keyMeta),
      );
    } else if (isInserting) {
      context.missing(_keyMeta);
    }
    if (data.containsKey('payload_json')) {
      context.handle(
        _payloadJsonMeta,
        payloadJson.isAcceptableOrUnknown(
          data['payload_json']!,
          _payloadJsonMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_payloadJsonMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {key};
  @override
  CachedMetaData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedMetaData(
      key: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}key'],
      )!,
      payloadJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload_json'],
      )!,
    );
  }

  @override
  $CachedMetaTable createAlias(String alias) {
    return $CachedMetaTable(attachedDatabase, alias);
  }
}

class CachedMetaData extends DataClass implements Insertable<CachedMetaData> {
  final String key;
  final String payloadJson;
  const CachedMetaData({required this.key, required this.payloadJson});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['key'] = Variable<String>(key);
    map['payload_json'] = Variable<String>(payloadJson);
    return map;
  }

  CachedMetaCompanion toCompanion(bool nullToAbsent) {
    return CachedMetaCompanion(
      key: Value(key),
      payloadJson: Value(payloadJson),
    );
  }

  factory CachedMetaData.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedMetaData(
      key: serializer.fromJson<String>(json['key']),
      payloadJson: serializer.fromJson<String>(json['payloadJson']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'key': serializer.toJson<String>(key),
      'payloadJson': serializer.toJson<String>(payloadJson),
    };
  }

  CachedMetaData copyWith({String? key, String? payloadJson}) => CachedMetaData(
    key: key ?? this.key,
    payloadJson: payloadJson ?? this.payloadJson,
  );
  CachedMetaData copyWithCompanion(CachedMetaCompanion data) {
    return CachedMetaData(
      key: data.key.present ? data.key.value : this.key,
      payloadJson: data.payloadJson.present
          ? data.payloadJson.value
          : this.payloadJson,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedMetaData(')
          ..write('key: $key, ')
          ..write('payloadJson: $payloadJson')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(key, payloadJson);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedMetaData &&
          other.key == this.key &&
          other.payloadJson == this.payloadJson);
}

class CachedMetaCompanion extends UpdateCompanion<CachedMetaData> {
  final Value<String> key;
  final Value<String> payloadJson;
  final Value<int> rowid;
  const CachedMetaCompanion({
    this.key = const Value.absent(),
    this.payloadJson = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedMetaCompanion.insert({
    required String key,
    required String payloadJson,
    this.rowid = const Value.absent(),
  }) : key = Value(key),
       payloadJson = Value(payloadJson);
  static Insertable<CachedMetaData> custom({
    Expression<String>? key,
    Expression<String>? payloadJson,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (key != null) 'key': key,
      if (payloadJson != null) 'payload_json': payloadJson,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedMetaCompanion copyWith({
    Value<String>? key,
    Value<String>? payloadJson,
    Value<int>? rowid,
  }) {
    return CachedMetaCompanion(
      key: key ?? this.key,
      payloadJson: payloadJson ?? this.payloadJson,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (key.present) {
      map['key'] = Variable<String>(key.value);
    }
    if (payloadJson.present) {
      map['payload_json'] = Variable<String>(payloadJson.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedMetaCompanion(')
          ..write('key: $key, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $LocalRoutinesTable localRoutines = $LocalRoutinesTable(this);
  late final $OutboxEventsTable outboxEvents = $OutboxEventsTable(this);
  late final $ExecutionDraftsTable executionDrafts = $ExecutionDraftsTable(
    this,
  );
  late final $PendingMediaTable pendingMedia = $PendingMediaTable(this);
  late final $CachedMetaTable cachedMeta = $CachedMetaTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    localRoutines,
    outboxEvents,
    executionDrafts,
    pendingMedia,
    cachedMeta,
  ];
}

typedef $$LocalRoutinesTableCreateCompanionBuilder =
    LocalRoutinesCompanion Function({
      Value<int> id,
      required String status,
      required String payloadJson,
      Value<String> localSyncStatus,
    });
typedef $$LocalRoutinesTableUpdateCompanionBuilder =
    LocalRoutinesCompanion Function({
      Value<int> id,
      Value<String> status,
      Value<String> payloadJson,
      Value<String> localSyncStatus,
    });

class $$LocalRoutinesTableFilterComposer
    extends Composer<_$AppDatabase, $LocalRoutinesTable> {
  $$LocalRoutinesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localSyncStatus => $composableBuilder(
    column: $table.localSyncStatus,
    builder: (column) => ColumnFilters(column),
  );
}

class $$LocalRoutinesTableOrderingComposer
    extends Composer<_$AppDatabase, $LocalRoutinesTable> {
  $$LocalRoutinesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localSyncStatus => $composableBuilder(
    column: $table.localSyncStatus,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$LocalRoutinesTableAnnotationComposer
    extends Composer<_$AppDatabase, $LocalRoutinesTable> {
  $$LocalRoutinesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => column,
  );

  GeneratedColumn<String> get localSyncStatus => $composableBuilder(
    column: $table.localSyncStatus,
    builder: (column) => column,
  );
}

class $$LocalRoutinesTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $LocalRoutinesTable,
          LocalRoutine,
          $$LocalRoutinesTableFilterComposer,
          $$LocalRoutinesTableOrderingComposer,
          $$LocalRoutinesTableAnnotationComposer,
          $$LocalRoutinesTableCreateCompanionBuilder,
          $$LocalRoutinesTableUpdateCompanionBuilder,
          (
            LocalRoutine,
            BaseReferences<_$AppDatabase, $LocalRoutinesTable, LocalRoutine>,
          ),
          LocalRoutine,
          PrefetchHooks Function()
        > {
  $$LocalRoutinesTableTableManager(_$AppDatabase db, $LocalRoutinesTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalRoutinesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalRoutinesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalRoutinesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String> payloadJson = const Value.absent(),
                Value<String> localSyncStatus = const Value.absent(),
              }) => LocalRoutinesCompanion(
                id: id,
                status: status,
                payloadJson: payloadJson,
                localSyncStatus: localSyncStatus,
              ),
          createCompanionCallback:
              ({
                Value<int> id = const Value.absent(),
                required String status,
                required String payloadJson,
                Value<String> localSyncStatus = const Value.absent(),
              }) => LocalRoutinesCompanion.insert(
                id: id,
                status: status,
                payloadJson: payloadJson,
                localSyncStatus: localSyncStatus,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$LocalRoutinesTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $LocalRoutinesTable,
      LocalRoutine,
      $$LocalRoutinesTableFilterComposer,
      $$LocalRoutinesTableOrderingComposer,
      $$LocalRoutinesTableAnnotationComposer,
      $$LocalRoutinesTableCreateCompanionBuilder,
      $$LocalRoutinesTableUpdateCompanionBuilder,
      (
        LocalRoutine,
        BaseReferences<_$AppDatabase, $LocalRoutinesTable, LocalRoutine>,
      ),
      LocalRoutine,
      PrefetchHooks Function()
    >;
typedef $$OutboxEventsTableCreateCompanionBuilder =
    OutboxEventsCompanion Function({
      required String eventId,
      required String eventType,
      required String payloadJson,
      required String status,
      Value<String?> errorMessage,
      required DateTime createdAt,
      Value<int> rowid,
    });
typedef $$OutboxEventsTableUpdateCompanionBuilder =
    OutboxEventsCompanion Function({
      Value<String> eventId,
      Value<String> eventType,
      Value<String> payloadJson,
      Value<String> status,
      Value<String?> errorMessage,
      Value<DateTime> createdAt,
      Value<int> rowid,
    });

class $$OutboxEventsTableFilterComposer
    extends Composer<_$AppDatabase, $OutboxEventsTable> {
  $$OutboxEventsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get eventId => $composableBuilder(
    column: $table.eventId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get eventType => $composableBuilder(
    column: $table.eventType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$OutboxEventsTableOrderingComposer
    extends Composer<_$AppDatabase, $OutboxEventsTable> {
  $$OutboxEventsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get eventId => $composableBuilder(
    column: $table.eventId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get eventType => $composableBuilder(
    column: $table.eventType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$OutboxEventsTableAnnotationComposer
    extends Composer<_$AppDatabase, $OutboxEventsTable> {
  $$OutboxEventsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get eventId =>
      $composableBuilder(column: $table.eventId, builder: (column) => column);

  GeneratedColumn<String> get eventType =>
      $composableBuilder(column: $table.eventType, builder: (column) => column);

  GeneratedColumn<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => column,
  );

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);
}

class $$OutboxEventsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $OutboxEventsTable,
          OutboxEvent,
          $$OutboxEventsTableFilterComposer,
          $$OutboxEventsTableOrderingComposer,
          $$OutboxEventsTableAnnotationComposer,
          $$OutboxEventsTableCreateCompanionBuilder,
          $$OutboxEventsTableUpdateCompanionBuilder,
          (
            OutboxEvent,
            BaseReferences<_$AppDatabase, $OutboxEventsTable, OutboxEvent>,
          ),
          OutboxEvent,
          PrefetchHooks Function()
        > {
  $$OutboxEventsTableTableManager(_$AppDatabase db, $OutboxEventsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$OutboxEventsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$OutboxEventsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$OutboxEventsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> eventId = const Value.absent(),
                Value<String> eventType = const Value.absent(),
                Value<String> payloadJson = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String?> errorMessage = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => OutboxEventsCompanion(
                eventId: eventId,
                eventType: eventType,
                payloadJson: payloadJson,
                status: status,
                errorMessage: errorMessage,
                createdAt: createdAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String eventId,
                required String eventType,
                required String payloadJson,
                required String status,
                Value<String?> errorMessage = const Value.absent(),
                required DateTime createdAt,
                Value<int> rowid = const Value.absent(),
              }) => OutboxEventsCompanion.insert(
                eventId: eventId,
                eventType: eventType,
                payloadJson: payloadJson,
                status: status,
                errorMessage: errorMessage,
                createdAt: createdAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$OutboxEventsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $OutboxEventsTable,
      OutboxEvent,
      $$OutboxEventsTableFilterComposer,
      $$OutboxEventsTableOrderingComposer,
      $$OutboxEventsTableAnnotationComposer,
      $$OutboxEventsTableCreateCompanionBuilder,
      $$OutboxEventsTableUpdateCompanionBuilder,
      (
        OutboxEvent,
        BaseReferences<_$AppDatabase, $OutboxEventsTable, OutboxEvent>,
      ),
      OutboxEvent,
      PrefetchHooks Function()
    >;
typedef $$ExecutionDraftsTableCreateCompanionBuilder =
    ExecutionDraftsCompanion Function({
      Value<int> routineId,
      required String responsesJson,
      Value<String?> comments,
      Value<int?> durationMinutes,
      Value<String?> signatureLocalId,
    });
typedef $$ExecutionDraftsTableUpdateCompanionBuilder =
    ExecutionDraftsCompanion Function({
      Value<int> routineId,
      Value<String> responsesJson,
      Value<String?> comments,
      Value<int?> durationMinutes,
      Value<String?> signatureLocalId,
    });

class $$ExecutionDraftsTableFilterComposer
    extends Composer<_$AppDatabase, $ExecutionDraftsTable> {
  $$ExecutionDraftsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get routineId => $composableBuilder(
    column: $table.routineId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get responsesJson => $composableBuilder(
    column: $table.responsesJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get comments => $composableBuilder(
    column: $table.comments,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get durationMinutes => $composableBuilder(
    column: $table.durationMinutes,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get signatureLocalId => $composableBuilder(
    column: $table.signatureLocalId,
    builder: (column) => ColumnFilters(column),
  );
}

class $$ExecutionDraftsTableOrderingComposer
    extends Composer<_$AppDatabase, $ExecutionDraftsTable> {
  $$ExecutionDraftsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get routineId => $composableBuilder(
    column: $table.routineId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get responsesJson => $composableBuilder(
    column: $table.responsesJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get comments => $composableBuilder(
    column: $table.comments,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get durationMinutes => $composableBuilder(
    column: $table.durationMinutes,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get signatureLocalId => $composableBuilder(
    column: $table.signatureLocalId,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$ExecutionDraftsTableAnnotationComposer
    extends Composer<_$AppDatabase, $ExecutionDraftsTable> {
  $$ExecutionDraftsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get routineId =>
      $composableBuilder(column: $table.routineId, builder: (column) => column);

  GeneratedColumn<String> get responsesJson => $composableBuilder(
    column: $table.responsesJson,
    builder: (column) => column,
  );

  GeneratedColumn<String> get comments =>
      $composableBuilder(column: $table.comments, builder: (column) => column);

  GeneratedColumn<int> get durationMinutes => $composableBuilder(
    column: $table.durationMinutes,
    builder: (column) => column,
  );

  GeneratedColumn<String> get signatureLocalId => $composableBuilder(
    column: $table.signatureLocalId,
    builder: (column) => column,
  );
}

class $$ExecutionDraftsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $ExecutionDraftsTable,
          ExecutionDraft,
          $$ExecutionDraftsTableFilterComposer,
          $$ExecutionDraftsTableOrderingComposer,
          $$ExecutionDraftsTableAnnotationComposer,
          $$ExecutionDraftsTableCreateCompanionBuilder,
          $$ExecutionDraftsTableUpdateCompanionBuilder,
          (
            ExecutionDraft,
            BaseReferences<
              _$AppDatabase,
              $ExecutionDraftsTable,
              ExecutionDraft
            >,
          ),
          ExecutionDraft,
          PrefetchHooks Function()
        > {
  $$ExecutionDraftsTableTableManager(
    _$AppDatabase db,
    $ExecutionDraftsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ExecutionDraftsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ExecutionDraftsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ExecutionDraftsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<int> routineId = const Value.absent(),
                Value<String> responsesJson = const Value.absent(),
                Value<String?> comments = const Value.absent(),
                Value<int?> durationMinutes = const Value.absent(),
                Value<String?> signatureLocalId = const Value.absent(),
              }) => ExecutionDraftsCompanion(
                routineId: routineId,
                responsesJson: responsesJson,
                comments: comments,
                durationMinutes: durationMinutes,
                signatureLocalId: signatureLocalId,
              ),
          createCompanionCallback:
              ({
                Value<int> routineId = const Value.absent(),
                required String responsesJson,
                Value<String?> comments = const Value.absent(),
                Value<int?> durationMinutes = const Value.absent(),
                Value<String?> signatureLocalId = const Value.absent(),
              }) => ExecutionDraftsCompanion.insert(
                routineId: routineId,
                responsesJson: responsesJson,
                comments: comments,
                durationMinutes: durationMinutes,
                signatureLocalId: signatureLocalId,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$ExecutionDraftsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $ExecutionDraftsTable,
      ExecutionDraft,
      $$ExecutionDraftsTableFilterComposer,
      $$ExecutionDraftsTableOrderingComposer,
      $$ExecutionDraftsTableAnnotationComposer,
      $$ExecutionDraftsTableCreateCompanionBuilder,
      $$ExecutionDraftsTableUpdateCompanionBuilder,
      (
        ExecutionDraft,
        BaseReferences<_$AppDatabase, $ExecutionDraftsTable, ExecutionDraft>,
      ),
      ExecutionDraft,
      PrefetchHooks Function()
    >;
typedef $$PendingMediaTableCreateCompanionBuilder =
    PendingMediaCompanion Function({
      required String id,
      required int routineId,
      required String fieldKey,
      required String localPath,
      Value<String?> caption,
      required String status,
      Value<String?> serverPath,
      Value<String?> errorMessage,
      required DateTime createdAt,
      Value<int> rowid,
    });
typedef $$PendingMediaTableUpdateCompanionBuilder =
    PendingMediaCompanion Function({
      Value<String> id,
      Value<int> routineId,
      Value<String> fieldKey,
      Value<String> localPath,
      Value<String?> caption,
      Value<String> status,
      Value<String?> serverPath,
      Value<String?> errorMessage,
      Value<DateTime> createdAt,
      Value<int> rowid,
    });

class $$PendingMediaTableFilterComposer
    extends Composer<_$AppDatabase, $PendingMediaTable> {
  $$PendingMediaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get routineId => $composableBuilder(
    column: $table.routineId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get fieldKey => $composableBuilder(
    column: $table.fieldKey,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get caption => $composableBuilder(
    column: $table.caption,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get serverPath => $composableBuilder(
    column: $table.serverPath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PendingMediaTableOrderingComposer
    extends Composer<_$AppDatabase, $PendingMediaTable> {
  $$PendingMediaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
    column: $table.id,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get routineId => $composableBuilder(
    column: $table.routineId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get fieldKey => $composableBuilder(
    column: $table.fieldKey,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get caption => $composableBuilder(
    column: $table.caption,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get serverPath => $composableBuilder(
    column: $table.serverPath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PendingMediaTableAnnotationComposer
    extends Composer<_$AppDatabase, $PendingMediaTable> {
  $$PendingMediaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<int> get routineId =>
      $composableBuilder(column: $table.routineId, builder: (column) => column);

  GeneratedColumn<String> get fieldKey =>
      $composableBuilder(column: $table.fieldKey, builder: (column) => column);

  GeneratedColumn<String> get localPath =>
      $composableBuilder(column: $table.localPath, builder: (column) => column);

  GeneratedColumn<String> get caption =>
      $composableBuilder(column: $table.caption, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get serverPath => $composableBuilder(
    column: $table.serverPath,
    builder: (column) => column,
  );

  GeneratedColumn<String> get errorMessage => $composableBuilder(
    column: $table.errorMessage,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);
}

class $$PendingMediaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PendingMediaTable,
          PendingMediaData,
          $$PendingMediaTableFilterComposer,
          $$PendingMediaTableOrderingComposer,
          $$PendingMediaTableAnnotationComposer,
          $$PendingMediaTableCreateCompanionBuilder,
          $$PendingMediaTableUpdateCompanionBuilder,
          (
            PendingMediaData,
            BaseReferences<_$AppDatabase, $PendingMediaTable, PendingMediaData>,
          ),
          PendingMediaData,
          PrefetchHooks Function()
        > {
  $$PendingMediaTableTableManager(_$AppDatabase db, $PendingMediaTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingMediaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingMediaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PendingMediaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> id = const Value.absent(),
                Value<int> routineId = const Value.absent(),
                Value<String> fieldKey = const Value.absent(),
                Value<String> localPath = const Value.absent(),
                Value<String?> caption = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<String?> serverPath = const Value.absent(),
                Value<String?> errorMessage = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingMediaCompanion(
                id: id,
                routineId: routineId,
                fieldKey: fieldKey,
                localPath: localPath,
                caption: caption,
                status: status,
                serverPath: serverPath,
                errorMessage: errorMessage,
                createdAt: createdAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String id,
                required int routineId,
                required String fieldKey,
                required String localPath,
                Value<String?> caption = const Value.absent(),
                required String status,
                Value<String?> serverPath = const Value.absent(),
                Value<String?> errorMessage = const Value.absent(),
                required DateTime createdAt,
                Value<int> rowid = const Value.absent(),
              }) => PendingMediaCompanion.insert(
                id: id,
                routineId: routineId,
                fieldKey: fieldKey,
                localPath: localPath,
                caption: caption,
                status: status,
                serverPath: serverPath,
                errorMessage: errorMessage,
                createdAt: createdAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PendingMediaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PendingMediaTable,
      PendingMediaData,
      $$PendingMediaTableFilterComposer,
      $$PendingMediaTableOrderingComposer,
      $$PendingMediaTableAnnotationComposer,
      $$PendingMediaTableCreateCompanionBuilder,
      $$PendingMediaTableUpdateCompanionBuilder,
      (
        PendingMediaData,
        BaseReferences<_$AppDatabase, $PendingMediaTable, PendingMediaData>,
      ),
      PendingMediaData,
      PrefetchHooks Function()
    >;
typedef $$CachedMetaTableCreateCompanionBuilder =
    CachedMetaCompanion Function({
      required String key,
      required String payloadJson,
      Value<int> rowid,
    });
typedef $$CachedMetaTableUpdateCompanionBuilder =
    CachedMetaCompanion Function({
      Value<String> key,
      Value<String> payloadJson,
      Value<int> rowid,
    });

class $$CachedMetaTableFilterComposer
    extends Composer<_$AppDatabase, $CachedMetaTable> {
  $$CachedMetaTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get key => $composableBuilder(
    column: $table.key,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedMetaTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedMetaTable> {
  $$CachedMetaTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get key => $composableBuilder(
    column: $table.key,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedMetaTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedMetaTable> {
  $$CachedMetaTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get key =>
      $composableBuilder(column: $table.key, builder: (column) => column);

  GeneratedColumn<String> get payloadJson => $composableBuilder(
    column: $table.payloadJson,
    builder: (column) => column,
  );
}

class $$CachedMetaTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedMetaTable,
          CachedMetaData,
          $$CachedMetaTableFilterComposer,
          $$CachedMetaTableOrderingComposer,
          $$CachedMetaTableAnnotationComposer,
          $$CachedMetaTableCreateCompanionBuilder,
          $$CachedMetaTableUpdateCompanionBuilder,
          (
            CachedMetaData,
            BaseReferences<_$AppDatabase, $CachedMetaTable, CachedMetaData>,
          ),
          CachedMetaData,
          PrefetchHooks Function()
        > {
  $$CachedMetaTableTableManager(_$AppDatabase db, $CachedMetaTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedMetaTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedMetaTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedMetaTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> key = const Value.absent(),
                Value<String> payloadJson = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedMetaCompanion(
                key: key,
                payloadJson: payloadJson,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String key,
                required String payloadJson,
                Value<int> rowid = const Value.absent(),
              }) => CachedMetaCompanion.insert(
                key: key,
                payloadJson: payloadJson,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map((e) => (e.readTable(table), BaseReferences(db, table, e)))
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedMetaTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedMetaTable,
      CachedMetaData,
      $$CachedMetaTableFilterComposer,
      $$CachedMetaTableOrderingComposer,
      $$CachedMetaTableAnnotationComposer,
      $$CachedMetaTableCreateCompanionBuilder,
      $$CachedMetaTableUpdateCompanionBuilder,
      (
        CachedMetaData,
        BaseReferences<_$AppDatabase, $CachedMetaTable, CachedMetaData>,
      ),
      CachedMetaData,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$LocalRoutinesTableTableManager get localRoutines =>
      $$LocalRoutinesTableTableManager(_db, _db.localRoutines);
  $$OutboxEventsTableTableManager get outboxEvents =>
      $$OutboxEventsTableTableManager(_db, _db.outboxEvents);
  $$ExecutionDraftsTableTableManager get executionDrafts =>
      $$ExecutionDraftsTableTableManager(_db, _db.executionDrafts);
  $$PendingMediaTableTableManager get pendingMedia =>
      $$PendingMediaTableTableManager(_db, _db.pendingMedia);
  $$CachedMetaTableTableManager get cachedMeta =>
      $$CachedMetaTableTableManager(_db, _db.cachedMeta);
}
