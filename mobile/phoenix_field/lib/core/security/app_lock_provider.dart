import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/security/app_lock_service.dart';
import 'package:phoenix_field/core/security/mobile_policy_enforcer.dart';
import 'package:phoenix_field/data/session/session_store.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';

final appLockServiceProvider = Provider<AppLockService>((ref) {
  return AppLockService();
});

final mobilePolicyEnforcerProvider = Provider<MobilePolicyEnforcer>((ref) {
  return MobilePolicyEnforcer(
    session: ref.watch(sessionStoreProvider),
    appLock: ref.watch(appLockServiceProvider),
  );
});

final appLockBootstrapProvider = FutureProvider<void>((ref) async {
  await ref.read(appLockServiceProvider).load();
});

final appLockStateProvider = StateProvider<bool>((ref) {
  final service = ref.watch(appLockServiceProvider);
  return service.isLocked;
});
