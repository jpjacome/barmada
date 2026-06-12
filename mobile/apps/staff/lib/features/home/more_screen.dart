import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider).value;
    final authed = session is Authed ? session : null;
    final venue = authed?.user.venue;
    final dimmed =
        Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6);

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
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
                          label: 'Currency ${venue.currencySymbol}',
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
          OutlinedButton.icon(
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).signOut(),
            icon: const Icon(Icons.logout),
            label: const Text('Sign out'),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).forgetServer(),
            child: const Text('Forget this server'),
          ),
          const SizedBox(height: 24),
          Text(
            'Barmada Staff · Phase 1 scaffold\n'
            'Self-hosted, zero commission, yours.',
            textAlign: TextAlign.center,
            style:
                Theme.of(context).textTheme.bodySmall?.copyWith(color: dimmed),
          ),
        ],
      ),
    );
  }
}
