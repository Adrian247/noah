import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/routing/app_router.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/core/security/mobile_policy_enforcer.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/core/theme/phoenix_theme.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/features/security/app_lock_page.dart';

class PhoenixFieldApp extends ConsumerStatefulWidget {
  const PhoenixFieldApp({super.key});

  @override
  ConsumerState<PhoenixFieldApp> createState() => _PhoenixFieldAppState();
}

class _PhoenixFieldAppState extends ConsumerState<PhoenixFieldApp>
    with WidgetsBindingObserver {
  bool _policyPromptPending = false;

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
    if (ref.read(authRepositoryProvider).isAuthenticated) {
      await BackgroundSyncService.enable();
      try {
        await ref.read(syncRepositoryProvider).syncNow();
      } catch (_) {
        // Sin red: se aplica la política guardada en sesión.
      }
      await ref.read(mobilePolicyEnforcerProvider).applyLocalRules();
      _lockIfNeeded();
      _policyPromptPending = true;
    }
  }

  Future<void> _ensurePolicyIfNeeded() async {
    if (!ref.read(authRepositoryProvider).isAuthenticated) {
      return;
    }
    if (!mounted) {
      return;
    }
    final ok = await ref.read(mobilePolicyEnforcerProvider).ensureRequiredPin(context);
    if (!ok && mounted) {
      await ref.read(authRepositoryProvider).logout();
      ref.invalidate(dioProvider);
      ref.read(appLockStateProvider.notifier).state = false;
      ref.read(authNavigationVersionProvider.notifier).state++;
    }
  }

  void _lockIfNeeded() {
    final lock = ref.read(appLockServiceProvider);
    if (ref.read(authRepositoryProvider).isAuthenticated && lock.isEnabled) {
      lock.lock();
      ref.read(appLockStateProvider.notifier).state = true;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final authed = ref.read(authRepositoryProvider).isAuthenticated;
    if (!authed) {
      return;
    }

    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden) {
      _lockIfNeeded();
      return;
    }

    if (state == AppLifecycleState.resumed) {
      unawaited(_syncOnResume());
    }
  }

  Future<void> _syncOnResume() async {
    if (ref.read(appLockStateProvider)) {
      return;
    }
    try {
      await ref.read(syncRepositoryProvider).syncNow();
    } catch (_) {
      // Sin conexión: la cola sigue pendiente.
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(appRouterProvider);
    final locked = ref.watch(appLockStateProvider);
    final authed = ref.watch(authRepositoryProvider).isAuthenticated;

    if (_policyPromptPending && authed && !locked) {
      _policyPromptPending = false;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _ensurePolicyIfNeeded();
      });
    }

    return MaterialApp.router(
      title: 'Phoenix Campo',
      debugShowCheckedModeBanner: false,
      theme: PhoenixTheme.dark,
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
        if (!locked) {
          return child ?? const SizedBox.shrink();
        }
        return Stack(
          children: [
            if (child != null) child,
            const Positioned.fill(child: AppLockPage()),
          ],
        );
      },
    );
  }
}
