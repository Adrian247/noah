import 'package:phoenix_field/shared/dynamic_form/dynamic_form_renderer.dart';

class DynamicFormValidator {
  const DynamicFormValidator._();

  static List<String> validate(
    Map<String, dynamic> schema,
    Map<String, dynamic> values, {
    List<Map<String, dynamic>> catalogs = const [],
  }) {
    final errors = <String>[];
    final sections = schema['sections'] as List<dynamic>? ?? [];

    for (final section in sections) {
      if (section is! Map) {
        continue;
      }
      final fields = section['fields'] as List<dynamic>? ?? [];
      for (final field in fields) {
        if (field is! Map) {
          continue;
        }
        final map = Map<String, dynamic>.from(field);
        final key = map['key']?.toString();
        if (key == null) {
          continue;
        }

        final label = map['label']?.toString() ?? key;
        final type = map['type']?.toString() ?? 'text';
        final required = map['required'] == true;
        final value = values[key];

        if (type == 'signature') {
          continue;
        }

        if (type == 'photo') {
          _validatePhoto(map, value, label, errors);
          continue;
        }

        if (required) {
          if (type == 'multiselect') {
            if (DynamicFormRenderer.selectedValues(value).isEmpty) {
              errors.add('$label es obligatorio');
            }
          } else if (type == 'duration') {
            final minutes = DynamicFormRenderer.durationMinutes(value);
            if (minutes == null || minutes <= 0) {
              errors.add('$label es obligatorio');
            }
          } else if (type == 'boolean') {
            if (value != true) {
              errors.add('$label es obligatorio');
            }
          } else if (type == 'date' || type == 'datetime') {
            final raw = value?.toString().trim();
            if (raw == null || raw.isEmpty) {
              errors.add('$label es obligatorio');
            }
          } else if (_isEmpty(value)) {
            errors.add('$label es obligatorio');
          }
        }

        if (_isEmpty(value)) {
          continue;
        }

        if (type == 'number') {
          final number = num.tryParse(value.toString());
          if (number == null) {
            errors.add('$label debe ser un número');
            continue;
          }
          final min = _asNum(map['min']);
          final max = _asNum(map['max']);
          if (min != null && number < min) {
            errors.add('$label debe ser al menos $min');
          }
          if (max != null && number > max) {
            errors.add('$label no puede superar $max');
          }
        }

        if (type == 'text' || type == 'textarea') {
          final text = value.toString();
          final minLength = _asInt(map['min_length']);
          final maxLength = _asInt(map['max_length']);
          if (minLength != null && text.length < minLength) {
            errors.add('$label debe tener al menos $minLength caracteres');
          }
          if (maxLength != null && text.length > maxLength) {
            errors.add('$label no puede superar $maxLength caracteres');
          }
          final pattern = map['pattern']?.toString();
          if (pattern != null && pattern.isNotEmpty) {
            try {
              if (!RegExp(pattern).hasMatch(text)) {
                errors.add('$label no cumple el formato requerido');
              }
            } catch (_) {
              // Patrón inválido en esquema: no bloquear al técnico.
            }
          }
        }

        if (type == 'select' || type == 'options') {
          final allowed = _catalogValues(map['option_catalog_id'], catalogs);
          if (allowed.isNotEmpty && !allowed.contains(value.toString())) {
            errors.add('$label tiene un valor no permitido');
          }
        }

        if (type == 'multiselect') {
          final allowed = _catalogValues(map['option_catalog_id'], catalogs);
          if (allowed.isNotEmpty) {
            for (final selected in DynamicFormRenderer.selectedValues(value)) {
              if (!allowed.contains(selected)) {
                errors.add('$label contiene opciones no permitidas');
                break;
              }
            }
          }
        }
      }
    }

    return errors;
  }

  /// Cuenta campos obligatorios (excluye signature) y cuántos ya están llenos.
  static ({int total, int filled}) requiredProgress(
    Map<String, dynamic> schema,
    Map<String, dynamic> values, {
    List<Map<String, dynamic>> catalogs = const [],
  }) {
    var total = 0;
    var filled = 0;
    final sections = schema['sections'] as List<dynamic>? ?? [];

    for (final section in sections) {
      if (section is! Map) {
        continue;
      }
      final fields = section['fields'] as List<dynamic>? ?? [];
      for (final field in fields) {
        if (field is! Map) {
          continue;
        }
        final map = Map<String, dynamic>.from(field);
        final key = map['key']?.toString();
        if (key == null) {
          continue;
        }
        final type = map['type']?.toString() ?? 'text';
        if (type == 'signature' || map['required'] != true) {
          continue;
        }

        total++;
        final probe = <String, dynamic>{key: values[key]};
        final miniSchema = {
          'sections': [
            {
              'fields': [map],
            }
          ],
        };
        final errors = validate(miniSchema, probe, catalogs: catalogs);
        if (errors.isEmpty) {
          filled++;
        }
      }
    }

    return (total: total, filled: filled);
  }

  static void _validatePhoto(
    Map<String, dynamic> field,
    dynamic value,
    String label,
    List<String> errors,
  ) {
    final items = DynamicFormRenderer.photoItems(value);
    final required = field['required'] == true;
    final allowMultiple = field['allow_multiple'] == true;
    final maxImages = allowMultiple ? (_asInt(field['max_images']) ?? 4) : 1;
    final captionEnabled = field['caption_enabled'] == true;
    final captionRequired = field['caption_required'] == true && captionEnabled;

    if (required && items.isEmpty) {
      errors.add('$label es obligatorio');
      return;
    }

    if (items.length > maxImages) {
      errors.add('$label admite máximo $maxImages imagen(es)');
    }

    if (!captionRequired) {
      return;
    }

    for (final item in items) {
      final caption = item['caption']?.toString().trim() ?? '';
      if (caption.isEmpty) {
        errors.add('La descripción es obligatoria en $label');
        return;
      }
    }
  }

  static bool _isEmpty(dynamic value) {
    if (value == null) {
      return true;
    }
    if (value is String) {
      return value.trim().isEmpty;
    }
    if (value is List) {
      return value.isEmpty;
    }
    return false;
  }

  static num? _asNum(dynamic value) {
    if (value is num) {
      return value;
    }
    return num.tryParse(value?.toString() ?? '');
  }

  static int? _asInt(dynamic value) {
    if (value is int) {
      return value;
    }
    return int.tryParse(value?.toString() ?? '');
  }

  static List<String> _catalogValues(
    dynamic catalogId,
    List<Map<String, dynamic>> catalogs,
  ) {
    final id = catalogId is int ? catalogId : int.tryParse(catalogId?.toString() ?? '');
    if (id == null) {
      return [];
    }

    for (final catalog in catalogs) {
      if (catalog['id'] == id) {
        final options = catalog['options'];
        if (options is List) {
          return options
              .map((option) {
                if (option is Map) {
                  return option['value']?.toString();
                }
                return null;
              })
              .whereType<String>()
              .toList();
        }
      }
    }

    return [];
  }
}
