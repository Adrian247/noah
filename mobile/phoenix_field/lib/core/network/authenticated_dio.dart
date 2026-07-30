import 'package:dio/dio.dart';
import 'package:phoenix_field/core/config/app_config.dart';
import 'package:phoenix_field/data/session/session_store.dart';

Dio createAuthenticatedDio(SessionStore session) {
  final baseUrl = AppConfig.resolveApiBaseUrl(persisted: session.apiBaseUrl);
  final dio = Dio(
    BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = session.token;
        final companyId = session.companyId;
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        if (companyId != null && !options.path.contains('/auth/login')) {
          options.headers['X-Company-Id'] = companyId.toString();
        }
        handler.next(options);
      },
    ),
  );

  return dio;
}
