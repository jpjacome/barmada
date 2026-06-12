import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, required this.server});

  final ServerInfo server;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  ApiException? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    // Resolved before the await: the device label travels to the server.
    final deviceName = AppLocalizations.of(context).defaultDeviceName;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(sessionControllerProvider.notifier).signIn(
            email: _email.text.trim(),
            password: _password.text,
            deviceName: deviceName,
          );
    } on ApiException catch (e) {
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;
    final dimmed =
        Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(widget.server.name, style: textTheme.displaySmall),
                  const SizedBox(height: 4),
                  Text(widget.server.url,
                      style: textTheme.bodySmall?.copyWith(color: dimmed)),
                  const SizedBox(height: 32),
                  TextField(
                    controller: _email,
                    enabled: !_busy,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    decoration: InputDecoration(labelText: l10n.emailLabel),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _password,
                    enabled: !_busy,
                    obscureText: true,
                    decoration: InputDecoration(labelText: l10n.passwordLabel),
                    onSubmitted: (_) => _signIn(),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      l10n.errorMessage(_error!),
                      style:
                          TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _busy ? null : _signIn,
                    child: _busy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.5),
                          )
                        : Text(l10n.signIn),
                  ),
                  const SizedBox(height: 12),
                  TextButton(
                    onPressed: _busy
                        ? null
                        : () => ref
                            .read(sessionControllerProvider.notifier)
                            .forgetServer(),
                    child: Text(l10n.useDifferentServer),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
