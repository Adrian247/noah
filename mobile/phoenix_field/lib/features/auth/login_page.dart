import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:phoenix_field/core/config/app_config.dart';
import 'package:phoenix_field/core/network/api_connectivity.dart';
import 'package:phoenix_field/core/network/dio_provider.dart';
import 'package:phoenix_field/core/security/app_lock_provider.dart';
import 'package:phoenix_field/core/security/mobile_policy_enforcer.dart';
import 'package:phoenix_field/data/repositories/auth_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';

class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController(text: 'misael.palos@mein-company.com');
  final _passwordController = TextEditingController(text: 'phoenix_application');
  final _apiController = TextEditingController();
  bool _loading = false;
  bool _probing = true;
  String? _error;
  String? _probeMessage;

  @override
  void initState() {
    super.initState();
    _prepareApiUrl();
  }

  Future<void> _prepareApiUrl() async {
    setState(() {
      _probing = true;
      _probeMessage = 'Buscando servidor Phoenix…';
    });

    try {
      await ref.read(sessionBootstrapProvider.future);
      final session = ref.read(sessionStoreProvider);
      final resolved = AppConfig.resolveApiBaseUrl(persisted: session.apiBaseUrl);
      final probed = await ApiConnectivity.probeWorkingBaseUrl();

      if (!mounted) {
        return;
      }

      setState(() {
        _apiController.text = probed ?? resolved;
        _probeMessage = probed != null
            ? 'Servidor detectado: $probed'
            : 'No se detectó servidor. Usa http://127.0.0.1:8888/api/v1 si corres en Linux.';
      });
    } finally {
      if (mounted) {
        setState(() => _probing = false);
      }
    }
  }

  Future<void> _testConnection() async {
    final baseUrl = _apiController.text.trim();
    if (baseUrl.isEmpty) {
      setState(() => _error = 'Indica la URL API');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _probeMessage = 'Probando $baseUrl…';
    });

    try {
      await ApiConnectivity.validateOrThrow(baseUrl);
      setState(() => _probeMessage = 'Conexión OK con $baseUrl');
    } catch (e) {
      setState(() => _error = _formatError(e));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _apiController.dispose();
    super.dispose();
  }

  String _formatError(Object error) {
    if (error is DioException) {
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.receiveTimeout) {
        return 'No se pudo conectar al servidor.\n'
            '• Linux/desktop: http://127.0.0.1:8888/api/v1\n'
            '• Emulador Android: http://10.0.2.2:8888/api/v1\n'
            'Comprueba: docker compose up -d';
      }
      final data = error.response?.data;
      if (data is Map && data['message'] != null) {
        return data['message'].toString();
      }
      if (data is Map && data['errors'] is Map) {
        final errors = data['errors'] as Map;
        final email = errors['email'];
        if (email is List && email.isNotEmpty) {
          return email.first.toString();
        }
      }
      return error.message ?? error.toString();
    }
    return error.toString();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(authRepositoryProvider).login(
            email: _emailController.text,
            password: _passwordController.text,
            apiBaseUrl: _apiController.text,
          );
      ref.read(sessionVersionProvider.notifier).state++;
      ref.read(authNavigationVersionProvider.notifier).state++;
      ref.invalidate(dioProvider);
      try {
        await ref.read(syncRepositoryProvider).syncNow();
      } catch (_) {
        // Sin red: política desde login.
      }
      await ref.read(mobilePolicyEnforcerProvider).applyLocalRules();
      if (!mounted) {
        return;
      }
      final policyOk =
          await ref.read(mobilePolicyEnforcerProvider).ensureRequiredPin(context);
      if (!policyOk) {
        await ref.read(authRepositoryProvider).logout();
        ref.read(authNavigationVersionProvider.notifier).state++;
        if (mounted) {
          setState(() => _error = 'Tu empresa exige bloqueo con PIN para usar la app.');
        }
        return;
      }
      if (mounted) {
        context.go('/routines');
      }
    } catch (e) {
      setState(() => _error = _formatError(e));
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Phoenix Campo',
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Rutinas offline-first para técnicos en campo',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.white70,
                          ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _apiController,
                      enabled: !_probing,
                      decoration: InputDecoration(
                        labelText: 'URL API',
                        helperText: _probeMessage ?? AppConfig.defaultForPlatform(),
                        helperMaxLines: 3,
                      ),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: _loading || _probing ? null : _testConnection,
                      child: const Text('Probar conexión'),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(labelText: 'Correo'),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Requerido' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: true,
                      decoration: const InputDecoration(labelText: 'Contraseña'),
                      validator: (v) => (v == null || v.isEmpty) ? 'Requerido' : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: const TextStyle(color: Colors.redAccent)),
                    ],
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _loading || _probing ? null : _submit,
                      child: _loading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Iniciar sesión'),
                    ),
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
