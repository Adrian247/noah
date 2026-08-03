import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/config/app_config.dart';
import 'package:phoenix_field/core/network/api_url_resolver.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/push/push_notification_service.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/data/api/auth_api.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/data/session/session_store.dart';
import 'package:uuid/uuid.dart';

class AuthRepository {
  AuthRepository({
    required Ref ref,
    required AuthApi authApi,
    required SessionStore session,
  })  : _ref = ref,
        _authApi = authApi,
        _session = session;

  final Ref _ref;
  final AuthApi _authApi;
  final SessionStore _session;
  final _uuid = const Uuid();

  Future<void> bootstrap() => _session.load();

  bool get isAuthenticated => _session.isAuthenticated;

  Future<void> login({
    required String email,
    required String password,
    String? apiBaseUrl,
  }) async {
    final baseUrl = (apiBaseUrl != null && apiBaseUrl.trim().isNotEmpty)
        ? apiBaseUrl.trim()
        : AppConfig.defaultForPlatform();
    final deviceId = _session.deviceId ?? 'phoenix-${_uuid.v4()}';

    final loginDio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 20),
        receiveTimeout: const Duration(seconds: 30),
        headers: {'Accept': 'application/json'},
      ),
    );

    final response = await loginDio.post<Map<String, dynamic>>(
      '/auth/login',
      data: {
        'email': email.trim(),
        'password': password,
        'device_name': 'phoenix-field',
      },
    );

    final body = response.data ?? {};
    final token = body['token']?.toString();
    final user = body['user'];
    final companiesRaw = body['companies'] as List<dynamic>? ?? [];

    if (token == null || token.isEmpty || user is! Map) {
      throw StateError('Respuesta de login inválida');
    }

    final companies = companiesRaw
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();

    if (companies.isEmpty) {
      throw StateError('El usuario no tiene empresas asignadas');
    }

    final companyId = (companies.first['id'] as num).toInt();

    await _session.saveLogin(
      token: token,
      user: Map<String, dynamic>.from(user),
      companies: companies,
      companyId: companyId,
      deviceId: deviceId,
    );
    await _session.setApiBaseUrl(baseUrl);
    await BackgroundSyncService.enable();
    final push = _ref.read(pushNotificationServiceProvider);
    if (!push.isReady) {
      await push.initialize();
    }
    await push.registerIfAuthenticated();
  }

  Future<void> logout() async {
    await BackgroundSyncService.disable();
    try {
      await _ref.read(pushNotificationServiceProvider).unregister();
    } catch (_) {}
    try {
      await _authApi.logout();
    } catch (_) {
      // Ignorar errores de red al cerrar sesión local.
    }
    await _ref.read(syncRepositoryProvider).purgeAllLocalData();
    _ref.read(appLockControllerProvider.notifier).unlock();
    _ref.read(appLockStateProvider.notifier).state = false;
    await _session.clear();
  }

  Future<void> switchCompany(int companyId) async {
    final valid = _session.companies.any((c) => c['id'] == companyId);
    if (!valid) {
      throw ArgumentError('Empresa no disponible para este usuario');
    }
    if (_session.companyId == companyId) {
      return;
    }
    await _session.setCompanyId(companyId);
  }

  String get apiBaseUrl =>
      AppConfig.resolveApiBaseUrl(persisted: _session.apiBaseUrl);

  String? get userAvatarUrl {
    final raw = _session.user?['avatar_url']?.toString();
    return ApiUrlResolver.resolveAssetUrl(raw, apiBaseUrl);
  }

  Future<void> refreshProfile() async {
    final body = await _authApi.me();
    final user = body['user'];
    if (user is Map) {
      await _session.updateUser(Map<String, dynamic>.from(user));
    }
    final companies = body['companies'];
    if (companies is List) {
      await _session.updateCompanies(
        companies.map((e) => Map<String, dynamic>.from(e as Map)).toList(),
      );
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref: ref,
    authApi: ref.watch(authApiProvider),
    session: ref.watch(sessionStoreProvider),
  );
});

final sessionBootstrapProvider = FutureProvider<void>((ref) async {
  await ref.read(authRepositoryProvider).bootstrap();
  ref.read(sessionVersionProvider.notifier).state++;
});
