import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../settings/business_settings_screen.dart';
import '../settings/locale_controller.dart';

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider).value;
    final authed = session is Authed ? session : null;
    final venue = authed?.user.venue;
    final localeSetting = ref.watch(localeControllerProvider);
    final dimmed =
        Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.tabMore)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (authed != null)
            BarmadaCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    venue?.businessName ??
                        authed.user.businessName ??
                        authed.user.name,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${authed.user.name} · ${authed.user.roleLabel}',
                    style: TextStyle(color: dimmed),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (venue != null)
                        StatusChip(
                          label: l10n.currencyChip(venue.currencySymbol),
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      if (venue != null)
                        StatusChip(
                          label: venue.timezone,
                          color: Theme.of(context).statusColors.info,
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(authed.server.url,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: dimmed)),
                ],
              ),
            ),
          const SizedBox(height: 16),
          // Language is a device preference (whoever holds the phone),
          // not the venue's guest-language setting.
          BarmadaCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l10n.languageLabel,
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                SegmentedButton<LocaleSetting>(
                  segments: [
                    ButtonSegment(
                      value: LocaleSetting.system,
                      label: Text(l10n.languageSystem),
                      icon: const Icon(Icons.smartphone_outlined),
                    ),
                    ButtonSegment(
                      value: LocaleSetting.english,
                      label: Text(l10n.languageEnglish),
                    ),
                    ButtonSegment(
                      value: LocaleSetting.spanish,
                      label: Text(l10n.languageSpanish),
                    ),
                  ],
                  selected: {localeSetting},
                  onSelectionChanged: (selection) => ref
                      .read(localeControllerProvider.notifier)
                      .set(selection.first),
                ),
              ],
            ),
          ),
          // Business settings are editor-only server-side; staff don't
          // get a dead-end entry.
          if (authed?.user.isEditor ?? false) ...[
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const BusinessSettingsScreen(),
                ),
              ),
              icon: const Icon(Icons.storefront_outlined),
              label: Text(l10n.businessSettingsTitle),
            ),
          ],
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).signOut(),
            icon: const Icon(Icons.logout),
            label: Text(l10n.signOut),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).forgetServer(),
            child: Text(l10n.forgetServer),
          ),
          const SizedBox(height: 24),
          Text(
            l10n.aboutFooter,
            textAlign: TextAlign.center,
            style:
                Theme.of(context).textTheme.bodySmall?.copyWith(color: dimmed),
          ),
        ],
      ),
    );
  }
}
