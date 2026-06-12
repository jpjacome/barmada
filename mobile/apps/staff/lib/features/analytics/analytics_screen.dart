import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import 'analytics_providers.dart';

/// Business-day analytics: the sales headline, orders by hour, top
/// products and service ops — the same VenueAnalytics read models as the
/// web dashboard, bucketed by the venue's timezone and day cutoff.
///
/// Editor-only by server policy: staff accounts get a friendly note
/// instead of a doomed 403.
class AnalyticsScreen extends ConsumerStatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  ConsumerState<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends ConsumerState<AnalyticsScreen> {
  String _range = 'today';

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider).value;
    final isEditor = switch (session) {
      Authed(:final user) => user.isEditor,
      _ => false,
    };

    if (!isEditor) {
      return Scaffold(
        appBar: AppBar(title: Text(l10n.tabAnalytics)),
        body: EmptyState(
          icon: Icons.lock_outline,
          title: l10n.analyticsOwnerOnlyTitle,
          subtitle: l10n.analyticsOwnerOnlySubtitle,
        ),
      );
    }

    final analytics = ref.watch(analyticsProvider(_range));
    final snapshot = analytics.valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.tabAnalytics),
        actions: [
          if (analytics.isLoading && snapshot != null)
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
        onRefresh: () async => ref.invalidate(analyticsProvider(_range)),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Center(
              child: SegmentedButton<String>(
                segments: [
                  ButtonSegment(value: 'today', label: Text(l10n.rangeToday)),
                  ButtonSegment(value: '7days', label: Text(l10n.range7days)),
                  ButtonSegment(value: '30days', label: Text(l10n.range30days)),
                ],
                selected: {_range},
                onSelectionChanged: (selection) =>
                    setState(() => _range = selection.first),
              ),
            ),
            const SizedBox(height: 16),
            ...switch ((snapshot, analytics)) {
              (null, AsyncError(:final error)) => [
                  const SizedBox(height: 80),
                  EmptyState(
                    icon: Icons.wifi_off_outlined,
                    title: l10n.boardErrorTitle,
                    subtitle:
                        '${l10n.errorMessage(error)}\n${l10n.boardErrorRetryHint}',
                  ),
                ],
              (null, _) => const [
                  SizedBox(height: 120),
                  Center(child: CircularProgressIndicator()),
                ],
              (final AnalyticsBundle data, _) => [
                  _SummaryCard(summary: data.summary),
                  const SizedBox(height: 12),
                  if (data.summary.hourDistribution.isNotEmpty) ...[
                    _HourChartCard(summary: data.summary),
                    const SizedBox(height: 12),
                  ],
                  if (data.products.topProducts.isNotEmpty) ...[
                    _TopProductsCard(
                      products: data.products,
                      symbol: data.summary.currencySymbol,
                    ),
                    const SizedBox(height: 12),
                  ],
                  _ServiceOpsCard(ops: data.serviceOps),
                ],
            },
          ],
        ),
      ),
    );
  }
}

String _fmt(String symbol, double amount) =>
    '$symbol${amount.toStringAsFixed(2)}';

/// "21:00" — peak hour and chart labels in the venue's local clock.
String _hourLabel(int hour) => '${hour.toString().padLeft(2, '0')}:00';

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});

  final AnalyticsSummary summary;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    return BarmadaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.totalSalesLabel,
              style: theme.textTheme.bodySmall?.copyWith(color: dimmed)),
          const SizedBox(height: 2),
          Text(
            _fmt(summary.currencySymbol, summary.totalSales),
            style: theme.textTheme.displaySmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: status.paid,
            ),
          ),
          if (summary.orderCount == 0) ...[
            const SizedBox(height: 8),
            Text(l10n.analyticsNoSales,
                style: theme.textTheme.bodyMedium?.copyWith(color: dimmed)),
          ] else ...[
            const SizedBox(height: 14),
            Row(
              children: [
                _Stat(label: l10n.ordersLabel, value: '${summary.orderCount}'),
                _Stat(
                  label: l10n.avgOrderLabel,
                  value:
                      _fmt(summary.currencySymbol, summary.averageOrderValue),
                ),
                _Stat(
                  label: l10n.peakHourLabel,
                  value: summary.peakHour == null
                      ? '—'
                      : _hourLabel(summary.peakHour!),
                ),
              ],
            ),
            if (summary.topProduct != null) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.star_outline, size: 16, color: status.info),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '${l10n.topProductLabel}: ${summary.topProduct}',
                      style:
                          theme.textTheme.bodyMedium?.copyWith(color: dimmed),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
            ),
          ),
          const SizedBox(height: 2),
          Text(value,
              style: theme.textTheme.titleLarge
                  ?.copyWith(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

/// Orders by local hour, 24 thin bars — plain Flutter, no chart library.
class _HourChartCard extends StatelessWidget {
  const _HourChartCard({required this.summary});

  final AnalyticsSummary summary;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final max =
        summary.hourDistribution.values.fold(0, (m, v) => v > m ? v : m);

    return BarmadaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.ordersByHourTitle,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          SizedBox(
            height: 72,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (var hour = 0; hour < 24; hour++) ...[
                  Expanded(
                    child: Container(
                      height: max == 0
                          ? 2
                          : 2 +
                              66 * (summary.hourDistribution[hour] ?? 0) / max,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(2),
                        color: hour == summary.peakHour
                            ? status.paid
                            : theme.colorScheme.primary.withValues(
                                alpha: (summary.hourDistribution[hour] ?? 0) > 0
                                    ? 0.65
                                    : 0.15,
                              ),
                      ),
                    ),
                  ),
                  if (hour < 23) const SizedBox(width: 2),
                ],
              ],
            ),
          ),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (final hour in const [0, 6, 12, 18, 23])
                Text(
                  _hourLabel(hour),
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 10,
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.5),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TopProductsCard extends StatelessWidget {
  const _TopProductsCard({required this.products, required this.symbol});

  final ProductStats products;
  final String symbol;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    return BarmadaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.topProductsTitle,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 10),
          for (final (index, stat) in products.topProducts.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  SizedBox(
                    width: 22,
                    child: Text(
                      '${index + 1}.',
                      style:
                          theme.textTheme.bodyMedium?.copyWith(color: dimmed),
                    ),
                  ),
                  Expanded(
                    child: Text(stat.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyLarge),
                  ),
                  Text('×${stat.quantity}',
                      style:
                          theme.textTheme.bodyMedium?.copyWith(color: dimmed)),
                  const SizedBox(width: 12),
                  Text(
                    _fmt(symbol, stat.revenue),
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                ],
              ),
            ),
          if (products.categorySales.isNotEmpty) ...[
            const Divider(height: 20),
            Text(l10n.byCategoryTitle,
                style: theme.textTheme.bodySmall?.copyWith(color: dimmed)),
            const SizedBox(height: 8),
            for (final stat in products.categorySales)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Expanded(
                        child:
                            Text(stat.name, style: theme.textTheme.bodyMedium)),
                    Text(
                      _fmt(symbol, stat.revenue),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _ServiceOpsCard extends StatelessWidget {
  const _ServiceOpsCard({required this.ops});

  final ServiceOpsStats ops;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    String minutes(double? value) =>
        value == null ? '—' : l10n.minutesShort(value.toStringAsFixed(0));

    return BarmadaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.serviceTitle,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          Row(
            children: [
              _Stat(label: l10n.sessionsLabel, value: '${ops.sessions}'),
              _Stat(
                label: l10n.avgSessionLabel,
                value: minutes(ops.avgSessionDurationMinutes),
              ),
              _Stat(
                label: l10n.mostUsedTableLabel,
                value: ops.mostUsedTable ?? '—',
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _Stat(label: l10n.qrScansLabel, value: '${ops.qrScans}'),
              _Stat(
                label: l10n.qrConversionLabel,
                value: ops.qrToOrderConversion == null
                    ? '—'
                    : '${ops.qrToOrderConversion!.toStringAsFixed(0)}%',
              ),
              _Stat(
                label: l10n.turnoverLabel,
                value: ops.tableTurnover?.toStringAsFixed(1) ?? '—',
              ),
            ],
          ),
          if (ops.staffOrderCounts.isNotEmpty) ...[
            const Divider(height: 20),
            Text(l10n.whoTookOrdersTitle,
                style: theme.textTheme.bodySmall?.copyWith(color: dimmed)),
            const SizedBox(height: 8),
            for (final row in ops.staffOrderCounts)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        // The server labels guest QR orders in English;
                        // localize that one marker, pass names through.
                        row.label == 'Guests (QR)'
                            ? l10n.guestsQrLabel
                            : row.label,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                    Text('×${row.orders}',
                        style: theme.textTheme.bodyMedium
                            ?.copyWith(color: dimmed)),
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }
}
