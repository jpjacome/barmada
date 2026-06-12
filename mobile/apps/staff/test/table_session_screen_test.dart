import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/tables/table_session_screen.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// A scripted server: one table (#4) with one €5.00 Pilsener on the bill.
/// Mutations flip its state so the screen's refetch-after-action shows
/// real transitions.
class _FakeClient extends BarmadaClient {
  _FakeClient({this.tableStatus = 'open'}) : super(baseUrl: 'http://fake');

  String tableStatus;
  bool itemPaid = false;
  final toggleCalls = <(int, int, int)>[];
  final closeCalls = <bool>[];
  int openCalls = 0;
  int settleCalls = 0;

  OrderInfo _order() => OrderInfo(
        id: 142,
        tableId: 4,
        status: 'pending',
        total: 5,
        paid: itemPaid ? 5 : 0,
        left: itemPaid ? 0 : 5,
        items: [
          OrderItemInfo(
            id: 1,
            productId: 11,
            itemIndex: 0,
            price: 5,
            isPaid: itemPaid,
            productName: 'Pilsener',
          ),
        ],
      );

  @override
  Future<SessionBill> tableSession(int tableId) async {
    final open = tableStatus == 'open';
    return SessionBill(
      orders: open ? [_order()] : const [],
      total: open ? 5 : 0,
      paid: open && itemPaid ? 5 : 0,
      left: open && !itemPaid ? 5 : 0,
      sessionId: open ? 9 : null,
      sessionStatus: open ? 'open' : null,
      openedAt: open ? DateTime.utc(2026, 6, 12, 20, 30) : null,
      table: TableInfo(id: 4, tableNumber: 4, status: tableStatus),
    );
  }

  @override
  Future<OrderInfo> toggleItemPaid({
    required int orderId,
    required int productId,
    required int itemIndex,
  }) async {
    toggleCalls.add((orderId, productId, itemIndex));
    itemPaid = !itemPaid;
    return _order();
  }

  @override
  Future<void> closeTable(int tableId, {bool settle = false}) async {
    closeCalls.add(settle);
    if (settle) itemPaid = true;
    tableStatus = 'closed';
  }

  @override
  Future<void> openTable(int tableId) async {
    openCalls++;
    tableStatus = 'open';
  }

  @override
  Future<void> settleTable(int tableId) async {
    settleCalls++;
    itemPaid = true;
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
        home: const TableSessionScreen(tableId: 4, tableNumber: 4),
      ),
    );

Future<void> _settleFrames(WidgetTester tester) async {
  // Explicit pumps — pumpAndSettle would chase the 5s poll forever.
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('tapping an item ticks it paid through the API', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    // The unpaid bill: venue currency everywhere, no tick yet.
    expect(find.text('Pilsener'), findsOneWidget);
    // Total, Remaining, order header, item price.
    expect(find.text('€5.00'), findsNWidgets(4));
    expect(find.text('€0.00'), findsOneWidget); // Paid column
    expect(find.byIcon(Icons.check_circle), findsNothing);

    await tester.tap(find.text('Pilsener'));
    await _settleFrames(tester);

    expect(client.toggleCalls, [(142, 11, 0)]);
    expect(find.byIcon(Icons.check_circle), findsOneWidget);

    await tester.pumpWidget(Container());
  });

  testWidgets('closing with an unpaid balance asks, settle & close settles',
      (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    await tester.tap(find.text('Close table'));
    await _settleFrames(tester);

    expect(find.text('Close table 4?'), findsOneWidget);
    expect(find.textContaining('€5.00 is still unpaid'), findsOneWidget);

    await tester.tap(find.text('Settle & close'));
    await _settleFrames(tester);

    expect(client.closeCalls, [true]);
    expect(find.text('Table 4 closed.'), findsOneWidget); // snackbar
    expect(find.text('This table is closed'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 5)); // drain snackbar timer
  });

  testWidgets('a closed table offers open and comes back live', (tester) async {
    final client = _FakeClient(tableStatus: 'closed');
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    expect(find.text('This table is closed'), findsOneWidget);
    // No manual order entry on a closed table — orders need a session.
    expect(find.text('New order'), findsNothing);

    await tester.tap(find.text('Open table'));
    await _settleFrames(tester);

    expect(client.openCalls, 1);
    expect(find.text('Table 4 is open.'), findsOneWidget); // snackbar
    // The session is live now: the New-order FAB appears.
    expect(find.text('New order'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 5)); // drain snackbar timer
  });

  testWidgets('the session screen speaks Spanish under es', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, locale: const Locale('es')));
    await _settleFrames(tester);

    expect(find.text('Abierta'), findsOneWidget); // status chip
    expect(find.text('Pagado'), findsOneWidget); // totals column
    expect(find.text('Cerrar mesa'), findsOneWidget); // close action
    expect(find.text('Marcar todo pagado'), findsOneWidget);

    await tester.pumpWidget(Container());
  });
}
