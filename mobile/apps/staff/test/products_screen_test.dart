import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/products/products_screen.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// A scripted catalog: two beers and an uncategorized snack. Toggling
/// flips real state so the refetch shows the transition.
class _FakeClient extends BarmadaClient {
  _FakeClient() : super(baseUrl: 'http://fake');

  final toggleCalls = <int>[];
  final availability = <int, bool>{1: true, 2: false, 3: true};

  @override
  Future<List<ProductInfo>> products({bool? available}) async => [
        ProductInfo(
          id: 1,
          name: 'Pilsener',
          price: 2.5,
          isAvailable: availability[1]!,
          categoryName: 'Beers',
        ),
        ProductInfo(
          id: 2,
          name: 'Club Premium',
          price: 3,
          isAvailable: availability[2]!,
          categoryName: 'Beers',
        ),
        ProductInfo(
          id: 3,
          name: 'Empanada',
          price: 1.75,
          isAvailable: availability[3]!,
        ),
      ];

  @override
  Future<ProductInfo> toggleProductAvailability(int productId) async {
    toggleCalls.add(productId);
    availability[productId] = !availability[productId]!;
    final all = await products();
    return all.firstWhere((p) => p.id == productId);
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
        home: const ProductsScreen(),
      ),
    );

Future<void> _settleFrames(WidgetTester tester) async {
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('the catalog groups by category with prices and sold-out',
      (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    expect(find.text('Beers'), findsOneWidget); // category header
    expect(find.text('Others'), findsOneWidget); // uncategorized bucket
    expect(find.text('Pilsener'), findsOneWidget);
    expect(find.text('€2.50'), findsOneWidget); // venue currency
    expect(find.text('Sold out'), findsOneWidget); // Club Premium is 86'd

    await tester.pumpWidget(Container());
  });

  testWidgets('tapping a row 86es the product through the API', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    await tester.tap(find.text('Pilsener'));
    await _settleFrames(tester);

    expect(client.toggleCalls, [1]);
    expect(find.text('Pilsener marked sold out.'), findsOneWidget); // snackbar
    expect(find.text('Sold out'), findsNWidgets(2)); // now two 86'd

    // And back on sale. The first snackbar is mid-display: give its
    // hide animation time before the queued one appears.
    await tester.tap(find.text('Pilsener'));
    await _settleFrames(tester);
    await tester.pump(const Duration(milliseconds: 750));

    expect(client.toggleCalls, [1, 1]);
    expect(find.text('Pilsener is back on sale.'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 3)); // drain snackbar timer
  });

  testWidgets('search filters the catalog', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client));
    await _settleFrames(tester);

    await tester.enterText(find.byType(TextField), 'empan');
    await _settleFrames(tester);

    expect(find.text('Empanada'), findsOneWidget);
    expect(find.text('Pilsener'), findsNothing);
    expect(find.text('Beers'), findsNothing); // empty groups vanish

    await tester.enterText(find.byType(TextField), 'zzz');
    await _settleFrames(tester);
    expect(find.text('No products match your search.'), findsOneWidget);

    await tester.pumpWidget(Container());
  });

  testWidgets('the catalog speaks Spanish under es', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, locale: const Locale('es')));
    await _settleFrames(tester);

    expect(find.text('Otros'), findsOneWidget);
    expect(find.text('Agotado'), findsOneWidget);

    await tester.tap(find.text('Pilsener'));
    await _settleFrames(tester);
    expect(find.text('Pilsener marcado como agotado.'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 3)); // drain snackbar timer
  });
}
