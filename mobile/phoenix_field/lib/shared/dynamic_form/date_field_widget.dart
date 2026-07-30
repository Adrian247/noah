import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class DateFieldWidget extends StatelessWidget {
  const DateFieldWidget({
    super.key,
    required this.label,
    required this.required,
    required this.includeTime,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool required;
  final bool includeTime;
  final dynamic value;
  final void Function(String? isoValue) onChanged;

  static final _dateFormat = DateFormat('yyyy-MM-dd');
  static final _dateTimeFormat = DateFormat("yyyy-MM-dd'T'HH:mm");

  DateTime? _parseValue() {
    final raw = value?.toString().trim();
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      if (includeTime) {
        return _dateTimeFormat.parseStrict(raw);
      }
      return _dateFormat.parseStrict(raw);
    } catch (_) {
      return DateTime.tryParse(raw);
    }
  }

  String _format(DateTime date) {
    if (includeTime) {
      return _dateTimeFormat.format(date);
    }
    return _dateFormat.format(date);
  }

  String _displayValue() {
    final parsed = _parseValue();
    if (parsed == null) {
      return 'Sin seleccionar';
    }
    if (includeTime) {
      return DateFormat('dd/MM/yyyy HH:mm').format(parsed);
    }
    return DateFormat('dd/MM/yyyy').format(parsed);
  }

  Future<void> _pick(BuildContext context) async {
    final initial = _parseValue() ?? DateTime.now();
    final pickedDate = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      locale: const Locale('es', 'MX'),
    );
    if (pickedDate == null) {
      return;
    }

    if (!includeTime) {
      onChanged(_format(pickedDate));
      return;
    }

    if (!context.mounted) {
      return;
    }

    final pickedTime = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (pickedTime == null) {
      return;
    }

    final combined = DateTime(
      pickedDate.year,
      pickedDate.month,
      pickedDate.day,
      pickedTime.hour,
      pickedTime.minute,
    );
    onChanged(_format(combined));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: '$label${required ? ' *' : ''}',
        ),
        child: Row(
          children: [
            Expanded(child: Text(_displayValue())),
            TextButton.icon(
              onPressed: () => _pick(context),
              icon: Icon(includeTime ? Icons.event_available : Icons.calendar_today),
              label: const Text('Elegir'),
            ),
            if (value != null && value.toString().isNotEmpty)
              IconButton(
                tooltip: 'Limpiar',
                onPressed: () => onChanged(null),
                icon: const Icon(Icons.clear),
              ),
          ],
        ),
      ),
    );
  }
}
