import 'package:barmada_core/barmada_core.dart';
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

Widget _app(AppSession session) => ProviderScope(
      overrides: [
        sessionControllerProvider
            .overrideWith(() => _FixedSession(session)),
      ],
      child: MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
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
}
