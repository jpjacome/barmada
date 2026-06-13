import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../board/board_providers.dart';

/// The venue's business settings (owner accounts): currency symbol,
/// guest menu language, timezone and the business-day cutoff — the
/// mobile twin of the web's Settings → Business Settings.
///
/// Server messages for invalid input (e.g. a bad timezone id) pass
/// through verbatim, per the app's error policy.
class BusinessSettingsScreen extends ConsumerStatefulWidget {
  const BusinessSettingsScreen({super.key});

  @override
  ConsumerState<BusinessSettingsScreen> createState() =>
      _BusinessSettingsScreenState();
}

class _BusinessSettingsScreenState
    extends ConsumerState<BusinessSettingsScreen> {
  final _currency = TextEditingController();
  final _timezone = TextEditingController();
  String _locale = 'es';
  int _cutoff = 0;
  bool _loaded = false;
  bool _busy = false;
  ApiException? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _currency.dispose();
    _timezone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    // initState can run before the async session resolves — wait for it
    // rather than reading a still-null client.
    var client = ref.read(apiClientProvider);
    client ??= switch (await ref.read(sessionControllerProvider.future)) {
      Authed(:final client) => client,
      _ => null,
    };
    if (client == null || !mounted) return;
    try {
      final settings = await client.settings();
      if (!mounted) return;
      setState(() {
        _currency.text = settings.currencySymbol;
        _timezone.text = settings.businessTimezone ?? '';
        _locale = settings.locale;
        _cutoff = settings.dayCutoffHour.clamp(0, 12);
        _loaded = true;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _save() async {
    final client = ref.read(apiClientProvider);
    if (client == null || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final l10n = AppLocalizations.of(context);
    try {
      await client.updateSettings(
        currencySymbol: _currency.text.trim(),
        locale: _locale,
        businessTimezone: _timezone.text.trim(),
        dayCutoffHour: _cutoff,
      );
      // Currency and timezone feed money formatting and chips app-wide;
      // refresh the session's venue without flashing the splash.
      await ref.read(sessionControllerProvider.notifier).refreshUser();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(l10n.settingsSavedSnack)));
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.businessSettingsTitle)),
      body: !_loaded && _error == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                BarmadaCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      TextField(
                        controller: _currency,
                        enabled: !_busy,
                        maxLength: 5,
                        decoration: InputDecoration(
                          labelText: l10n.currencySymbolLabel,
                          counterText: '',
                        ),
                      ),
                      const SizedBox(height: 20),
                      Text(l10n.guestLanguageLabel,
                          style: theme.textTheme.titleSmall),
                      const SizedBox(height: 8),
                      SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                              value: 'es', label: Text(l10n.languageSpanish)),
                          ButtonSegment(
                              value: 'en', label: Text(l10n.languageEnglish)),
                        ],
                        selected: {_locale},
                        onSelectionChanged: _busy
                            ? null
                            : (selection) =>
                                setState(() => _locale = selection.first),
                      ),
                      const SizedBox(height: 20),
                      TextField(
                        controller: _timezone,
                        enabled: !_busy,
                        autocorrect: false,
                        decoration: InputDecoration(
                          labelText: l10n.timezoneLabel,
                          hintText: 'America/Guayaquil',
                        ),
                      ),
                      const SizedBox(height: 20),
                      Text(l10n.dayCutoffLabel,
                          style: theme.textTheme.titleSmall),
                      const SizedBox(height: 4),
                      Text(
                        l10n.dayCutoffHelp,
                        style:
                            theme.textTheme.bodySmall?.copyWith(color: dimmed),
                      ),
                      const SizedBox(height: 8),
                      DropdownButton<int>(
                        value: _cutoff,
                        isExpanded: true,
                        onChanged: _busy
                            ? null
                            : (value) => setState(() => _cutoff = value ?? 0),
                        items: [
                          for (var hour = 0; hour <= 12; hour++)
                            DropdownMenuItem(
                              value: hour,
                              child:
                                  Text('${hour.toString().padLeft(2, '0')}:00'),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    l10n.errorMessage(_error!),
                    style: TextStyle(color: theme.colorScheme.error),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _busy || !_loaded ? null : _save,
                  style: FilledButton.styleFrom(minimumSize: const Size(0, 50)),
                  icon: _busy
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check, size: 18),
                  label: Text(l10n.saveSettings),
                ),
              ],
            ),
    );
  }
}
