import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_renderer.dart';

void main() {
  group('DynamicFormRenderer.validateRequired', () {
    final schema = {
      'sections': [
        {
          'title': 'Datos',
          'fields': [
            {'key': 'notes', 'type': 'text', 'label': 'Notas', 'required': true},
            {'key': 'tags', 'type': 'multiselect', 'label': 'Etiquetas', 'required': true},
            {'key': 'time', 'type': 'duration', 'label': 'Tiempo', 'required': true},
            {'key': 'photo', 'type': 'photo', 'label': 'Foto', 'required': true},
          ],
        },
      ],
    };

    test('detects missing multiselect and duration', () {
      final missing = DynamicFormRenderer.validateRequired(schema, {
        'notes': 'ok',
      });

      expect(missing, contains('Etiquetas'));
      expect(missing, contains('Tiempo'));
      expect(missing, contains('Foto'));
      expect(missing, isNot(contains('Notas')));
    });

    test('accepts valid multiselect and duration', () {
      final missing = DynamicFormRenderer.validateRequired(schema, {
        'notes': 'ok',
        'tags': ['a', 'b'],
        'time': 90,
        'photo': 'local:abc',
      });

      expect(missing, isEmpty);
    });

    test('detects missing boolean and date fields', () {
      final extendedSchema = {
        'sections': [
          {
            'fields': [
              {'key': 'ok', 'type': 'boolean', 'label': 'Confirmo', 'required': true},
              {'key': 'when', 'type': 'date', 'label': 'Fecha', 'required': true},
              {'key': 'at', 'type': 'datetime', 'label': 'Inicio', 'required': true},
            ],
          },
        ],
      };

      final missing = DynamicFormRenderer.validateRequired(extendedSchema, {
        'ok': false,
      });

      expect(missing, contains('Confirmo'));
      expect(missing, contains('Fecha'));
      expect(missing, contains('Inicio'));
    });

    test('accepts valid boolean and date fields', () {
      final extendedSchema = {
        'sections': [
          {
            'fields': [
              {'key': 'ok', 'type': 'boolean', 'label': 'Confirmo', 'required': true},
              {'key': 'when', 'type': 'date', 'label': 'Fecha', 'required': true},
              {'key': 'at', 'type': 'datetime', 'label': 'Inicio', 'required': true},
            ],
          },
        ],
      };

      final missing = DynamicFormRenderer.validateRequired(extendedSchema, {
        'ok': true,
        'when': '2026-07-29',
        'at': '2026-07-29T10:30',
      });

      expect(missing, isEmpty);
    });
  });
}
