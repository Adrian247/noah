import 'package:dio/dio.dart';
import 'package:phoenix_field/core/config/app_config.dart';

class ApiConnectivity {
  const ApiConnectivity._();

  static Future<String?> probeWorkingBaseUrl({
    List<String>? candidates,
    Duration timeout = const Duration(seconds: 4),
  }) async {
    for (final baseUrl in candidates ?? AppConfig.probeCandidates()) {
      try {
        final dio = Dio(
          BaseOptions(
            baseUrl: baseUrl,
            connectTimeout: timeout,
            receiveTimeout: timeout,
            headers: {'Accept': 'application/json'},
          ),
        );
        final response = await dio.get<dynamic>('/health');
        if (response.statusCode == 200) {
          return baseUrl;
        }
      } catch (_) {
        continue;
      }
    }
    return null;
  }

  static Future<void> validateOrThrow(String baseUrl) async {
    final dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 8),
        receiveTimeout: const Duration(seconds: 8),
        headers: {'Accept': 'application/json'},
      ),
    );
    await dio.get<dynamic>('/health');
  }
}
