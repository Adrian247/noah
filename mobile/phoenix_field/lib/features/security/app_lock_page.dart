import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/shared/widgets/phoenix_brand_logo.dart';

class AppLockPage extends ConsumerStatefulWidget {
  const AppLockPage({super.key});

  @override
  ConsumerState<AppLockPage> createState() => _AppLockPageState();
}

class _AppLockPageState extends ConsumerState<AppLockPage> {
  final _pinController = TextEditingController();
  String? _error;
  bool _loading = false;
  bool _canUseBiometrics = false;
  bool _autoPromptAttempted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_checkBiometrics());
    });
  }

  Future<void> _checkBiometrics() async {
    final service = ref.read(appLockControllerProvider.notifier).service;
    final canUse = await service.canUseBiometrics();
    if (!mounted) {
      return;
    }
    setState(() => _canUseBiometrics = canUse && service.isBiometricEnabled);
    if (_canUseBiometrics && !_autoPromptAttempted) {
      _autoPromptAttempted = true;
      await _unlockWithBiometrics();
    }
  }

  @override
  void dispose() {
    _pinController.dispose();
    super.dispose();
  }

  Future<void> _unlockWithPin() async {
    final pin = _pinController.text.trim();
    if (pin.isEmpty) {
      setState(() => _error = 'Ingresa tu PIN');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    final service = ref.read(appLockControllerProvider.notifier).service;
    final ok = await service.unlockWithPin(pin);
    if (!mounted) {
      return;
    }

    if (ok) {
      unlockAppSession(ref);
      _pinController.clear();
    } else {
      setState(() => _error = 'PIN incorrecto');
    }

    setState(() => _loading = false);
  }

  Future<void> _unlockWithBiometrics() async {
    if (_loading) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      // La UI del sistema pone la app en paused/resumed: hay que suprimir el re-bloqueo.
      final error = await runWithAppLockSuppressed(ref, () async {
        final service = ref.read(appLockControllerProvider.notifier).service;
        return service.unlockWithBiometrics();
      });

      if (!mounted) {
        return;
      }

      if (error == null) {
        unlockAppSession(ref);
      } else {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 360),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(
                      child: PhoenixBrandLogo(
                        size: PhoenixBrandLogoSize.md,
                        animated: true,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Icon(
                      Icons.lock_outline,
                      size: 32,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 12),
                    const PhoenixBrandWordmark(
                      title: 'Phoenix Campo',
                      subtitle: 'Pyro Systems',
                      compact: true,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Ingresa tu PIN para continuar',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .onSurface
                                .withValues(alpha: 0.7),
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    TextField(
                      controller: _pinController,
                      obscureText: true,
                      keyboardType: TextInputType.number,
                      maxLength: 6,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 24, letterSpacing: 8),
                      decoration: const InputDecoration(
                        labelText: 'PIN',
                        counterText: '',
                      ),
                      onSubmitted: (_) => _unlockWithPin(),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _error!,
                        style: TextStyle(color: Theme.of(context).colorScheme.error),
                        textAlign: TextAlign.center,
                      ),
                    ],
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _loading ? null : _unlockWithPin,
                      child: _loading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Desbloquear'),
                    ),
                    if (_canUseBiometrics) ...[
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: _loading ? null : _unlockWithBiometrics,
                        icon: const Icon(Icons.fingerprint),
                        label: const Text('Usar biometría'),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
