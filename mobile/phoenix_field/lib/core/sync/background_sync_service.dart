import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:phoenix_field/core/sync/background_sync_worker.dart';
import 'package:phoenix_field/core/sync/sync_runner.dart';
import 'package:workmanager/workmanager.dart';

class BackgroundSyncService {
  const BackgroundSyncService._();

  static bool get isSupported =>
      !kIsWeb && (Platform.isAndroid || Platform.isIOS);

  static Future<void> initialize() async {
    if (!isSupported) {
      return;
    }
    await Workmanager().initialize(callbackDispatcher);
  }

  static Future<void> enable() async {
    if (!isSupported) {
      return;
    }
    await Workmanager().registerPeriodicTask(
      backgroundSyncUniqueName,
      backgroundSyncTaskName,
      frequency: const Duration(minutes: 15),
      constraints: Constraints(
        networkType: NetworkType.connected,
      ),
      existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
    );
  }

  static Future<void> disable() async {
    if (!isSupported) {
      return;
    }
    await Workmanager().cancelByUniqueName(backgroundSyncUniqueName);
    await Workmanager().cancelAll();
  }

  static Future<void> requestImmediate() async {
    if (isSupported) {
      await Workmanager().registerOneOffTask(
        'phoenix-sync-now-${DateTime.now().millisecondsSinceEpoch}',
        backgroundSyncTaskName,
        constraints: Constraints(networkType: NetworkType.connected),
        existingWorkPolicy: ExistingWorkPolicy.keep,
      );
      return;
    }
    await runBackgroundSyncIfAuthenticated();
  }
}
