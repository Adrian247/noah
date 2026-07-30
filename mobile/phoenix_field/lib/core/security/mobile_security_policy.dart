class MobileSecurityPolicy {
  const MobileSecurityPolicy({
    this.requireAppLock = false,
    this.allowBiometricUnlock = true,
  });

  final bool requireAppLock;
  final bool allowBiometricUnlock;

  static const defaults = MobileSecurityPolicy();

  factory MobileSecurityPolicy.fromMap(Map<String, dynamic>? map) {
    if (map == null || map.isEmpty) {
      return defaults;
    }

    return MobileSecurityPolicy(
      requireAppLock: _asBool(map['require_app_lock']),
      allowBiometricUnlock: _asBool(map['allow_biometric_unlock'], fallback: true),
    );
  }

  Map<String, dynamic> toMap() => {
        'require_app_lock': requireAppLock,
        'allow_biometric_unlock': allowBiometricUnlock,
      };

  static bool _asBool(dynamic value, {bool fallback = false}) {
    if (value is bool) {
      return value;
    }
    if (value is num) {
      return value != 0;
    }
    if (value is String) {
      final normalized = value.toLowerCase();
      if (normalized == 'true' || normalized == '1') {
        return true;
      }
      if (normalized == 'false' || normalized == '0') {
        return false;
      }
    }
    return fallback;
  }
}
