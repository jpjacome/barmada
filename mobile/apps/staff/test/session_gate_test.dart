import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_staff/l10n/app_localizations.dart';
import 'package:barmada_staff/session_gate.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _FixedSession extends SessionController {
  _FixedSession(this._session);
  final AppSession _session;

  @override
  Future<AppSession> build() async => _session;
}

Widget _app(AppSession session, {Locale? locale}) => ProviderScope(
      overrides: [
        sessionControllerProvider.overrideWith(() => _FixedSession(session)),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: const SessionGate(),
      ),
    );

void main() {
  testWidgets('setup required shows the add-server screen', (tester) async {
    await tester.pumpWidget(_app(const SetupRequired()));
    await tester.pumpAndSettle();
    expect(find.text('Your server'), findsOneWidget);
  });

  testWidgets('logged out shows the login screen for the server',
      (tester) async {
    await tester.pumpWidget(_app(const LoggedOut(
      ServerInfo(url: 'http://10.0.2.2:8000', name: 'La Cantina'),
    )));
    await tester.pumpAndSettle();
    expect(find.text('La Cantina'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });

  // One session per test: a ProviderScope keeps the notifier created from
  // its first overrides, so swapping the session needs a fresh tree.
  testWidgets('the add-server screen renders in Spanish under es',
      (tester) async {
    await tester.pumpWidget(_app(
      const SetupRequired(),
      locale: const Locale('es'),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Tu servidor'), findsOneWidget);
    expect(find.text('Conectar'), findsOneWidget);
  });

  testWidgets('the login screen renders in Spanish under es', (tester) async {
    await tester.pumpWidget(_app(
      const LoggedOut(
        ServerInfo(url: 'http://10.0.2.2:8000', name: 'La Cantina'),
      ),
      locale: const Locale('es'),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Iniciar sesión'), findsOneWidget);
    expect(find.text('Usar otro servidor'), findsOneWidget);
  });
}
