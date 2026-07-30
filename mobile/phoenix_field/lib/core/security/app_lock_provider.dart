import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/security/app_lock_service.dart';
import 'package:phoenix_field/core/security/app_lock_settings.dart';
import 'package:phoenix_field/core/security/mobile_policy_enforcer.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/data/session/session_store.dart';

final appLockServiceProvider = Provider<AppLockService>((ref) {
  return AppLockService();
});

class AppLockController extends StateNotifier<AppLockSettings> {
  AppLockController(this._service) : super(const AppLockSettings());

  final AppLockService _service;

  AppLockService get service => _service;

  Future<void> bootstrap(String scope) async {
    await _service.load(scope: scope);
    _syncState();
  }

  Future<void> enablePin(String pin) async {
    await _service.enablePin(pin);
    _syncState();
  }

  Future<void> disable() async {
    await _service.disable();
    _syncState();
  }

  Future<void> setBiometricEnabled(bool enabled) async {
    await _service.setBiometricEnabled(enabled);
    _syncState();
  }

  void lock() {
    _service.lock();
  }

  void unlock() {
    _service.unlock();
  }

  void refreshFromService() {
    _syncState();
  }

  void _syncState() {
    state = AppLockSettings(
      enabled: _service.isEnabled,
      biometricEnabled: _service.isBiometricEnabled,
      loaded: _service.isLoaded,
      scope: _service.scope,
    );
  }
}

final appLockControllerProvider =
    StateNotifierProvider<AppLockController, AppLockSettings>((ref) {
  return AppLockController(ref.watch(appLockServiceProvider));
});

final mobilePolicyEnforcerProvider = Provider<MobilePolicyEnforcer>((ref) {
  final controller = ref.watch(appLockControllerProvider.notifier);
  return MobilePolicyEnforcer(
    session: ref.watch(sessionStoreProvider),
    appLock: controller.service,
    onSettingsChanged: controller.refreshFromService,
  );
});

final appLockBootstrapProvider = FutureProvider<void>((ref) async {
  await ref.read(sessionBootstrapProvider.future);
  final session = ref.read(sessionStoreProvider);
  final scope = AppLockService.scopeForUser(session.user);
  await ref.read(appLockControllerProvider.notifier).bootstrap(scope);
});

final appLockStateProvider = StateProvider<bool>((ref) => false);

/// Evita bloquear mientras hay flujos externos (cámara, galería, etc.).
final appLockSuppressionProvider = StateProvider<int>((ref) => 0);

Future<T> runWithAppLockSuppressed<T>(
  WidgetRef ref,
  Future<T> Function() action,
) async {
  final notifier = ref.read(appLockSuppressionProvider.notifier);
  notifier.state++;
  try {
    return await action();
  } finally {
    final next = ref.read(appLockSuppressionProvider) - 1;
    notifier.state = next < 0 ? 0 : next;
  }
}

Future<void> reloadAppLockForSession(WidgetRef ref) async {
  final session = ref.read(sessionStoreProvider);
  final scope = AppLockService.scopeForUser(session.user);
  await ref.read(appLockControllerProvider.notifier).bootstrap(scope);
}
