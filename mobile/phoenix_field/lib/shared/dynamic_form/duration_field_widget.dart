import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class DurationFieldWidget extends StatelessWidget {
  const DurationFieldWidget({
    super.key,
    required this.label,
    required this.required,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool required;
  final dynamic value;
  final void Function(int? minutes) onChanged;

  int get _totalMinutes {
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final hours = _totalMinutes ~/ 60;
    final minutes = _totalMinutes % 60;

    void update(int h, int m) {
      final total = (h * 60) + m;
      onChanged(total > 0 ? total : null);
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$label${required ? ' *' : ''}',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  key: ValueKey('h-$hours'),
                  initialValue: hours > 0 ? hours.toString() : '',
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Horas',
                    suffixText: 'h',
                  ),
                  onChanged: (v) {
                    final h = int.tryParse(v) ?? 0;
                    update(h, minutes);
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextFormField(
                  key: ValueKey('m-$minutes'),
                  initialValue: minutes > 0 ? minutes.toString() : '',
                  keyboardType: TextInputType.number,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(2),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Minutos',
                    suffixText: 'min',
                  ),
                  onChanged: (v) {
                    final m = (int.tryParse(v) ?? 0).clamp(0, 59);
                    update(hours, m);
                  },
                ),
              ),
            ],
          ),
          if (_totalMinutes > 0)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                'Total: $_totalMinutes min',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.white70,
                    ),
              ),
            ),
        ],
      ),
    );
  }
}
