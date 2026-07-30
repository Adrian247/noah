import 'package:flutter/material.dart';
import 'package:phoenix_field/core/security/app_lock_service.dart';
import 'package:phoenix_field/core/security/mobile_security_policy.dart';
import 'package:phoenix_field/data/session/session_store.dart';
import 'package:phoenix_field/features/security/set_pin_dialog.dart';

class MobilePolicyEnforcer {
  MobilePolicyEnforcer({
    required SessionStore session,
    required AppLockService appLock,
    this.onSettingsChanged,
  })  : _session = session,
        _appLock = appLock;

  final SessionStore _session;
  final AppLockService _appLock;
  final void Function()? onSettingsChanged;

  MobileSecurityPolicy get policy => _session.mobilePolicyForCurrentCompany;

  Future<void> applyAfterSync(Map<String, dynamic>? pull) async {
    final mobilePolicy = pull?['mobile_policy'];
    if (mobilePolicy is Map) {
      await _session.updateCurrentCompanyMobilePolicy(
        Map<String, dynamic>.from(mobilePolicy),
      );
    }
    await applyLocalRules();
  }

  Future<void> applyLocalRules() async {
    final policy = this.policy;

    if (!policy.allowBiometricUnlock && _appLock.isBiometricEnabled) {
      await _appLock.setBiometricEnabled(false);
      onSettingsChanged?.call();
    }
  }

  /// Devuelve `true` si el usuario configuró PIN cuando la política lo exige.
  Future<bool> ensureRequiredPin(BuildContext context) async {
    await applyLocalRules();

    if (!_appLock.isEnabled && policy.requireAppLock) {
      if (!context.mounted) {
        return false;
      }

      final pin = await showDialog<String>(
        context: context,
        barrierDismissible: false,
        builder: (context) => const SetPinDialog(
          title: 'PIN obligatorio',
          message:
              'Tu empresa exige bloqueo con PIN en la app de campo. Configúralo para continuar.',
          allowCancel: false,
        ),
      );

      if (pin == null) {
        return false;
      }

      await _appLock.enablePin(pin);
      onSettingsChanged?.call();
    }

    return !policy.requireAppLock || _appLock.isEnabled;
  }
}
