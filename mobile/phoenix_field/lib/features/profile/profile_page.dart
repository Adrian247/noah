import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/core/security/mobile_policy_enforcer.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/features/security/set_pin_dialog.dart';
import 'package:phoenix_field/shared/widgets/user_avatar.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  bool _syncing = false;
  bool _canUseBiometrics = false;

  @override
  void initState() {
    super.initState();
    _loadBiometrics();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _refreshProfile();
    });
  }

  Future<void> _refreshProfile() async {
    try {
      await ref.read(authRepositoryProvider).refreshProfile();
      ref.read(profileRefreshProvider.notifier).state++;
    } catch (_) {
      // Sin red: se muestra lo guardado en sesión.
    }
  }

  Future<void> _loadBiometrics() async {
    final canUse = await ref.read(appLockControllerProvider.notifier).service.canUseBiometrics();
    if (mounted) {
      setState(() => _canUseBiometrics = canUse);
    }
  }

  Future<void> _syncNow() async {
    setState(() => _syncing = true);
    try {
      await ref.read(syncRepositoryProvider).syncNow();
      await ref.read(mobilePolicyEnforcerProvider).applyLocalRules();
      await BackgroundSyncService.requestImmediate();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Sincronización completada')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _syncing = false);
      }
    }
  }

  Future<void> _toggleAppLock(bool enabled) async {
    final controller = ref.read(appLockControllerProvider.notifier);
    final policy = ref.read(mobilePolicyEnforcerProvider).policy;

    if (!enabled && policy.requireAppLock) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Tu empresa exige bloqueo con PIN. No puedes desactivarlo.'),
          ),
        );
      }
      return;
    }

    if (enabled) {
      final pin = await showDialog<String>(
        context: context,
        builder: (context) => const SetPinDialog(),
      );
      if (pin == null) {
        return;
      }
      try {
        await controller.enablePin(pin);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Bloqueo con PIN activado')),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error: $e')),
          );
        }
      }
    } else {
      await controller.disable();
      ref.read(appLockStateProvider.notifier).state = false;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Bloqueo desactivado')),
        );
      }
    }
    setState(() {});
  }

  Future<void> _changePin() async {
    final pin = await showDialog<String>(
      context: context,
      builder: (context) => const SetPinDialog(),
    );
    if (pin == null) {
      return;
    }
    try {
      await ref.read(appLockControllerProvider.notifier).enablePin(pin);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('PIN actualizado')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _toggleBiometric(bool enabled) async {
    final policy = ref.read(mobilePolicyEnforcerProvider).policy;
    if (enabled && !policy.allowBiometricUnlock) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Tu empresa no permite desbloqueo biométrico.'),
          ),
        );
      }
      return;
    }

    try {
      await runWithAppLockSuppressed(
        ref,
        () => ref.read(appLockControllerProvider.notifier).setBiometricEnabled(enabled),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  Future<void> _switchCompany(int? companyId) async {
    final session = ref.read(sessionStoreProvider);
    if (companyId == null || companyId == session.companyId) {
      return;
    }

    final sync = ref.read(syncRepositoryProvider);
    final pendingEvents = await sync.countPendingOutbox();
    final pendingMedia = await ref.read(mediaRepositoryProvider).countPending();

    if (pendingEvents > 0 || pendingMedia > 0) {
      if (!mounted) {
        return;
      }
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Cambiar empresa'),
          content: Text(
            'Hay $pendingEvents evento(s) y $pendingMedia archivo(s) pendientes de sync. '
            'Al cambiar de empresa se limpiará la caché local de la empresa actual.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancelar'),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Continuar'),
            ),
          ],
        ),
      );
      if (confirmed != true) {
        return;
      }
    }

    setState(() => _syncing = true);
    try {
      await sync.resetLocalData();
      await ref.read(authRepositoryProvider).switchCompany(companyId);
      ref.invalidate(dioProvider);
      await sync.syncNow();
      await ref.read(mobilePolicyEnforcerProvider).applyLocalRules();
      if (mounted) {
        await ref.read(mobilePolicyEnforcerProvider).ensureRequiredPin(context);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Empresa cambiada y datos sincronizados')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cambiar empresa: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _syncing = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.watch(profileRefreshProvider);
    final session = ref.watch(sessionStoreProvider);
    final auth = ref.watch(authRepositoryProvider);
    final lock = ref.watch(appLockControllerProvider);
    final policy = ref.watch(mobilePolicyEnforcerProvider).policy;
    final userName = session.user?['name']?.toString() ?? 'Técnico';
    final email = session.user?['email']?.toString() ?? '';
    final avatarUrl = auth.userAvatarUrl;
    final companies = session.companies;

    return Scaffold(
      appBar: AppBar(title: const Text('Perfil')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: ListTile(
              leading: UserAvatar(
                name: userName,
                avatarUrl: avatarUrl,
                radius: 28,
              ),
              title: Text(userName),
              subtitle: Text(email),
            ),
          ),
          if (companies.length > 1) ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: session.companyId,
              decoration: const InputDecoration(
                labelText: 'Empresa activa',
                prefixIcon: Icon(Icons.business_outlined),
              ),
              items: [
                for (final company in companies)
                  DropdownMenuItem<int>(
                    value: (company['id'] as num).toInt(),
                    child: Text(company['name']?.toString() ?? 'Empresa'),
                  ),
              ],
              onChanged: _syncing ? null : _switchCompany,
            ),
          ] else ...[
            const SizedBox(height: 12),
            ListTile(
              leading: const Icon(Icons.business_outlined),
              title: const Text('Empresa'),
              subtitle: Text(
                companies.isNotEmpty
                    ? companies.first['name']?.toString() ?? '—'
                    : '—',
              ),
            ),
          ],
          const SizedBox(height: 16),
          ListTile(
            title: const Text('Device ID'),
            subtitle: Text(session.deviceId ?? '—'),
          ),
          ListTile(
            title: const Text('API'),
            subtitle: Text(ref.read(authRepositoryProvider).apiBaseUrl),
          ),
          ListTile(
            title: const Text('Sync en segundo plano'),
            subtitle: Text(
              BackgroundSyncService.isSupported
                  ? 'Activo cada ~15 min con red (Android/iOS)'
                  : 'En desktop: sync al volver a la app',
            ),
          ),
          const Divider(),
          Text('Seguridad', style: Theme.of(context).textTheme.titleSmall),
          if (policy.requireAppLock)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                'Tu empresa exige bloqueo con PIN en este dispositivo.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                    ),
              ),
            ),
          const SizedBox(height: 8),
          SwitchListTile(
            title: const Text('Bloqueo con PIN'),
            subtitle: const Text('Al volver a la app o al iniciar con sesión activa'),
            value: lock.enabled,
            onChanged: policy.requireAppLock && lock.enabled
                ? null
                : _toggleAppLock,
          ),
          if (lock.enabled) ...[
            ListTile(
              title: const Text('Cambiar PIN'),
              trailing: const Icon(Icons.chevron_right),
              onTap: _changePin,
            ),
            if (_canUseBiometrics && policy.allowBiometricUnlock)
              SwitchListTile(
                title: const Text('Desbloqueo biométrico'),
                subtitle: const Text('Huella o Face ID además del PIN'),
                value: lock.biometricEnabled,
                onChanged: _toggleBiometric,
              ),
          ],
          const SizedBox(height: 8),
          ElevatedButton.icon(
            onPressed: _syncing ? null : _syncNow,
            icon: _syncing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.sync),
            label: const Text('Sincronizar ahora'),
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: () async {
              await ref.read(authRepositoryProvider).logout();
              ref.invalidate(dioProvider);
              ref.read(appLockStateProvider.notifier).state = false;
              ref.read(authNavigationVersionProvider.notifier).state++;
              if (context.mounted) {
                context.go('/login');
              }
            },
            child: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );
  }
}
