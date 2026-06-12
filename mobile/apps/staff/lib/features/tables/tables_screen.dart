import 'dart:async';

import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import 'table_session_screen.dart';
import 'tables_providers.dart';

/// The tables grid: every table's live state at a glance, color-coded
/// like the web dashboard — tap a table for its session and bill.
class TablesScreen extends ConsumerStatefulWidget {
  const TablesScreen({super.key});

  @override
  ConsumerState<TablesScreen> createState() => _TablesScreenState();
}

class _TablesScreenState extends ConsumerState<TablesScreen> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) ref.invalidate(tablesProvider);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final tables = ref.watch(tablesProvider);
    final snapshot = tables.valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.tabTables),
        actions: [
          if (tables.isLoading && snapshot != null)
            const Padding(
              padding: EdgeInsets.only(right: 16),
              child: Center(
                child: SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(tablesProvider),
        child: switch ((snapshot, tables)) {
          (null, AsyncError(:final error)) => ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 120),
                EmptyState(
                  icon: Icons.wifi_off_outlined,
                  title: l10n.boardErrorTitle,
                  subtitle:
                      '${l10n.errorMessage(error)}\n${l10n.boardErrorRetryHint}',
                ),
              ],
            ),
          (null, _) => const Center(child: CircularProgressIndicator()),
          (final List<TableInfo> data, _) when data.isEmpty => ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 120),
                EmptyState(
                  icon: Icons.table_bar_outlined,
                  title: l10n.noTablesYet,
                  subtitle: l10n.noTablesSubtitle,
                ),
              ],
            ),
          (final List<TableInfo> data, _) => GridView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                maxCrossAxisExtent: 200,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.25,
              ),
              itemCount: data.length,
              itemBuilder: (context, index) => _TableCard(table: data[index]),
            ),
        },
      ),
    );
  }
}

class _TableCard extends ConsumerWidget {
  const _TableCard({required this.table});

  final TableInfo table;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;

    final (label, color) = switch (table.status) {
      'open' => (l10n.statusOpen, status.paid),
      'pending_approval' => (l10n.statusPendingApproval, status.pending),
      _ => (
          l10n.statusClosed,
          theme.colorScheme.onSurface.withValues(alpha: 0.45)
        ),
    };

    return BarmadaCard(
      // Closed tables recede; attention states glow in their color.
      borderColor: table.isOpen || table.isAwaitingApproval
          ? color.withValues(alpha: 0.75)
          : theme.colorScheme.primary.withValues(alpha: 0.35),
      padding: const EdgeInsets.all(12),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TableSessionScreen(
            tableId: table.id,
            tableNumber: table.tableNumber,
          ),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${table.tableNumber}',
                  style: theme.textTheme.displaySmall
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
              Icon(
                table.isAwaitingApproval
                    ? Icons.person_add_alt_outlined
                    : Icons.table_bar_outlined,
                size: 20,
                color: color,
              ),
            ],
          ),
          if (table.reference != null && table.reference!.isNotEmpty)
            Text(
              table.reference!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
              ),
            ),
          const Spacer(),
          // Long localized states ("Pendiente de aprobación") scale down
          // rather than overflow the small grid cell.
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: StatusChip(label: label, color: color),
          ),
        ],
      ),
    );
  }
}
