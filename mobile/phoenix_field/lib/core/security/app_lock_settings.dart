class AppLockSettings {
  const AppLockSettings({
    this.enabled = false,
    this.biometricEnabled = false,
    this.loaded = false,
    this.scope = 'anonymous',
  });

  final bool enabled;
  final bool biometricEnabled;
  final bool loaded;
  final String scope;

  AppLockSettings copyWith({
    bool? enabled,
    bool? biometricEnabled,
    bool? loaded,
    String? scope,
  }) {
    return AppLockSettings(
      enabled: enabled ?? this.enabled,
      biometricEnabled: biometricEnabled ?? this.biometricEnabled,
      loaded: loaded ?? this.loaded,
      scope: scope ?? this.scope,
    );
  }
}
