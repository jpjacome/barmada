import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/analytics/analytics_screen.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Scripted analytics: €152.50 over 23 orders, Pilsener on top, a busy
/// 21:00, one staff member taking orders next to the QR guests.
class _FakeClient extends BarmadaClient {
  _FakeClient() : super(baseUrl: 'http://fake');

  final requestedRanges = <String>[];

  @override
  Future<AnalyticsSummary> analyticsSummary({String range = 'today'}) async {
    requestedRanges.add(range);
    return AnalyticsSummary(
      range: range,
      currencySymbol: '€',
      totalSales: 152.50,
      orderCount: 23,
      averageOrderValue: 6.63,
      hourDistribution: {20: 5, 21: 14, 22: 4},
      topProduct: 'Pilsener',
      peakHour: 21,
    );
  }

  @override
  Future<ProductStats> analyticsProducts({String range = 'today'}) async =>
      ProductStats(
        range: range,
        topProducts: [
          RankedStat(id: 11, name: 'Pilsener', quantity: 14, revenue: 35),
          RankedStat(id: 12, name: 'Empanada', quantity: 6, revenue: 10.5),
        ],
        leastProducts: const [],
        categorySales: [
          RankedStat(id: 2, name: 'Beers', quantity: 18, revenue: 47.5),
        ],
        categoryOrders: const [],
      );

  @override
  Future<ServiceOpsStats> analyticsServiceOps({String range = 'today'}) async =>
      ServiceOpsStats(
        range: range,
        sessions: 9,
        sessionReopenings: 1,
        qrScans: 31,
        staffOrderCounts: [
          CountRow(label: 'Guests (QR)', orders: 19),
          CountRow(label: 'Ana', orders: 4),
        ],
        tableUsage: [CountRow(label: '4', orders: 11)],
        mostUsedTable: '4',
        avgSessionDurationMinutes: 73.4,
        tableTurnover: 1.5,
        qrToOrderConversion: 74.2,
        avgTimeQrToOrderMinutes: 2.4,
      );
}

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

AppSession _authed(_FakeClient client, {required bool isEditor}) => Authed(
      server: const ServerInfo(url: 'http://fake', name: 'La Cantina'),
      user: ApiUser(
        id: 1,
        name: isEditor ? 'Marta' : 'Ana',
        email: 'owner@bar.example',
        isAdmin: false,
        isEditor: isEditor,
        isStaff: !isEditor,
        venue: VenueSettings(
          currencySymbol: '€',
          guestLocale: 'es',
          timezone: 'America/Guayaquil',
          dayCutoffHour: 6,
        ),
      ),
      client: client,
    );

Widget _app(_FakeClient client, {required bool isEditor, Locale? locale}) =>
    ProviderScope(
      overrides: [
        sessionControllerProvider.overrideWith(
            () => _FixedSession(_authed(client, isEditor: isEditor))),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: const AnalyticsScreen(),
      ),
    );

Future<void> _settleFrames(WidgetTester tester) async {
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('the owner sees summary, hours, top products and service ops',
      (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, isEditor: true));
    await _settleFrames(tester);

    // Summary headline in the venue currency.
    expect(find.text('€152.50'), findsOneWidget);
    expect(find.text('23'), findsOneWidget); // orders
    expect(find.text('21:00'), findsOneWidget); // peak hour
    expect(find.textContaining('Pilsener'), findsWidgets);
    expect(find.text('Orders by hour'), findsOneWidget);

    // Lower cards live below the test viewport — scroll to them
    // (the ListView materializes children lazily).
    await tester.scrollUntilVisible(find.text('×14'), 200);
    expect(find.text('Top products'), findsOneWidget);
    expect(find.text('×14'), findsOneWidget); // ranked quantity

    await tester.scrollUntilVisible(find.text('Guests (QR)'), 200);
    expect(find.text('Service'), findsOneWidget);
    expect(find.text('Guests (QR)'), findsOneWidget);
    expect(find.text('Ana'), findsOneWidget);

    expect(client.requestedRanges, ['today']);

    await tester.pumpWidget(Container());
  });

  testWidgets('switching the range refetches with the new window',
      (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, isEditor: true));
    await _settleFrames(tester);

    await tester.tap(find.text('7 days'));
    await _settleFrames(tester);

    expect(client.requestedRanges, ['today', '7days']);

    await tester.pumpWidget(Container());
  });

  testWidgets('staff accounts get the owner-only note, not a 403',
      (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, isEditor: false));
    await _settleFrames(tester);

    expect(find.text('Owner access only'), findsOneWidget);
    // And no doomed requests were fired.
    expect(client.requestedRanges, isEmpty);

    await tester.pumpWidget(Container());
  });

  testWidgets('analytics speak Spanish under es', (tester) async {
    final client = _FakeClient();
    await tester
        .pumpWidget(_app(client, isEditor: true, locale: const Locale('es')));
    await _settleFrames(tester);

    expect(find.text('Hoy'), findsOneWidget);
    expect(find.text('Ventas totales'), findsOneWidget);
    expect(find.text('Pedidos por hora'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('Productos más vendidos'), 200);
    expect(find.text('Productos más vendidos'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('Clientes (QR)'), 200);
    expect(find.text('Clientes (QR)'), findsOneWidget); // localized marker

    await tester.pumpWidget(Container());
  });
}
