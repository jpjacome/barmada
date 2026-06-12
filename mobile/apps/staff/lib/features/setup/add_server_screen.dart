import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';

/// Resolves an error message against the *current* locale at build time,
/// so text on screen follows a live language switch.
typedef _ErrorText = String Function(AppLocalizations l10n);

/// First-run: point the app at a Barmada server (self-hosting is the
/// product, so the address is the user's, not ours).
class AddServerScreen extends ConsumerStatefulWidget {
  const AddServerScreen({super.key});

  @override
  ConsumerState<AddServerScreen> createState() => _AddServerScreenState();
}

class _AddServerScreenState extends ConsumerState<AddServerScreen> {
  final _controller = TextEditingController();
  bool _busy = false;
  _ErrorText? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    final url = _controller.text.trim();
    if (url.isEmpty) {
      setState(() => _error = (l10n) => l10n.setupEmptyUrl);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final meta = await ref
          .read(sessionControllerProvider.notifier)
          .addServer(url.startsWith('http') ? url : 'https://$url');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content:
                Text(AppLocalizations.of(context).setupConnected(meta.name))),
      );
    } on ApiException catch (e) {
      setState(() => _error = (l10n) => l10n.errorMessage(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final textTheme = Theme.of(context).textTheme;
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
                  // The wordmark — identical in every language.
                  Text('Barmada', style: textTheme.displayMedium),
                  const SizedBox(height: 4),
                  Text(
                    l10n.setupTagline,
                    style: textTheme.bodyLarge?.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.7),
                    ),
                  ),
                  const SizedBox(height: 40),
                  Text(l10n.setupServerLabel, style: textTheme.titleMedium),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _controller,
                    keyboardType: TextInputType.url,
                    autocorrect: false,
                    enabled: !_busy,
                    decoration: InputDecoration(
                      hintText: l10n.setupServerHint,
                    ),
                    onSubmitted: (_) => _connect(),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    l10n.setupEmulatorTip,
                    style: textTheme.bodySmall?.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.55),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!(l10n),
                      style:
                          TextStyle(color: Theme.of(context).colorScheme.error),
                    ),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _busy ? null : _connect,
                    child: _busy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.5),
                          )
                        : Text(l10n.connect),
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
