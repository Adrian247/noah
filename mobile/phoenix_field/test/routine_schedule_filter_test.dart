import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/shared/dynamic_form/dynamic_form_validator.dart';
import 'package:phoenix_field/shared/routine/routine_schedule_filter.dart';

void main() {
  group('RoutineScheduleFilter', () {
    test('includes routines without scheduled_at', () {
      expect(
        RoutineScheduleFilter.isScheduledToday({}, DateTime(2026, 7, 29)),
        isTrue,
      );
    });

    test('matches same local calendar day', () {
      expect(
        RoutineScheduleFilter.isScheduledToday(
          {'scheduled_at': '2026-07-29T15:30:00Z'},
          DateTime(2026, 7, 29, 8),
        ),
        isTrue,
      );
    });

    test('excludes other days', () {
      expect(
        RoutineScheduleFilter.isScheduledToday(
          {'scheduled_at': '2026-07-28T15:30:00Z'},
          DateTime(2026, 7, 29),
        ),
        isFalse,
      );
    });
  });

  group('DynamicFormValidator', () {
    test('requires photo captions when configured', () {
      final schema = {
        'sections': [
          {
            'fields': [
              {
                'key': 'evidence',
                'type': 'photo',
                'label': 'Evidencia',
                'required': true,
                'caption_enabled': true,
                'caption_required': true,
              },
            ],
          },
        ],
      };

      final errors = DynamicFormValidator.validate(schema, {
        'evidence': {'path': 'local:abc', 'caption': ''},
      });

      expect(errors, isNotEmpty);
    });

    test('validates number min and max', () {
      final schema = {
        'sections': [
          {
            'fields': [
              {
                'key': 'km',
                'type': 'number',
                'label': 'Kilometraje',
                'min': 0,
                'max': 999999,
              },
            ],
          },
        ],
      };

      expect(
        DynamicFormValidator.validate(schema, {'km': -1}),
        contains('Kilometraje debe ser al menos 0'),
      );
      expect(DynamicFormValidator.validate(schema, {'km': 120}), isEmpty);
    });
  });
}
