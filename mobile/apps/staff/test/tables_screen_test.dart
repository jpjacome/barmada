import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/tables/tables_providers.dart';
import 'package:barmada_staff/features/tables/tables_screen.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

AppSession _authed() => Authed(
      server: const ServerInfo(url: 'http://10.0.2.2:8000', name: 'La Cantina'),
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
      client: BarmadaClient(baseUrl: 'http://10.0.2.2:8000'),
    );

Widget _app(List<TableInfo> tables, {Locale? locale}) => ProviderScope(
      overrides: [
        sessionControllerProvider.overrideWith(() => _FixedSession(_authed())),
        tablesProvider.overrideWith((ref) async => tables),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: const TablesScreen(),
      ),
    );

List<TableInfo> _threeTables() => [
      TableInfo(id: 1, tableNumber: 2, status: 'pending_approval'),
      TableInfo(id: 2, tableNumber: 4, status: 'open'),
      TableInfo(id: 3, tableNumber: 7, status: 'closed', reference: 'Terraza'),
    ];

void main() {
  testWidgets('the grid shows each table with its localized state',
      (tester) async {
    await tester.pumpWidget(_app(_threeTables()));
    await tester.pump();
    await tester.pump();

    expect(find.text('2'), findsOneWidget);
    expect(find.text('4'), findsOneWidget);
    expect(find.text('7'), findsOneWidget);
    expect(find.text('Pending approval'), findsOneWidget);
    expect(find.text('Open'), findsOneWidget);
    expect(find.text('Closed'), findsOneWidget);
    expect(find.text('Terraza'), findsOneWidget);

    // Unmount so the 5s poll timer is cancelled before the test ends.
    await tester.pumpWidget(Container());
  });

  testWidgets('the grid speaks Spanish under es', (tester) async {
    await tester.pumpWidget(_app(_threeTables(), locale: const Locale('es')));
    await tester.pump();
    await tester.pump();

    expect(find.text('Pendiente de aprobación'), findsOneWidget);
    expect(find.text('Abierta'), findsOneWidget);
    expect(find.text('Cerrada'), findsOneWidget);

    await tester.pumpWidget(Container());
  });

  testWidgets('an empty venue gets the create-tables hint', (tester) async {
    await tester.pumpWidget(_app(const []));
    await tester.pump();
    await tester.pump();

    expect(find.text('No tables yet'), findsOneWidget);

    await tester.pumpWidget(Container());
  });
}
