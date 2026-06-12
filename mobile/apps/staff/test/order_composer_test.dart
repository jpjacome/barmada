import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/tables/order_composer_screen.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:barmada_staff/l10n/app_localizations.dart';

/// Catalog: Pilsener €2.50 (Beers), Club Premium €3.00 (Beers, SOLD OUT),
/// Empanada €1.75 (uncategorized). createOrder records the exact payload.
class _FakeClient extends BarmadaClient {
  _FakeClient() : super(baseUrl: 'http://fake');

  final createdOrders = <(int, Map<int, int>, String?)>[];

  @override
  Future<List<ProductInfo>> products({bool? available}) async => [
        ProductInfo(
            id: 1,
            name: 'Pilsener',
            price: 2.5,
            isAvailable: true,
            categoryName: 'Beers'),
        ProductInfo(
            id: 2,
            name: 'Club Premium',
            price: 3,
            isAvailable: false,
            categoryName: 'Beers'),
        ProductInfo(id: 3, name: 'Empanada', price: 1.75, isAvailable: true),
      ];

  @override
  Future<OrderInfo> createOrder({
    required int tableId,
    required Map<int, int> products,
    String? note,
  }) async {
    createdOrders.add((tableId, Map.of(products), note));
    return OrderInfo(
      id: 900,
      tableId: tableId,
      status: 'pending',
      total: 0,
      paid: 0,
      left: 0,
      items: const [],
    );
  }
}

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

AppSession _authed(_FakeClient client) => Authed(
      server: const ServerInfo(url: 'http://fake', name: 'La Cantina'),
      user: ApiUser(
        id: 1,
        name: 'Ana',
        email: 'ana@bar.example',
        isAdmin: false,
        isEditor: false,
        isStaff: true,
        venue: VenueSettings(
          currencySymbol: '€',
          guestLocale: 'es',
          timezone: 'America/Guayaquil',
          dayCutoffHour: 6,
        ),
      ),
      client: client,
    );

/// A host screen that pushes the composer, so popping after submit has
/// somewhere real to land (and the snackbar a messenger to show on).
Widget _app(_FakeClient client, {Locale? locale}) => ProviderScope(
      overrides: [
        sessionControllerProvider
            .overrideWith(() => _FixedSession(_authed(client))),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Scaffold(
          body: Builder(
            builder: (context) => Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) =>
                        const OrderComposerScreen(tableId: 4, tableNumber: 4),
                  ),
                ),
                child: const Text('HOST'),
              ),
            ),
          ),
        ),
      ),
    );

Future<void> _open(WidgetTester tester, _FakeClient client,
    {Locale? locale}) async {
  await tester.pumpWidget(_app(client, locale: locale));
  await tester.tap(find.text('HOST'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 350)); // route animation
  await tester.pump();
}

void main() {
  testWidgets('composing builds the exact payload and submits it',
      (tester) async {
    final client = _FakeClient();
    await _open(tester, client);

    // Empty cart: review disabled.
    expect(find.text('No items'), findsOneWidget);
    expect(
      tester
          .widget<FilledButton>(
              find.widgetWithText(FilledButton, 'Review order'))
          .onPressed,
      isNull,
    );

    // 2 × Pilsener + 1 × Empanada, then one Pilsener removed.
    await tester.tap(find.text('Pilsener'));
    await tester.pump();
    await tester.tap(find.text('Pilsener'));
    await tester.pump();
    expect(find.text('2 items'), findsOneWidget);
    expect(find.text('€5.00'), findsOneWidget); // running total

    await tester.tap(find.text('Empanada'));
    await tester.pump();
    expect(find.text('3 items'), findsOneWidget);
    expect(find.text('€6.75'), findsOneWidget);

    await tester.tap(find.descendant(
      of: find.widgetWithText(BarmadaCard, 'Pilsener'),
      matching: find.byIcon(Icons.remove_circle_outline),
    ));
    await tester.pump();
    expect(find.text('2 items'), findsOneWidget);
    expect(find.text('€4.25'), findsOneWidget);

    // Review: recap lines + note, then confirm.
    await tester.tap(find.text('Review order'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 350)); // sheet animation

    expect(find.text('1 × Pilsener'), findsOneWidget);
    expect(find.text('1 × Empanada'), findsOneWidget);

    await tester.enterText(find.byType(TextField).last, 'sin hielo');
    await tester.tap(find.text('Confirm order'));
    // No poll timers in the composer, so settling is safe: sheet pop +
    // route pop + snackbar entrance all finish.
    await tester.pumpAndSettle();

    // Records hold the Map by reference — compare fields for deep equality.
    expect(client.createdOrders, hasLength(1));
    final (tableId, payload, note) = client.createdOrders.single;
    expect(tableId, 4);
    expect(payload, {1: 1, 3: 1});
    expect(note, 'sin hielo');
    // Back on the host with the confirmation visible.
    expect(find.byType(OrderComposerScreen), findsNothing);
    expect(find.text('Order placed for table 4.'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 5)); // drain snackbar timer
  });

  testWidgets('sold-out products cannot be added', (tester) async {
    final client = _FakeClient();
    await _open(tester, client);

    await tester.tap(find.text('Club Premium'));
    await tester.pump();

    expect(find.text('No items'), findsOneWidget); // nothing added
    expect(find.text('Sold out'), findsOneWidget);
    // No stepper appeared anywhere.
    expect(find.byIcon(Icons.remove_circle_outline), findsNothing);

    await tester.pumpWidget(Container());
  });

  testWidgets('the composer speaks Spanish under es', (tester) async {
    final client = _FakeClient();
    await _open(tester, client, locale: const Locale('es'));

    expect(find.text('Nuevo pedido · mesa 4'), findsOneWidget);
    expect(find.text('Sin artículos'), findsOneWidget);

    await tester.tap(find.text('Pilsener'));
    await tester.pump();
    expect(find.text('1 artículo'), findsOneWidget);

    await tester.tap(find.text('Revisar pedido'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 350));

    await tester.tap(find.text('Confirmar pedido'));
    await tester.pumpAndSettle();

    expect(client.createdOrders, hasLength(1));
    final (tableId, payload, note) = client.createdOrders.single;
    expect(tableId, 4);
    expect(payload, {1: 1});
    expect(note, isNull);
    expect(find.text('Pedido enviado a la mesa 4.'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 5)); // drain snackbar timer
  });
}
