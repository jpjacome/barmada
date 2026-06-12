import 'package:barmada_core/barmada_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/auth/login_screen.dart';
import 'features/home/home_shell.dart';
import 'features/setup/add_server_screen.dart';
import 'l10n/api_error_l10n.dart';
import 'l10n/app_localizations.dart';

/// Routes the app by session state:
/// no server -> add server, server -> login, token -> home.
class SessionGate extends ConsumerWidget {
  const SessionGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);

    return switch (session) {
      AsyncData(value: SetupRequired()) => const AddServerScreen(),
      AsyncData(value: LoggedOut(:final server)) => LoginScreen(server: server),
      AsyncData(value: Authed()) => const HomeShell(),
      AsyncError(:final error) => _StartupError(error: error),
      _ => const _Splash(),
    };
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // The wordmark, not a phrase — identical in every language.
            Text('Barmada', style: Theme.of(context).textTheme.displayMedium),
            const SizedBox(height: 24),
            const SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
          ],
        ),
      ),
    );
  }
}

class _StartupError extends ConsumerWidget {
  const _StartupError({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    return Scaffold(
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(l10n.startupErrorTitle,
                style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 12),
            Text(l10n.errorMessage(error)),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: () => ref.invalidate(sessionControllerProvider),
              child: Text(l10n.retry),
            ),
          ],
        ),
      ),
    );
  }
}
