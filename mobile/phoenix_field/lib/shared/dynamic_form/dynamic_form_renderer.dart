import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_validator.dart';
import 'package:phoenix_field/shared/dynamic_form/date_field_widget.dart';
import 'package:phoenix_field/shared/dynamic_form/duration_field_widget.dart';
import 'package:phoenix_field/shared/dynamic_form/photo_field_widget.dart';

class DynamicFormRenderer extends StatelessWidget {
  const DynamicFormRenderer({
    super.key,
    required this.routineId,
    required this.schema,
    required this.catalogs,
    required this.values,
    required this.onChanged,
  });

  final int routineId;
  final Map<String, dynamic> schema;
  final List<Map<String, dynamic>> catalogs;
  final Map<String, dynamic> values;
  final void Function(String key, dynamic value) onChanged;

  static Map<String, dynamic> decodeResponses(String json) {
    final decoded = jsonDecode(json);
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    return {};
  }

  static List<String> validateRequired(
    Map<String, dynamic> schema,
    Map<String, dynamic> values,
  ) {
    return DynamicFormValidator.validate(schema, values);
  }

  static List<Map<String, dynamic>> photoItems(dynamic value) => _photoItems(value);

  static List<String> selectedValues(dynamic value) => _selectedValues(value);

  static int? durationMinutes(dynamic value) => _durationMinutes(value);

  static List<Map<String, dynamic>> _photoItems(dynamic value) {
    if (value == null) {
      return [];
    }
    if (value is String && value.isNotEmpty) {
      return [{'path': value}];
    }
    if (value is Map && value['path'] != null) {
      return [Map<String, dynamic>.from(value)];
    }
    if (value is List) {
      return value
          .map((e) {
            if (e is String) {
              return {'path': e};
            }
            if (e is Map) {
              return Map<String, dynamic>.from(e);
            }
            return null;
          })
          .whereType<Map<String, dynamic>>()
          .toList();
    }
    return [];
  }

  static List<String> _selectedValues(dynamic value) {
    if (value is List) {
      return value.map((e) => e.toString()).where((e) => e.isNotEmpty).toList();
    }
    if (value is String && value.isNotEmpty) {
      return [value];
    }
    return [];
  }

  static int? _durationMinutes(dynamic value) {
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final sections = schema['sections'] as List<dynamic>? ?? [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final section in sections)
          if (section is Map<String, dynamic>) _SectionCard(
            routineId: routineId,
            title: section['title']?.toString() ?? 'Sección',
            fields: section['fields'] as List<dynamic>? ?? [],
            catalogs: catalogs,
            values: values,
            onChanged: onChanged,
          ),
      ],
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.routineId,
    required this.title,
    required this.fields,
    required this.catalogs,
    required this.values,
    required this.onChanged,
  });

  final int routineId;
  final String title;
  final List<dynamic> fields;
  final List<Map<String, dynamic>> catalogs;
  final Map<String, dynamic> values;
  final void Function(String key, dynamic value) onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 12),
            for (final field in fields)
              if (field is Map<String, dynamic>) _FieldWidget(
                routineId: routineId,
                field: field,
                catalogs: catalogs,
                value: values[field['key']],
                onChanged: onChanged,
              ),
          ],
        ),
      ),
    );
  }
}

class _FieldWidget extends StatelessWidget {
  const _FieldWidget({
    required this.routineId,
    required this.field,
    required this.catalogs,
    required this.value,
    required this.onChanged,
  });

  final int routineId;
  final Map<String, dynamic> field;
  final List<Map<String, dynamic>> catalogs;
  final dynamic value;
  final void Function(String key, dynamic value) onChanged;

  @override
  Widget build(BuildContext context) {
    final key = field['key']?.toString();
    if (key == null) {
      return const SizedBox.shrink();
    }

    final label = field['label']?.toString() ?? key;
    final type = field['type']?.toString() ?? 'text';
    final required = field['required'] == true;

    if (type == 'photo') {
      final allowMultiple = field['allow_multiple'] == true;
      final maxImages = allowMultiple ? (field['max_images'] as int? ?? 4) : 1;
      return PhotoFieldWidget(
        routineId: routineId,
        fieldKey: key,
        label: label,
        required: required,
        allowMultiple: allowMultiple,
        maxImages: maxImages,
        captionEnabled: field['caption_enabled'] == true,
        captionRequired: field['caption_required'] == true,
        value: value,
        onChanged: (v) => onChanged(key, v),
      );
    }

    if (type == 'signature') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('$label${required ? ' *' : ''}'),
          subtitle: Text(
            value == null ? 'Se captura al finalizar la rutina' : 'Firma registrada',
          ),
          leading: const Icon(Icons.draw_outlined),
        ),
      );
    }

    if (type == 'duration') {
      return DurationFieldWidget(
        label: label,
        required: required,
        value: value,
        onChanged: (minutes) => onChanged(key, minutes),
      );
    }

    if (type == 'boolean') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          title: Text('$label${required ? ' *' : ''}'),
          value: value == true,
          onChanged: (checked) => onChanged(key, checked == true),
        ),
      );
    }

    if (type == 'date' || type == 'datetime') {
      return DateFieldWidget(
        label: label,
        required: required,
        includeTime: type == 'datetime',
        value: value,
        onChanged: (iso) => onChanged(key, iso),
      );
    }

    if (type == 'multiselect') {
      final options = _catalogOptions(field['option_catalog_id']);
      final selected = DynamicFormRenderer._selectedValues(value).toSet();

      if (options.isEmpty) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextFormField(
            initialValue: value?.toString() ?? '',
            decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
            onChanged: (v) => onChanged(key, v.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList()),
          ),
        );
      }

      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$label${required ? ' *' : ''}'),
            const SizedBox(height: 8),
            for (final option in options)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(option['label']?.toString() ?? option['value']?.toString() ?? ''),
                subtitle: option['description'] != null
                    ? Text(option['description'].toString())
                    : null,
                value: selected.contains(option['value']?.toString()),
                onChanged: (checked) {
                  final optionValue = option['value']?.toString();
                  if (optionValue == null) {
                    return;
                  }
                  final next = Set<String>.from(selected);
                  if (checked == true) {
                    next.add(optionValue);
                  } else {
                    next.remove(optionValue);
                  }
                  onChanged(key, next.toList());
                },
              ),
          ],
        ),
      );
    }

    if (type == 'options') {
      final options = _catalogOptions(field['option_catalog_id']);
      if (options.isEmpty) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextFormField(
            initialValue: value?.toString() ?? '',
            decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
            onChanged: (v) => onChanged(key, v),
          ),
        );
      }

      final selected = value?.toString();

      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$label${required ? ' *' : ''}', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            for (final option in options)
              RadioListTile<String>(
                contentPadding: EdgeInsets.zero,
                title: Text(option['label']?.toString() ?? option['value']?.toString() ?? ''),
                subtitle: option['description'] != null
                    ? Text(option['description'].toString())
                    : null,
                value: option['value']?.toString() ?? '',
                groupValue: selected,
                onChanged: (v) => onChanged(key, v),
              ),
          ],
        ),
      );
    }

    if (type == 'select') {
      final options = _catalogOptions(field['option_catalog_id']);
      if (options.isEmpty) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: TextFormField(
            initialValue: value?.toString() ?? '',
            decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
            onChanged: (v) => onChanged(key, v),
          ),
        );
      }

      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: DropdownButtonFormField<String>(
          initialValue: value?.toString().isNotEmpty == true ? value.toString() : null,
          decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
          items: [
            for (final option in options)
              DropdownMenuItem(
                value: option['value']?.toString(),
                child: Text(option['label']?.toString() ?? option['value']?.toString() ?? ''),
              ),
          ],
          onChanged: (v) => onChanged(key, v),
        ),
      );
    }

    if (type == 'number') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          initialValue: value?.toString() ?? '',
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
          onChanged: (v) {
            final parsed = num.tryParse(v);
            onChanged(key, parsed ?? v);
          },
        ),
      );
    }

    if (type == 'textarea') {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: TextFormField(
          initialValue: value?.toString() ?? '',
          maxLines: 3,
          decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
          onChanged: (v) => onChanged(key, v),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        initialValue: value?.toString() ?? '',
        decoration: InputDecoration(labelText: '$label${required ? ' *' : ''}'),
        onChanged: (v) => onChanged(key, v),
      ),
    );
  }

  List<Map<String, dynamic>> _catalogOptions(dynamic catalogId) {
    if (catalogId == null) {
      return [];
    }
    final id = catalogId is int ? catalogId : int.tryParse(catalogId.toString());
    if (id == null) {
      return [];
    }

    for (final catalog in catalogs) {
      if (catalog['id'] == id) {
        final options = catalog['options'];
        if (options is List) {
          return options.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        }
      }
    }
    return [];
  }
}
