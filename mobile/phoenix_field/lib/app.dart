import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/push/push_notification_service.dart';
import 'package:phoenix_field/core/routing/app_router.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/core/system_enter/system_enter_provider.dart';
import 'package:phoenix_field/core/theme/phoenix_theme.dart';
import 'package:phoenix_field/core/theme/theme_mode_provider.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/features/security/app_lock_page.dart';
import 'package:phoenix_field/features/security/set_pin_dialog.dart';
import 'package:phoenix_field/shared/widgets/phoenix_brand_logo.dart';
import 'package:phoenix_field/shared/widgets/phoenix_system_enter_overlay.dart';

class PhoenixFieldApp extends ConsumerStatefulWidget {
  const PhoenixFieldApp({super.key});

  @override
  ConsumerState<PhoenixFieldApp> createState() => _PhoenixFieldAppState();
}

class _PhoenixFieldAppState extends ConsumerState<PhoenixFieldApp>
    with WidgetsBindingObserver {
  bool _pendingLockOnResume = false;
  bool _awaitingRequiredPinSetup = false;
  bool _savingRequiredPin = false;
  String? _requiredPinError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _bootstrap();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _bootstrap() async {
    await ref.read(sessionBootstrapProvider.future);
    await ref.read(appLockBootstrapProvider.future);
    await ref.read(pushNotificationServiceProvider).initialize();
    if (!ref.read(authRepositoryProvider).isAuthenticated) {
      return;
    }

    await BackgroundSyncService.enable();
    await ref.read(pushNotificationServiceProvider).registerIfAuthenticated();
    try {
      await ref.read(syncRepositoryProvider).syncNow();
    } catch (_) {
      // Sin red: se aplica la política guardada en sesión.
    }
    await _applySecurityGate();
  }

  /// Aplica política de la empresa: exigir PIN y, opcionalmente, bloquear sesión.
  ///
  /// [lockIfPinEnabled] solo en arranque/login. En resume/sync no debe re-bloquear
  /// una sesión ya desbloqueada (p. ej. al volver de cámara/galería).
  Future<void> _applySecurityGate({bool lockIfPinEnabled = true}) async {
    if (!ref.read(authRepositoryProvider).isAuthenticated) {
      return;
    }

    await ref.read(mobilePolicyEnforcerProvider).applyLocalRules();
    final policy = ref.read(mobilePolicyEnforcerProvider).policy;
    final pinEnabled = ref.read(appLockControllerProvider).enabled;

    if (pinEnabled) {
      if (lockIfPinEnabled) {
        lockAppSession(ref);
      }
      if (mounted) {
        setState(() {
          _awaitingRequiredPinSetup = false;
          _requiredPinError = null;
        });
      }
      return;
    }

    if (policy.requireAppLock && mounted) {
      setState(() {
        _awaitingRequiredPinSetup = true;
        _requiredPinError = null;
      });
    }
  }

  Future<void> _saveRequiredPin(String pin) async {
    if (_savingRequiredPin) {
      return;
    }
    setState(() {
      _savingRequiredPin = true;
      _requiredPinError = null;
    });

    try {
      await ref.read(appLockControllerProvider.notifier).enablePin(pin);
      if (!mounted) {
        return;
      }
      setState(() => _awaitingRequiredPinSetup = false);
      lockAppSession(ref);
    } catch (e) {
      if (mounted) {
        setState(() => _requiredPinError = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _savingRequiredPin = false);
      }
    }
  }

  Future<void> _logoutFromRequiredPin() async {
    await ref.read(authRepositoryProvider).logout();
    ref.invalidate(dioProvider);
    unlockAppSession(ref);
    ref.read(authNavigationVersionProvider.notifier).state++;
    if (mounted) {
      setState(() {
        _awaitingRequiredPinSetup = false;
        _requiredPinError = null;
      });
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final authed = ref.read(authRepositoryProvider).isAuthenticated;
    if (!authed) {
      return;
    }

    final suppressed = shouldIgnoreLockOnResume(ref);
    final lockEnabled = ref.read(appLockControllerProvider).enabled;

    if (state == AppLifecycleState.paused || state == AppLifecycleState.hidden) {
      if (!suppressed && lockEnabled) {
        _pendingLockOnResume = true;
      }
      return;
    }

    if (state == AppLifecycleState.resumed) {
      if (_pendingLockOnResume && !shouldIgnoreLockOnResume(ref)) {
        _pendingLockOnResume = false;
        lockAppSession(ref);
      } else {
        _pendingLockOnResume = false;
      }
      unawaited(_syncOnResume());
    }
  }

  Future<void> _syncOnResume() async {
    if (ref.read(appLockStateProvider) || _awaitingRequiredPinSetup) {
      return;
    }
    // Cámara/galería/biometría: no sync ni gate (evitar PIN al volver).
    if (shouldIgnoreLockOnResume(ref)) {
      return;
    }
    try {
      await ref.read(syncRepositoryProvider).syncNow();
      // Tras sync solo actualiza política; no re-bloquea sesión activa.
      await _applySecurityGate(lockIfPinEnabled: false);
    } catch (_) {
      // Sin conexión: la cola sigue pendiente.
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(appRouterProvider);
    final locked = ref.watch(appLockStateProvider);
    final authed = ref.watch(authRepositoryProvider).isAuthenticated;
    final systemEnter = ref.watch(systemEnterProvider);
    final themeMode = ref.watch(themeModeControllerProvider);

    return MaterialApp.router(
      title: 'Phoenix Campo',
      debugShowCheckedModeBanner: false,
      theme: PhoenixTheme.light,
      darkTheme: PhoenixTheme.dark,
      themeMode: themeMode,
      locale: const Locale('es', 'MX'),
      supportedLocales: const [
        Locale('es', 'MX'),
        Locale('en'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: router,
      builder: (context, child) {
        Widget content = child ?? const SizedBox.shrink();

        // Formulario de PIN obligatorio embebido (no depende de Navigator/showDialog).
        if (_awaitingRequiredPinSetup && authed && !locked) {
          content = PopScope(
            canPop: false,
            child: Stack(
              fit: StackFit.expand,
              children: [
                IgnorePointer(ignoring: true, child: content),
                Positioned.fill(
                  child: Material(
                    color: Theme.of(context).scaffoldBackgroundColor,
                    child: SafeArea(
                      child: Center(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(24),
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 420),
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
                                  Icons.security,
                                  size: 32,
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                                const SizedBox(height: 16),
                                IgnorePointer(
                                  ignoring: _savingRequiredPin,
                                  child: SetPinForm(
                                    title: 'PIN obligatorio',
                                    message:
                                        'Tu empresa exige bloqueo con PIN en la app de campo. Configúralo para continuar.',
                                    submitLabel: _savingRequiredPin ? 'Guardando…' : 'Guardar y continuar',
                                    allowCancel: false,
                                    onSubmit: _saveRequiredPin,
                                  ),
                                ),
                                if (_requiredPinError != null) ...[
                                  const SizedBox(height: 12),
                                  Text(
                                    _requiredPinError!,
                                    style: TextStyle(color: Theme.of(context).colorScheme.error),
                                    textAlign: TextAlign.center,
                                  ),
                                ],
                                const SizedBox(height: 16),
                                TextButton(
                                  onPressed: _savingRequiredPin ? null : _logoutFromRequiredPin,
                                  child: const Text('Cerrar sesión'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          );
        }

        if (locked) {
          content = PopScope(
            canPop: false,
            child: Stack(
              fit: StackFit.expand,
              children: [
                IgnorePointer(ignoring: true, child: content),
                const Positioned.fill(child: AppLockPage()),
              ],
            ),
          );
        }

        if (systemEnter.active) {
          content = Stack(
            children: [
              content,
              Positioned.fill(
                child: PhoenixSystemEnterOverlay(message: systemEnter.message),
              ),
            ],
          );
        }

        return content;
      },
    );
  }
}
