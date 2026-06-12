import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _host(Widget child) => MaterialApp(
      // Widgets must work under any Material theme; the Barmada theme
      // itself is exercised on-device (google_fonts fetches at runtime).
      theme: ThemeData.dark(useMaterial3: true),
      home: Scaffold(body: Center(child: child)),
    );

void main() {
  testWidgets('BarmadaCard renders its child with an outline', (tester) async {
    await tester.pumpWidget(_host(const BarmadaCard(child: Text('Table 4'))));
    expect(find.text('Table 4'), findsOneWidget);
  });

  testWidgets('StatusChip and AlertChip show their labels', (tester) async {
    await tester.pumpWidget(_host(Column(
      children: const [
        StatusChip(label: 'paid', color: Colors.green),
        AlertChip(
          label: 'Bill · table 2',
          color: Colors.teal,
          icon: Icons.receipt_long_outlined,
        ),
      ],
    )));
    expect(find.text('paid'), findsOneWidget);
    expect(find.text('Bill · table 2'), findsOneWidget);
  });

  testWidgets('EmptyState shows title and subtitle', (tester) async {
    await tester.pumpWidget(_host(const EmptyState(
      icon: Icons.nightlife_outlined,
      title: 'All quiet',
      subtitle: 'Nothing pending.',
    )));
    expect(find.text('All quiet'), findsOneWidget);
    expect(find.text('Nothing pending.'), findsOneWidget);
  });

  testWidgets('OrderTimer renders mm:ss and goes overdue', (tester) async {
    final start = DateTime.now().subtract(const Duration(minutes: 6));
    await tester.pumpWidget(_host(OrderTimer(since: start)));
    // 06:0x — at least six minutes on the clock.
    expect(find.textContaining('06:'), findsOneWidget);
    // Pulsing opacity wrapper appears when overdue (scoped inside the
    // timer — MaterialApp's route transitions are FadeTransitions too).
    expect(
      find.descendant(
        of: find.byType(OrderTimer),
        matching: find.byType(FadeTransition),
      ),
      findsOneWidget,
    );
    // Let the ticker settle so the test ends cleanly.
    await tester.pump(const Duration(seconds: 1));
  });
}
