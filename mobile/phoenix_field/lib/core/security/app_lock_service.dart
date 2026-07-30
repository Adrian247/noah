import 'dart:convert';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'package:local_auth_android/local_auth_android.dart';
import 'package:local_auth_darwin/local_auth_darwin.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppLockService {
  AppLockService({
    FlutterSecureStorage? secureStorage,
    LocalAuthentication? localAuth,
  })  : _secureStorage = secureStorage ?? const FlutterSecureStorage(),
        _localAuth = localAuth ?? LocalAuthentication();

  static const _enabledKey = 'phoenix_app_lock_enabled';
  static const _biometricKey = 'phoenix_app_lock_biometric';
  static const _pinHashKey = 'phoenix_app_lock_pin_hash';
  static const _pinSaltKey = 'phoenix_app_lock_pin_salt';

  static const _authMessages = <AuthMessages>[
    AndroidAuthMessages(
      signInTitle: 'Desbloqueo biométrico',
      cancelButton: 'Cancelar',
      biometricHint: 'Toca el sensor',
      biometricNotRecognized: 'No reconocido. Intenta de nuevo.',
      biometricRequiredTitle: 'Biometría requerida',
      biometricSuccess: 'Verificado',
      goToSettingsButton: 'Configuración',
      goToSettingsDescription: 'Configura huella o rostro en el sistema.',
      deviceCredentialsRequiredTitle: 'Desbloqueo requerido',
      deviceCredentialsSetupDescription: 'Configura un método de desbloqueo en el sistema.',
    ),
    IOSAuthMessages(
      cancelButton: 'Cancelar',
      goToSettingsButton: 'Configuración',
      goToSettingsDescription: 'Configura Face ID o Touch ID en Ajustes.',
      lockOut: 'Biometría bloqueada. Usa tu PIN de la app.',
    ),
  ];

  final FlutterSecureStorage _secureStorage;
  final LocalAuthentication _localAuth;

  bool _enabled = false;
  bool _biometricEnabled = false;
  bool _locked = false;
  bool _loaded = false;

  bool get isLoaded => _loaded;
  bool get isEnabled => _enabled;
  bool get isBiometricEnabled => _biometricEnabled;
  bool get isLocked => _locked;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _enabled = prefs.getBool(_enabledKey) ?? false;
    _biometricEnabled = prefs.getBool(_biometricKey) ?? false;
    _loaded = true;
  }

  Future<bool> canUseBiometrics() async {
    if (kIsWeb) {
      return false;
    }
    try {
      if (!await _localAuth.isDeviceSupported()) {
        return false;
      }
      final enrolled = await _localAuth.getAvailableBiometrics();
      return enrolled.isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  Future<void> enablePin(String pin) async {
    _validatePin(pin);
    final salt = _generateSalt();
    final hash = _hashPin(pin, salt);
    await _secureStorage.write(key: _pinHashKey, value: hash);
    await _secureStorage.write(key: _pinSaltKey, value: salt);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_enabledKey, true);
    _enabled = true;
    _locked = false;
  }

  Future<void> disable() async {
    await _secureStorage.delete(key: _pinHashKey);
    await _secureStorage.delete(key: _pinSaltKey);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_enabledKey, false);
    await prefs.setBool(_biometricKey, false);
    _enabled = false;
    _biometricEnabled = false;
    _locked = false;
  }

  Future<void> setBiometricEnabled(bool enabled) async {
    if (!enabled) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_biometricKey, false);
      _biometricEnabled = false;
      return;
    }

    if (!await canUseBiometrics()) {
      throw StateError(
        'No hay biometría disponible. Registra huella o rostro en Ajustes del teléfono.',
      );
    }

    final error = await _promptBiometric(reason: 'Confirma para activar biometría');
    if (error != null) {
      throw StateError(error);
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_biometricKey, true);
    _biometricEnabled = true;
  }

  Future<bool> verifyPin(String pin) async {
    final storedHash = await _secureStorage.read(key: _pinHashKey);
    final salt = await _secureStorage.read(key: _pinSaltKey);
    if (storedHash == null || salt == null) {
      return false;
    }
    return storedHash == _hashPin(pin, salt);
  }

  Future<bool> unlockWithPin(String pin) async {
    final ok = await verifyPin(pin);
    if (ok) {
      _locked = false;
    }
    return ok;
  }

  /// Devuelve `null` si desbloqueó correctamente; si no, mensaje de error.
  Future<String?> unlockWithBiometrics() async {
    if (!_enabled || !_biometricEnabled) {
      return 'Biometría no está activada';
    }
    return _promptBiometric(reason: 'Desbloquea Phoenix Campo');
  }

  Future<String?> _promptBiometric({required String reason}) async {
    try {
      if (!await canUseBiometrics()) {
        return 'No hay huella ni rostro registrados en el dispositivo';
      }

      final ok = await _localAuth.authenticate(
        localizedReason: reason,
        authMessages: _authMessages,
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: true,
        ),
      );
      if (ok) {
        _locked = false;
        return null;
      }
      return 'Autenticación cancelada';
    } on PlatformException catch (e) {
      if (e.code == 'NotAvailable') {
        return 'Biometría no disponible en este dispositivo';
      }
      if (e.code == 'NotEnrolled') {
        return 'Registra huella o rostro en Ajustes del teléfono';
      }
      if (e.code == 'LockedOut' || e.code == 'PermanentlyLockedOut') {
        return 'Biometría bloqueada temporalmente. Usa tu PIN.';
      }
      return e.message ?? 'No se pudo verificar biometría (${e.code})';
    } catch (_) {
      return 'No se pudo verificar biometría';
    }
  }

  void lock() {
    if (_enabled) {
      _locked = true;
    }
  }

  void unlock() {
    _locked = false;
  }

  void _validatePin(String pin) {
    if (!RegExp(r'^\d{4,6}$').hasMatch(pin)) {
      throw ArgumentError('El PIN debe tener entre 4 y 6 dígitos');
    }
  }

  String _generateSalt() {
    final bytes = List<int>.generate(16, (i) => (DateTime.now().microsecondsSinceEpoch + i) % 256);
    return base64Url.encode(bytes);
  }

  String _hashPin(String pin, String salt) {
    final bytes = utf8.encode('$salt:$pin');
    return sha256.convert(bytes).toString();
  }
}
