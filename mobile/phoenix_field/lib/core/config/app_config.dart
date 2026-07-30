import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

class AppConfig {
  const AppConfig({required this.apiBaseUrl});

  final String apiBaseUrl;

  static const int apiPort = 8888;
  static const String apiPath = '/api/v1';

  static String url(String host) => 'http://$host:$apiPort$apiPath';

  /// URLs a probar al iniciar (orden de prioridad).
  static List<String> probeCandidates() {
    const fromEnv = String.fromEnvironment('API_BASE_URL');
    if (fromEnv.isNotEmpty) {
      return [fromEnv];
    }

    if (kIsWeb) {
      return [url('127.0.0.1'), url('localhost')];
    }
    if (Platform.isAndroid) {
      return [
        url('10.0.2.2'),
        url('127.0.0.1'),
      ];
    }
    return [
      url('127.0.0.1'),
      url('localhost'),
    ];
  }

  static String defaultForPlatform() => probeCandidates().first;

  static bool shouldDiscardPersistedUrl(String value) {
    if (kIsWeb || Platform.isAndroid) {
      return false;
    }
    return value.contains('10.0.2.2');
  }

  static String resolveApiBaseUrl({String? persisted}) {
    const fromEnv = String.fromEnvironment('API_BASE_URL');
    if (fromEnv.isNotEmpty) {
      return fromEnv;
    }

    if (persisted != null && persisted.trim().isNotEmpty) {
      final saved = persisted.trim();
      if (shouldDiscardPersistedUrl(saved)) {
        return defaultForPlatform();
      }
      return saved;
    }

    return defaultForPlatform();
  }

  static AppConfig get appConfig => AppConfig(apiBaseUrl: defaultForPlatform());
}
