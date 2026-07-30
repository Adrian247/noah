import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/core/security/mobile_security_policy.dart';

void main() {
  group('MobileSecurityPolicy', () {
    test('defaults are permissive', () {
      const policy = MobileSecurityPolicy.defaults;
      expect(policy.requireAppLock, isFalse);
      expect(policy.allowBiometricUnlock, isTrue);
    });

    test('parses server payload', () {
      final policy = MobileSecurityPolicy.fromMap({
        'require_app_lock': true,
        'allow_biometric_unlock': false,
      });
      expect(policy.requireAppLock, isTrue);
      expect(policy.allowBiometricUnlock, isFalse);
    });

    test('round-trips to map', () {
      const policy = MobileSecurityPolicy(
        requireAppLock: true,
        allowBiometricUnlock: false,
      );
      expect(policy.toMap(), {
        'require_app_lock': true,
        'allow_biometric_unlock': false,
      });
    });
  });
}
