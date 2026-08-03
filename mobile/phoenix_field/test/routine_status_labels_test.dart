import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/shared/routine/routine_status_labels.dart';

void main() {
  test('routineStatusLabel maps known statuses', () {
    expect(routineStatusLabel('invoiced'), 'Facturada');
    expect(routineStatusLabel('assigned'), 'Asignada');
    expect(routineStatusLabel('rejected'), 'Rechazada');
    expect(localSyncStatusLabel('synced'), 'Sync: enviado');
  });

  test('routineStatusHint and color are defined for field statuses', () {
    expect(routineStatusHint('assigned'), contains('campo'));
    expect(routineStatusColor('rejected'), const Color(0xFFF87171));
    expect(routineCanSubmitFromField('assigned'), isTrue);
    expect(routineCanSubmitFromField('rejected'), isTrue);
    expect(routineCanSubmitFromField('submitted'), isFalse);
    expect(routineCanSubmitFromField('pending_sync'), isFalse);
  });

  test('routineRejectionReason reads latest_execution', () {
    expect(
      routineRejectionReason({
        'latest_execution': {'rejection_reason': ' Falta foto '},
      }),
      'Falta foto',
    );
    expect(routineRejectionReason({'latest_execution': {}}), isNull);
  });

  testWidgets('RoutineStatusChip shows label', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: RoutineStatusChip(status: 'assigned')),
      ),
    );
    expect(find.text('Asignada'), findsOneWidget);
  });

  testWidgets('RoutineStatusBanner shows rejection reason', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: RoutineStatusBanner(
            status: 'assigned',
            rejectionReason: 'Falta evidencia',
          ),
        ),
      ),
    );
    expect(find.text('Motivo de rechazo'), findsOneWidget);
    expect(find.text('Falta evidencia'), findsOneWidget);
  });
}
