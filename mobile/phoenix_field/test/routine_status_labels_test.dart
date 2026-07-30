import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/shared/routine/routine_status_labels.dart';

void main() {
  test('routineStatusLabel maps invoiced status', () {
    expect(routineStatusLabel('invoiced'), 'Facturada');
    expect(localSyncStatusLabel('synced'), 'Sync: enviado');
  });
}
