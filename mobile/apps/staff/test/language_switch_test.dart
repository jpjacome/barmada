import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/features/home/more_screen.dart';
import 'package:barmada_staff/features/settings/locale_controller.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

/// Mirrors BarmadaStaffApp's locale wiring (provider-driven MaterialApp)
/// without the Barmada theme — google_fonts wants the network in tests.
class _Harness extends ConsumerWidget {
  const _Harness();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final localeSetting = ref.watch(localeControllerProvider);
    return MaterialApp(
      theme: ThemeData.dark(useMaterial3: true),
      locale: localeSetting.locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: const MoreScreen(),
    );
  }
}

void main() {
  testWidgets('picking Español re-renders the app live and persists',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final session = Authed(
      server: const ServerInfo(url: 'http://10.0.2.2:8000', name: 'La Cantina'),
      user: ApiUser(
        id: 1,
        name: 'Ana',
        email: 'ana@bar.example',
        isAdmin: false,
        isEditor: false,
        isStaff: true,
      ),
      client: BarmadaClient(baseUrl: 'http://10.0.2.2:8000'),
    );

    await tester.pumpWidget(ProviderScope(
      overrides: [
        sessionControllerProvider.overrideWith(() => _FixedSession(session)),
      ],
      child: const _Harness(),
    ));
    await tester.pumpAndSettle();

    // English first (test devices default to en_US).
    expect(find.text('Sign out'), findsOneWidget);
    expect(find.text('Language'), findsOneWidget);

    await tester.tap(find.text('Español'));
    await tester.pumpAndSettle();

    // The whole screen now reads in Spanish — no restart.
    expect(find.text('Cerrar sesión'), findsOneWidget);
    expect(find.text('Idioma'), findsOneWidget);
    expect(find.text('Sign out'), findsNothing);

    // And the choice was written through to disk.
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('barmada.locale'), 'es');

    // Back to English via the picker still works (round trip).
    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();
    expect(find.text('Sign out'), findsOneWidget);
  });
}
