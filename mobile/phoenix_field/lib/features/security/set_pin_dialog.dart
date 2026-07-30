import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class SetPinDialog extends StatefulWidget {
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
  State<SetPinDialog> createState() => _SetPinDialogState();
}

class _SetPinDialogState extends State<SetPinDialog> {
  final _pinController = TextEditingController();
  final _confirmController = TextEditingController();

  @override
  void dispose() {
    _pinController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (widget.message != null) ...[
            Text(widget.message!),
            const SizedBox(height: 12),
          ],
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
          ),
        ],
      ),
      actions: [
        if (widget.allowCancel)
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancelar'),
          ),
        ElevatedButton(
          onPressed: () {
            final pin = _pinController.text.trim();
            final confirm = _confirmController.text.trim();
            if (!RegExp(r'^\d{4,6}$').hasMatch(pin)) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('El PIN debe tener entre 4 y 6 dígitos')),
              );
              return;
            }
            if (pin != confirm) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Los PIN no coinciden')),
              );
              return;
            }
            Navigator.of(context).pop(pin);
          },
          child: const Text('Guardar'),
        ),
      ],
    );
  }
}
