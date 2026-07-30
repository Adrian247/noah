import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/core/security/app_lock_service.dart';
import 'package:phoenix_field/core/security/secure_key_value_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('AppLockService', () {
    test('persists pin and biometric per user scope', () async {
      SharedPreferences.setMockInitialValues({});
      final store = InMemorySecureKeyValueStore();

      final service = AppLockService(secureStorage: store);
      await service.load(scope: 'user_7');
      await service.enablePin('2468');
      await service.setBiometricEnabled(false);

      final reloaded = AppLockService(secureStorage: store);
      await reloaded.load(scope: 'user_7');

      expect(reloaded.isEnabled, isTrue);
      expect(await reloaded.verifyPin('2468'), isTrue);
      expect(reloaded.isBiometricEnabled, isFalse);
    });

    test('scopes settings independently per user', () async {
      SharedPreferences.setMockInitialValues({});
      final store = InMemorySecureKeyValueStore();

      final userA = AppLockService(secureStorage: store);
      await userA.load(scope: 'user_1');
      await userA.enablePin('1111');

      final userB = AppLockService(secureStorage: store);
      await userB.load(scope: 'user_2');
      expect(userB.isEnabled, isFalse);

      await userB.enablePin('2222');

      final reloadA = AppLockService(secureStorage: store);
      await reloadA.load(scope: 'user_1');
      expect(reloadA.isEnabled, isTrue);
      expect(await reloadA.verifyPin('1111'), isTrue);
      expect(await reloadA.verifyPin('2222'), isFalse);
    });

    test('scopeForUser uses id when available', () {
      expect(
        AppLockService.scopeForUser({'id': 99, 'email': 'a@b.com'}),
        'user_99',
      );
    });
  });
}
