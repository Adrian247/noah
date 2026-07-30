import 'package:flutter_test/flutter_test.dart';
import 'package:phoenix_field/app.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

void main() {
  testWidgets('Phoenix Field app smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: PhoenixFieldApp()));
    expect(find.text('Phoenix Campo'), findsOneWidget);
  });
}
