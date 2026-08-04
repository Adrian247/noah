import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:phoenix_field/core/sync/background_sync_service.dart';
import 'package:phoenix_field/data/local/app_database.dart';
import 'package:phoenix_field/data/repositories/media_repository.dart';
import 'package:phoenix_field/data/repositories/sync_repository.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_renderer.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_validator.dart';
import 'package:phoenix_field/shared/routine/consumptions_panel.dart';
import 'package:phoenix_field/shared/routine/routine_status_labels.dart';
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
  String _localSyncStatus = 'synced';
  List<Map<String, dynamic>> _catalogs = [];
  List<Map<String, dynamic>> _supplies = [];
  List<ConsumptionLine> _consumptions = [];

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
      final local = await repo.getLocalRoutine(widget.routineId);
      final catalogs = await repo.getOptionCatalogs();
      final supplies = await repo.getSupplyItems();
      final draft = await repo.getDraft(widget.routineId);

      Map<String, dynamic>? routine;
      if (local != null) {
        routine = jsonDecode(local.payloadJson) as Map<String, dynamic>;
        // Preferir status de columna local (más confiable tras sync).
        routine['status'] = local.status;
      }

      var hydratedFromServer = false;
      if (draft != null) {
        final decoded = DynamicFormRenderer.decodeResponses(draft.responsesJson);
        if (decoded.isNotEmpty) {
          _responses.addAll(decoded);
        }
        if (draft.comments != null && draft.comments!.trim().isNotEmpty) {
          _commentsController.text = draft.comments!;
        }
        if (draft.durationMinutes != null && draft.durationMinutes! > 0) {
          _durationMinutes = draft.durationMinutes!;
        }
        _signatureLocalId = draft.signatureLocalId;
        _consumptions = ConsumptionsPanel.decodeList(
          jsonDecode(draft.consumptionsJson),
        );
      }

      // Tras rechazo (u otra reasignación), recuperar lo enviado en la última ejecución.
      if (_responses.isEmpty || _shouldPreferServerExecution(routine, draft)) {
        hydratedFromServer = await _hydrateFromLatestExecution(routine);
      } else {
        // Completar huecos desde la ejecución rechazada sin pisar edits locales.
        await _mergeMissingFromLatestExecution(routine);
      }

      // Convertir rutas de servidor a archivos locales para poder ver/editar fotos.
      final media = ref.read(mediaRepositoryProvider);
      final materialized = await media.materializeRemotePhotosInResponses(
        routineId: widget.routineId,
        responses: Map<String, dynamic>.from(_responses),
      );
      _responses
        ..clear()
        ..addAll(materialized);

      if (hydratedFromServer || (_responses.isNotEmpty && draft == null) || materialized.isNotEmpty) {
        await repo.saveDraft(
          routineId: widget.routineId,
          responses: Map<String, dynamic>.from(_responses),
          comments: _commentsController.text.trim().isEmpty
              ? null
              : _commentsController.text.trim(),
          durationMinutes: _durationMinutes > 0 ? _durationMinutes : null,
          signatureLocalId: _signatureLocalId,
          consumptions: _consumptions.map((line) => line.toPayload()).toList(),
        );
      }

      setState(() {
        _routine = routine;
        _localSyncStatus = local?.localSyncStatus ?? 'synced';
        _catalogs = catalogs;
        _supplies = supplies;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  bool _shouldPreferServerExecution(
    Map<String, dynamic>? routine,
    ExecutionDraft? draft,
  ) {
    final reason = routineRejectionReason(routine);
    if (reason == null) {
      return false;
    }
    if (draft == null) {
      return true;
    }
    final decoded = DynamicFormRenderer.decodeResponses(draft.responsesJson);
    return decoded.isEmpty;
  }

  Future<bool> _hydrateFromLatestExecution(Map<String, dynamic>? routine) async {
    final execution = routine?['latest_execution'];
    if (execution is! Map) {
      return false;
    }

    var changed = false;
    final responses = execution['responses'];
    if (responses is Map && responses.isNotEmpty) {
      _responses
        ..clear()
        ..addAll(Map<String, dynamic>.from(responses));
      // La firma se vuelve a capturar al reenviar.
      _responses.remove('technician_signature');
      changed = true;
    }

    final comments = execution['technician_comments']?.toString();
    if (comments != null && comments.trim().isNotEmpty && _commentsController.text.isEmpty) {
      _commentsController.text = comments;
      changed = true;
    }

    final duration = execution['duration_minutes'];
    if (duration is num && duration > 0 && _durationMinutes <= 0) {
      _durationMinutes = duration.toInt();
      changed = true;
    }

    return changed;
  }

  Future<void> _mergeMissingFromLatestExecution(Map<String, dynamic>? routine) async {
    final execution = routine?['latest_execution'];
    if (execution is! Map) {
      return;
    }
    final responses = execution['responses'];
    if (responses is! Map) {
      return;
    }
    for (final entry in responses.entries) {
      final key = entry.key.toString();
      if (key == 'technician_signature') {
        continue;
      }
      if (!_responses.containsKey(key) || _isBlankResponse(_responses[key])) {
        _responses[key] = entry.value;
      }
    }
    final comments = execution['technician_comments']?.toString();
    if ((_commentsController.text.trim().isEmpty) &&
        comments != null &&
        comments.trim().isNotEmpty) {
      _commentsController.text = comments;
    }
    final duration = execution['duration_minutes'];
    if (_durationMinutes <= 0 && duration is num && duration > 0) {
      _durationMinutes = duration.toInt();
    }
  }

  bool _isBlankResponse(dynamic value) {
    if (value == null) {
      return true;
    }
    if (value is String) {
      return value.trim().isEmpty;
    }
    if (value is List) {
      return value.isEmpty;
    }
    if (value is Map) {
      return value.isEmpty;
    }
    return false;
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
          consumptions: _consumptions.map((line) => line.toPayload()).toList(),
        );
  }

  void _onFieldChanged(String key, dynamic value) {
    setState(() => _responses[key] = value);
    _persistDraft();
  }

  Map<String, dynamic>? get _schema {
    final formVersion = _routine?['routine_type']?['form_version'];
    if (formVersion is! Map) {
      return null;
    }
    final schema = formVersion['schema'];
    if (schema is! Map) {
      return null;
    }
    return Map<String, dynamic>.from(schema);
  }

  Future<void> _submit() async {
    final schema = _schema;
    if (schema == null) {
      setState(() => _error = 'Formulario no disponible offline');
      return;
    }

    final errors = DynamicFormValidator.validate(
      schema,
      _responses,
      catalogs: _catalogs,
    );
    if (errors.isNotEmpty) {
      setState(() => _error = errors.first);
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
        consumptions: _consumptions.map((line) => line.toPayload()).toList(),
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
        appBar: AppBar(title: Text('Servicio #${widget.routineId}')),
        body: Center(child: Text(_error ?? 'Servicio no encontrado')),
      );
    }

    final asset = _routine!['asset']?['tag']?.toString();
    final client = _routine!['client']?['trade_name']?.toString() ??
        _routine!['client']?['legal_name']?.toString();
    final site = _routine!['site']?['name']?.toString() ?? '—';
    final type = _routine!['routine_type']?['name']?.toString() ?? 'Servicio';
    final serviceCategory = _routine!['routine_type']?['service_category']?.toString() ?? _routine!['routine_type']?['service_line']?.toString();
    final serviceLineLabel = switch (serviceCategory) {
      'manufacturing' || 'fabrication' => 'Fabricación',
      'installation' || 'supply' => 'Instalación',
      'maintenance' => 'Mantenimiento',
      _ => null,
    };
    final subject = (asset != null && asset.isNotEmpty)
        ? asset
        : (client != null && client.isNotEmpty ? client : '—');
    final status = _routine!['status']?.toString() ?? '';
    final canSubmit = routineCanSubmitFromField(status);
    final rejectionReason = _rejectionReason(_routine!);
    final schema = _schema;
    final progress = schema == null
        ? null
        : DynamicFormValidator.requiredProgress(
            schema,
            _responses,
            catalogs: _catalogs,
          );

    return Scaffold(
      appBar: AppBar(
        title: Text('Servicio #${widget.routineId}'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: Center(child: RoutineStatusChip(status: status, compact: true)),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          RoutineStatusBanner(
            status: status,
            localSyncStatus: _localSyncStatus,
            rejectionReason: rejectionReason,
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(type, style: Theme.of(context).textTheme.titleMedium),
                  if (serviceLineLabel != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      serviceLineLabel,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                  const SizedBox(height: 8),
                  Text(asset != null && asset.isNotEmpty ? 'Artículo: $asset' : 'Sujeto: $subject'),
                  if (client != null &&
                      client.isNotEmpty &&
                      asset != null &&
                      asset.isNotEmpty)
                    Text('Cliente: $client'),
                  Text('Sitio: $site'),
                ],
              ),
            ),
          ),
          if (progress != null && progress.total > 0) ...[
            const SizedBox(height: 12),
            _FormProgressCard(
              filled: progress.filled,
              total: progress.total,
            ),
          ],
          const SizedBox(height: 16),
          IgnorePointer(
            ignoring: !canSubmit,
            child: Opacity(
              opacity: canSubmit ? 1 : 0.72,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  RoutineTimer(
                    initialMinutes: _durationMinutes > 0 ? _durationMinutes : null,
                    autoStart: canSubmit && _durationMinutes == 0 && status == 'assigned',
                    onMinutesChanged: (minutes) {
                      _durationMinutes = minutes;
                      _persistDraft();
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _commentsController,
                    maxLines: 3,
                    readOnly: !canSubmit,
                    decoration: const InputDecoration(labelText: 'Comentarios del técnico'),
                    onChanged: (_) => _persistDraft(),
                  ),
                  const SizedBox(height: 16),
                  if (schema != null)
                    DynamicFormRenderer(
                      routineId: widget.routineId,
                      schema: schema,
                      catalogs: _catalogs,
                      values: _responses,
                      onChanged: _onFieldChanged,
                    )
                  else
                    const Text('Sin esquema de formulario en cache. Sincroniza de nuevo.'),
                  const SizedBox(height: 16),
                  ConsumptionsPanel(
                    supplies: _supplies,
                    lines: _consumptions,
                    enabled: canSubmit,
                    onChanged: (lines) {
                      setState(() => _consumptions = lines);
                      _persistDraft();
                    },
                  ),
                ],
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          if (!canSubmit) ...[
            const SizedBox(height: 16),
            Card(
              color: routineStatusColor(status).withValues(alpha: 0.12),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  'Este servicio está en «${routineStatusLabel(status)}» y ya no se puede enviar desde campo.',
                  style: TextStyle(color: routineStatusColor(status)),
                ),
              ),
            ),
          ],
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _submitting || !canSubmit ? null : _submit,
            child: _submitting
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(
                    rejectionReason != null
                        ? 'Corregir, firmar y reenviar'
                        : 'Finalizar, firmar y enviar',
                  ),
          ),
        ],
      ),
    );
  }

  String? _rejectionReason(Map<String, dynamic> routine) {
    final execution = routine['latest_execution'];
    if (execution is Map) {
      final reason = execution['rejection_reason']?.toString().trim();
      if (reason != null && reason.isNotEmpty) {
        return reason;
      }
    }
    return null;
  }
}

class _FormProgressCard extends StatelessWidget {
  const _FormProgressCard({required this.filled, required this.total});

  final int filled;
  final int total;

  @override
  Widget build(BuildContext context) {
    final ratio = total == 0 ? 0.0 : filled / total;
    final complete = filled >= total;
    final color = complete ? const Color(0xFF34D399) : Theme.of(context).colorScheme.primary;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  complete ? Icons.check_circle_outline : Icons.checklist_rtl,
                  color: color,
                  size: 20,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    complete
                        ? 'Formulario listo para firmar'
                        : 'Progreso del formulario',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ),
                Text(
                  '$filled / $total',
                  style: TextStyle(color: color, fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: ratio,
                minHeight: 8,
                color: color,
                backgroundColor: color.withValues(alpha: 0.15),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
