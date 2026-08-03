import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Formulario reutilizable para crear/confirmar PIN (diálogo o pantalla completa).
class SetPinForm extends StatefulWidget {
  const SetPinForm({
    super.key,
    this.title = 'Configurar PIN',
    this.message,
    this.submitLabel = 'Guardar',
    this.allowCancel = true,
    this.onCancel,
    required this.onSubmit,
  });

  final String title;
  final String? message;
  final String submitLabel;
  final bool allowCancel;
  final VoidCallback? onCancel;
  final ValueChanged<String> onSubmit;

  @override
  State<SetPinForm> createState() => _SetPinFormState();
}

class _SetPinFormState extends State<SetPinForm> {
  final _pinController = TextEditingController();
  final _confirmController = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _pinController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  void _submit() {
    final pin = _pinController.text.trim();
    final confirm = _confirmController.text.trim();
    if (!RegExp(r'^\d{4,6}$').hasMatch(pin)) {
      setState(() => _error = 'El PIN debe tener entre 4 y 6 dígitos');
      return;
    }
    if (pin != confirm) {
      setState(() => _error = 'Los PIN no coinciden');
      return;
    }
    widget.onSubmit(pin);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
        if (widget.message != null) ...[
          const SizedBox(height: 8),
          Text(
            widget.message!,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.75),
                ),
          ),
        ],
        const SizedBox(height: 16),
        TextField(
          controller: _pinController,
          obscureText: true,
          keyboardType: TextInputType.number,
          maxLength: 6,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            labelText: 'PIN (4-6 dígitos)',
            counterText: '',
          ),
          onChanged: (_) {
            if (_error != null) {
              setState(() => _error = null);
            }
          },
        ),
        const SizedBox(height: 8),
        TextField(
          controller: _confirmController,
          obscureText: true,
          keyboardType: TextInputType.number,
          maxLength: 6,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            labelText: 'Confirmar PIN',
            counterText: '',
          ),
          onSubmitted: (_) => _submit(),
          onChanged: (_) {
            if (_error != null) {
              setState(() => _error = null);
            }
          },
        ),
        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ],
        const SizedBox(height: 20),
        ElevatedButton(
          onPressed: _submit,
          child: Text(widget.submitLabel),
        ),
        if (widget.allowCancel && widget.onCancel != null) ...[
          const SizedBox(height: 8),
          TextButton(
            onPressed: widget.onCancel,
            child: const Text('Cancelar'),
          ),
        ],
      ],
    );
  }
}

class SetPinDialog extends StatelessWidget {
  const SetPinDialog({
    super.key,
    this.title = 'Configurar PIN',
    this.message,
    this.allowCancel = true,
  });

  final String title;
  final String? message;
  final bool allowCancel;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: allowCancel,
      child: AlertDialog(
        contentPadding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
        content: SetPinForm(
          title: title,
          message: message,
          allowCancel: allowCancel,
          onCancel: allowCancel ? () => Navigator.of(context).pop() : null,
          onSubmit: (pin) => Navigator.of(context).pop(pin),
        ),
      ),
    );
  }
}
