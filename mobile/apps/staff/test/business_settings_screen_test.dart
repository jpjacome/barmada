import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/home/more_screen.dart';
import 'package:barmada_staff/features/settings/business_settings_screen.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _FakeClient extends BarmadaClient {
  _FakeClient() : super(baseUrl: 'http://fake');

  final updates = <Map<String, Object?>>[];
  String currencySymbol = r'$';
  String locale = 'es';

  @override
  Future<BusinessSettings> settings() async => BusinessSettings(
        currencySymbol: currencySymbol,
        locale: locale,
        dayCutoffHour: 4,
        businessName: 'La Cantina',
        // Distinct from the field's hint text so finders see one match.
        businessTimezone: 'Europe/Madrid',
      );

  @override
  Future<BusinessSettings> updateSettings({
    required String currencySymbol,
    required String locale,
    String? businessTimezone,
    int? dayCutoffHour,
  }) async {
    updates.add({
      'currency_symbol': currencySymbol,
      'locale': locale,
      'business_timezone': businessTimezone,
      'day_cutoff_hour': dayCutoffHour,
    });
    this.currencySymbol = currencySymbol;
    this.locale = locale;
    return settings();
  }

  @override
  Future<ApiUser> currentUser() async => _user(this, isEditor: true);
}

ApiUser _user(_FakeClient client, {required bool isEditor}) => ApiUser(
      id: 1,
      name: isEditor ? 'Marta' : 'Ana',
      email: 'owner@bar.example',
      isAdmin: false,
      isEditor: isEditor,
      isStaff: !isEditor,
      venue: VenueSettings(
        currencySymbol: client.currencySymbol,
        guestLocale: client.locale,
        timezone: 'America/Guayaquil',
        dayCutoffHour: 4,
      ),
    );

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

Widget _app(_FakeClient client, Widget home,
        {required bool isEditor, Locale? locale}) =>
    ProviderScope(
      overrides: [
        sessionControllerProvider.overrideWith(() => _FixedSession(Authed(
              server: const ServerInfo(url: 'http://fake', name: 'La Cantina'),
              user: _user(client, isEditor: isEditor),
              client: client,
            ))),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: home,
      ),
    );

Future<void> _settleFrames(WidgetTester tester) async {
  await tester.pump();
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('loads current values and saves the exact payload',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final client = _FakeClient();
    await tester.pumpWidget(
        _app(client, const BusinessSettingsScreen(), isEditor: true));
    await _settleFrames(tester);

    // Loaded state shows what the venue has today.
    expect(find.text('Europe/Madrid'), findsOneWidget);
    expect(find.text('04:00'), findsOneWidget);

    // Change the currency and the guest language.
    await tester.enterText(
        find.widgetWithText(TextField, 'Currency symbol'), '€');
    await tester.tap(find.text('English'));
    await tester.pump();

    await tester.tap(find.text('Save'));
    await _settleFrames(tester);

    expect(client.updates, [
      {
        'currency_symbol': '€',
        'locale': 'en',
        'business_timezone': 'Europe/Madrid',
        'day_cutoff_hour': 4,
      },
    ]);
    expect(find.text('Settings saved.'), findsOneWidget); // snackbar

    await tester.pumpWidget(Container());
    await tester.pump(const Duration(seconds: 5)); // drain snackbar timer
  });

  testWidgets('the settings screen speaks Spanish under es', (tester) async {
    final client = _FakeClient();
    await tester.pumpWidget(_app(client, const BusinessSettingsScreen(),
        isEditor: true, locale: const Locale('es')));
    await _settleFrames(tester);

    expect(find.text('Configuración del negocio'), findsOneWidget);
    expect(find.text('Símbolo de moneda'), findsOneWidget);
    expect(find.text('Idioma del menú para clientes'), findsOneWidget);
    expect(find.text('Guardar'), findsOneWidget);

    await tester.pumpWidget(Container());
  });

  testWidgets('the More entry shows for owners and hides for staff',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final client = _FakeClient();

    await tester.pumpWidget(_app(client, const MoreScreen(), isEditor: true));
    await _settleFrames(tester);
    expect(find.text('Business settings'), findsOneWidget);

    await tester.pumpWidget(Container());
    await tester.pump();

    await tester.pumpWidget(_app(client, const MoreScreen(), isEditor: false));
    await _settleFrames(tester);
    expect(find.text('Business settings'), findsNothing);

    await tester.pumpWidget(Container());
  });
}
