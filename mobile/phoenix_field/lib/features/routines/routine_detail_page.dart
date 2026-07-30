import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_renderer.dart';
import 'package:phoenix_field/shared/widgets/routine_timer.dart';
import 'package:phoenix_field/shared/widgets/signature_capture_dialog.dart';

class RoutineDetailPage extends ConsumerStatefulWidget {
  const RoutineDetailPage({super.key, required this.routineId});

  final int routineId;

  @override
  ConsumerState<RoutineDetailPage> createState() => _RoutineDetailPageState();
}

class _RoutineDetailPageState extends ConsumerState<RoutineDetailPage> {
  final _responses = <String, dynamic>{};
  final _commentsController = TextEditingController();
  int _durationMinutes = 0;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _signatureLocalId;
  Map<String, dynamic>? _routine;
  List<Map<String, dynamic>> _catalogs = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _commentsController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final repo = ref.read(syncRepositoryProvider);
    try {
      final routine = await repo.getRoutine(widget.routineId);
      final catalogs = await repo.getOptionCatalogs();
      final draft = await repo.getDraft(widget.routineId);

      if (draft != null) {
        final decoded = DynamicFormRenderer.decodeResponses(draft.responsesJson);
        _responses.addAll(decoded);
        _commentsController.text = draft.comments ?? '';
        _durationMinutes = draft.durationMinutes ?? 0;
        _signatureLocalId = draft.signatureLocalId;
      }

      setState(() {
        _routine = routine;
        _catalogs = catalogs;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _persistDraft() async {
    await ref.read(syncRepositoryProvider).saveDraft(
          routineId: widget.routineId,
          responses: Map<String, dynamic>.from(_responses),
          comments: _commentsController.text.trim().isEmpty
              ? null
              : _commentsController.text.trim(),
          durationMinutes: _durationMinutes > 0 ? _durationMinutes : null,
          signatureLocalId: _signatureLocalId,
        );
  }

  void _onFieldChanged(String key, dynamic value) {
    setState(() => _responses[key] = value);
    _persistDraft();
  }

  Map<String, dynamic>? get _schema {
    final formVersion = _routine?['routine_type']?['form_version'];
    if (formVersion is Map<String, dynamic>) {
      final schema = formVersion['schema'];
      if (schema is Map<String, dynamic>) {
        return schema;
      }
    }
    return null;
  }

  Future<void> _submit() async {
    final schema = _schema;
    if (schema == null) {
      setState(() => _error = 'Formulario no disponible offline');
      return;
    }

    final missing = DynamicFormRenderer.validateRequired(schema, _responses);
    if (missing.isNotEmpty) {
      setState(() => _error = 'Faltan campos: ${missing.join(', ')}');
      return;
    }

    final signatureBytes = await showSignatureCaptureDialog(context);
    if (signatureBytes == null) {
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    final repo = ref.read(syncRepositoryProvider);
    final media = ref.read(mediaRepositoryProvider);

    try {
      final signatureRef = await media.saveSignaturePng(
        routineId: widget.routineId,
        pngBytes: signatureBytes,
      );

      final responses = Map<String, dynamic>.from(_responses);
      responses['technician_signature'] = {'path': signatureRef};

      await repo.submitExecution(
        routineId: widget.routineId,
        responses: responses,
        comments: _commentsController.text.trim().isEmpty
            ? null
            : _commentsController.text.trim(),
        durationMinutes: _durationMinutes > 0 ? _durationMinutes : null,
        signatureLocalId: signatureRef,
      );

      try {
        await repo.syncNow();
        await BackgroundSyncService.requestImmediate();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Ejecución enviada. Las fotos se suben en segundo plano si quedan pendientes.'),
            ),
          );
          Navigator.of(context).pop();
        }
      } catch (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Guardado localmente. Se sincronizará cuando haya conexión.'),
            ),
          );
          Navigator.of(context).pop();
        }
      }
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_routine == null) {
      return Scaffold(
        appBar: AppBar(title: Text('Rutina #${widget.routineId}')),
        body: Center(child: Text(_error ?? 'Rutina no encontrada')),
      );
    }

    final asset = _routine!['asset']?['tag']?.toString() ?? '—';
    final site = _routine!['site']?['name']?.toString() ?? '—';
    final type = _routine!['routine_type']?['name']?.toString() ?? 'Rutina';
    final status = _routine!['status']?.toString() ?? '';

    return Scaffold(
      appBar: AppBar(title: Text('Rutina #${widget.routineId}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(type, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  Text('Activo: $asset'),
                  Text('Sitio: $site'),
                  Text('Estado: $status'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          RoutineTimer(
            initialMinutes: _durationMinutes > 0 ? _durationMinutes : null,
            onMinutesChanged: (minutes) {
              _durationMinutes = minutes;
              _persistDraft();
            },
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _commentsController,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Comentarios del técnico'),
            onChanged: (_) => _persistDraft(),
          ),
          const SizedBox(height: 16),
          if (_schema != null)
            DynamicFormRenderer(
              routineId: widget.routineId,
              schema: _schema!,
              catalogs: _catalogs,
              values: _responses,
              onChanged: _onFieldChanged,
            )
          else
            const Text('Sin esquema de formulario en cache. Sincroniza de nuevo.'),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _submitting || status != 'assigned' ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Finalizar, firmar y enviar'),
          ),
        ],
      ),
    );
  }
}
