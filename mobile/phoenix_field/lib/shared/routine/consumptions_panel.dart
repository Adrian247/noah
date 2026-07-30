import 'package:flutter/material.dart';

class ConsumptionLine {
  ConsumptionLine({
    required this.supplyItemId,
    required this.quantity,
    this.usageType = 'out',
    this.unitCost = 0,
  });

  final int supplyItemId;
  final double quantity;
  final String usageType;
  final double unitCost;

  Map<String, dynamic> toPayload() => {
        'supply_item_id': supplyItemId,
        'quantity': quantity,
        'usage_type': usageType,
        'unit_cost': unitCost,
      };

  static ConsumptionLine? fromMap(Map<String, dynamic> map) {
    final supplyId = map['supply_item_id'];
    final quantity = map['quantity'];
    if (supplyId is! int && supplyId is! num) {
      return null;
    }
    final parsedQty = quantity is num ? quantity.toDouble() : double.tryParse('$quantity');
    if (parsedQty == null || parsedQty <= 0) {
      return null;
    }

    return ConsumptionLine(
      supplyItemId: supplyId is int ? supplyId : supplyId.toInt(),
      quantity: parsedQty,
      usageType: map['usage_type']?.toString() ?? 'out',
      unitCost: (map['unit_cost'] as num?)?.toDouble() ?? 0,
    );
  }
}

class ConsumptionsPanel extends StatelessWidget {
  const ConsumptionsPanel({
    super.key,
    required this.supplies,
    required this.lines,
    required this.onChanged,
  });

  final List<Map<String, dynamic>> supplies;
  final List<ConsumptionLine> lines;
  final void Function(List<ConsumptionLine> lines) onChanged;

  static List<ConsumptionLine> decodeList(dynamic raw) {
    if (raw is! List) {
      return [];
    }
    return raw
        .whereType<Map>()
        .map((e) => ConsumptionLine.fromMap(Map<String, dynamic>.from(e)))
        .whereType<ConsumptionLine>()
        .toList();
  }

  void _addLine() {
    if (supplies.isEmpty) {
      return;
    }
    final first = supplies.first;
    final id = first['id'];
    if (id is! int) {
      return;
    }
    final cost = (first['standard_cost'] as num?)?.toDouble() ?? 0;
    onChanged([
      ...lines,
      ConsumptionLine(
        supplyItemId: id,
        quantity: 1,
        unitCost: cost,
      ),
    ]);
  }

  void _updateLine(int index, ConsumptionLine line) {
    final next = List<ConsumptionLine>.from(lines);
    next[index] = line;
    onChanged(next);
  }

  void _removeLine(int index) {
    final next = List<ConsumptionLine>.from(lines)..removeAt(index);
    onChanged(next);
  }

  String _supplyLabel(int supplyItemId) {
    for (final supply in supplies) {
      if (supply['id'] == supplyItemId) {
        final sku = supply['sku']?.toString();
        final name = supply['name']?.toString() ?? 'Insumo';
        final unit = supply['unit']?.toString();
        final stock = supply['quantity_on_hand'];
        final stockLabel = stock != null ? ' · stock $stock' : '';
        return sku != null && sku.isNotEmpty
            ? '$sku — $name${unit != null ? ' ($unit)' : ''}$stockLabel'
            : '$name${unit != null ? ' ($unit)' : ''}$stockLabel';
      }
    }
    return 'Insumo #$supplyItemId';
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Consumos / insumos', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 4),
            const Text(
              'Opcional. Se registran al enviar la ejecución.',
              style: TextStyle(fontSize: 12, color: Colors.white70),
            ),
            if (supplies.isEmpty) ...[
              const SizedBox(height: 12),
              const Text(
                'Sin insumos en caché. Sincroniza para cargar el catálogo.',
                style: TextStyle(color: Colors.amber),
              ),
            ],
            for (var i = 0; i < lines.length; i++) ...[
              const SizedBox(height: 12),
              _ConsumptionRow(
                supplies: supplies,
                line: lines[i],
                label: _supplyLabel(lines[i].supplyItemId),
                onChanged: (line) => _updateLine(i, line),
                onRemove: () => _removeLine(i),
              ),
            ],
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: supplies.isEmpty ? null : _addLine,
              icon: const Icon(Icons.add),
              label: const Text('Agregar consumo'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ConsumptionRow extends StatelessWidget {
  const _ConsumptionRow({
    required this.supplies,
    required this.line,
    required this.label,
    required this.onChanged,
    required this.onRemove,
  });

  final List<Map<String, dynamic>> supplies;
  final ConsumptionLine line;
  final String label;
  final ValueChanged<ConsumptionLine> onChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<int>(
          value: line.supplyItemId,
          decoration: const InputDecoration(labelText: 'Insumo'),
          items: [
            for (final supply in supplies)
              if (supply['id'] is int)
                DropdownMenuItem<int>(
                  value: supply['id'] as int,
                  child: Text(
                    '${supply['sku'] ?? ''} ${supply['name'] ?? 'Insumo'}'.trim(),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
          ],
          onChanged: (id) {
            if (id == null) {
              return;
            }
            Map<String, dynamic>? selected;
            for (final supply in supplies) {
              if (supply['id'] == id) {
                selected = supply;
                break;
              }
            }
            onChanged(
              ConsumptionLine(
                supplyItemId: id,
                quantity: line.quantity,
                usageType: line.usageType,
                unitCost: (selected?['standard_cost'] as num?)?.toDouble() ?? line.unitCost,
              ),
            );
          },
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: TextFormField(
                initialValue: line.quantity.toString(),
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'Cantidad',
                  helperText: label,
                  helperMaxLines: 2,
                ),
                onChanged: (value) {
                  final qty = double.tryParse(value.replaceAll(',', '.'));
                  if (qty == null) {
                    return;
                  }
                  onChanged(
                    ConsumptionLine(
                      supplyItemId: line.supplyItemId,
                      quantity: qty,
                      usageType: line.usageType,
                      unitCost: line.unitCost,
                    ),
                  );
                },
              ),
            ),
            IconButton(
              tooltip: 'Quitar',
              onPressed: onRemove,
              icon: const Icon(Icons.delete_outline),
            ),
          ],
        ),
      ],
    );
  }
}
