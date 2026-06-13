import 'dart:async';
import 'dart:io';

import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../board/board_providers.dart';
import 'bill_pdf.dart';
import 'order_composer_screen.dart';
import 'tables_providers.dart';

/// One table's live session: the running bill with the product's
/// signature item-by-item payment ticking, plus open / approve /
/// settle / close — the staff side of "no processor, cash and tabs".
class TableSessionScreen extends ConsumerStatefulWidget {
  const TableSessionScreen({
    super.key,
    required this.tableId,
    required this.tableNumber,
  });

  final int tableId;
  final int tableNumber;

  @override
  ConsumerState<TableSessionScreen> createState() => _TableSessionScreenState();
}

class _TableSessionScreenState extends ConsumerState<TableSessionScreen> {
  Timer? _poll;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) ref.invalidate(tableSessionProvider(widget.tableId));
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  void _refresh() {
    ref.invalidate(tableSessionProvider(widget.tableId));
    ref.invalidate(tablesProvider);
  }

  Future<void> _run(
    Future<void> Function(BarmadaClient client) action, {
    String? successMessage,
  }) async {
    final client = ref.read(apiClientProvider);
    if (client == null || _busy) return;
    setState(() => _busy = true);
    try {
      await action(client);
      if (mounted && successMessage != null) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(successMessage)));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(AppLocalizations.of(context).errorMessage(e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
      _refresh();
    }
  }

  Future<void> _shareBill(SessionBill bill) async {
    if (_busy) return;
    setState(() => _busy = true);
    final l10n = AppLocalizations.of(context);
    final session = ref.read(sessionControllerProvider).value;
    final venueName = switch (session) {
      Authed(:final user, :final server) =>
        user.venue?.businessName ?? user.businessName ?? server.name,
      _ => 'Barmada',
    };
    final symbol = switch (session) {
      Authed(:final user) => user.venue?.currencySymbol ?? r'$',
      _ => r'$',
    };
    final locale = Localizations.localeOf(context).languageCode;
    try {
      // Roboto for full Unicode currency coverage (the PDF built-in
      // fonts can't draw €).
      final baseFont = await rootBundle.load('assets/fonts/Roboto-Regular.ttf');
      final boldFont = await rootBundle.load('assets/fonts/Roboto-Bold.ttf');
      final bytes = await const BillPdfGenerator().generate(
        bill: bill,
        venueName: venueName,
        currencySymbol: symbol,
        locale: locale,
        baseFont: baseFont,
        boldFont: boldFont,
      );
      final dir = await getTemporaryDirectory();
      final filename = l10n.shareBillFilename('${widget.tableNumber}');
      final file = File('${dir.path}/$filename');
      await file.writeAsBytes(bytes, flush: true);

      if (!mounted) return;
      await Share.shareXFiles(
        [XFile(file.path, mimeType: 'application/pdf')],
        subject: l10n.shareBillSubject(venueName, '${widget.tableNumber}'),
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(l10n.shareBillError)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _close(SessionBill bill) async {
    final l10n = AppLocalizations.of(context);
    if (bill.left <= 0) {
      await _run(
        (c) => c.closeTable(widget.tableId),
        successMessage: l10n.tableClosedSnack(widget.tableNumber),
      );
      return;
    }

    // Money is on the table: make the choice explicit.
    final settle = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.closeTableTitle(widget.tableNumber)),
        content: Text(l10n.closeUnpaidWarning(_money(ref, bill.left))),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(l10n.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l10n.closeWithoutSettling),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(l10n.closeAndSettle),
          ),
        ],
      ),
    );
    if (settle == null || !mounted) return;

    await _run(
      (c) => c.closeTable(widget.tableId, settle: settle),
      successMessage: l10n.tableClosedSnack(widget.tableNumber),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final bill = ref.watch(tableSessionProvider(widget.tableId));
    final snapshot = bill.valueOrNull;
    final tableOpen = snapshot?.table?.status == 'open';

    return Scaffold(
      // Manual order entry — for guests who don't scan. Only on open
      // tables: orders need a session to land in.
      floatingActionButton: tableOpen
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => OrderComposerScreen(
                    tableId: widget.tableId,
                    tableNumber: widget.tableNumber,
                  ),
                ),
              ),
              icon: const Icon(Icons.add),
              label: Text(l10n.newOrderAction),
            )
          : null,
      appBar: AppBar(
        title: Text(l10n.orderTableTitle(widget.tableNumber)),
        actions: [
          // Share bill — shown when there is a session with at least one
          // non-cancelled order so there is something worth sharing.
          if (snapshot != null &&
              snapshot.hasOpenSession &&
              snapshot.orders.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.share_outlined),
              tooltip: l10n.shareBillAction,
              onPressed: _busy ? null : () => _shareBill(snapshot),
            ),
          if (bill.isLoading && snapshot != null)
            const Padding(
              padding: EdgeInsets.only(right: 8),
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
        onRefresh: () async => _refresh(),
        child: switch ((snapshot, bill)) {
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
          (final SessionBill data, _) => _SessionView(
              bill: data,
              busy: _busy,
              onOpen: () => _run(
                (c) => c.openTable(widget.tableId),
                successMessage: l10n.tableOpenedSnack(widget.tableNumber),
              ),
              onApprove: () => _run(
                (c) => c.approveTable(widget.tableId),
                successMessage: l10n.tableOpenedSnack(widget.tableNumber),
              ),
              onSettleAll: () => _run((c) => c.settleTable(widget.tableId)),
              onClose: () => _close(data),
              onToggleItem: (order, item) => _run(
                (c) => c.toggleItemPaid(
                  orderId: order.id,
                  productId: item.productId,
                  itemIndex: item.itemIndex,
                ),
              ),
              onServiceDone: (request) =>
                  _run((c) => c.resolveServiceRequest(request.id)),
            ),
        },
      ),
    );
  }
}

/// Venue currency symbol + amount ("€12.50"). The symbol is the venue's
/// setting from the server, never a locale guess.
String _money(WidgetRef ref, double amount) {
  final session = ref.read(sessionControllerProvider).value;
  final symbol = switch (session) {
    Authed(:final user) => user.venue?.currencySymbol ?? r'$',
    _ => r'$',
  };
  return '$symbol${amount.toStringAsFixed(2)}';
}

class _SessionView extends ConsumerWidget {
  const _SessionView({
    required this.bill,
    required this.busy,
    required this.onOpen,
    required this.onApprove,
    required this.onSettleAll,
    required this.onClose,
    required this.onToggleItem,
    required this.onServiceDone,
  });

  final SessionBill bill;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onApprove;
  final VoidCallback onSettleAll;
  final VoidCallback onClose;
  final void Function(OrderInfo order, OrderItemInfo item) onToggleItem;
  final void Function(ServiceRequestInfo request) onServiceDone;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    final tableStatus = bill.table?.status ?? 'closed';
    final (statusLabel, statusColor) = switch (tableStatus) {
      'open' => (l10n.statusOpen, status.paid),
      'pending_approval' => (l10n.statusPendingApproval, status.pending),
      _ => (l10n.statusClosed, dimmed),
    };

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      // Extra bottom room so the New-order FAB never covers the last
      // order's tick targets.
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
      children: [
        Row(
          children: [
            StatusChip(label: statusLabel, color: statusColor),
            const SizedBox(width: 12),
            if (bill.openedAt != null)
              Expanded(
                child: Text(
                  l10n.openedAtTime(
                    DateFormat.jm(Localizations.localeOf(context).toString())
                        .format(bill.openedAt!.toLocal()),
                  ),
                  style: theme.textTheme.bodySmall?.copyWith(color: dimmed),
                ),
              ),
          ],
        ),
        if (bill.serviceRequests.isNotEmpty) ...[
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final request in bill.serviceRequests)
                AlertChip(
                  icon: request.isBill
                      ? Icons.receipt_long_outlined
                      : Icons.notifications_active_outlined,
                  color: status.info,
                  label: request.isBill
                      ? l10n.billRequestedChip
                      : l10n.waiterCalledChip,
                  onTap: busy ? null : () => onServiceDone(request),
                ),
            ],
          ),
        ],
        const SizedBox(height: 12),
        BarmadaCard(
          child: Row(
            children: [
              _TotalColumn(
                label: l10n.totalLabel,
                value: _money(ref, bill.total),
              ),
              _TotalColumn(
                label: l10n.paidLabel,
                value: _money(ref, bill.paid),
                color: status.paid,
              ),
              _TotalColumn(
                label: l10n.remainingLabel,
                value: _money(ref, bill.left),
                color: bill.left > 0 ? status.pending : status.paid,
                emphasized: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        switch (tableStatus) {
          'open' => Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: busy || bill.left <= 0 ? null : onSettleAll,
                    icon: const Icon(Icons.done_all, size: 18),
                    label: Text(l10n.settleAllAction),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: busy ? null : onClose,
                    icon: const Icon(Icons.lock_outline, size: 18),
                    label: Text(l10n.closeTableAction),
                  ),
                ),
              ],
            ),
          'pending_approval' => FilledButton.icon(
              onPressed: busy ? null : onApprove,
              icon: const Icon(Icons.person_add_alt_outlined, size: 18),
              label: Text(l10n.approveTableAction),
            ),
          _ => FilledButton.icon(
              onPressed: busy ? null : onOpen,
              icon: const Icon(Icons.lock_open_outlined, size: 18),
              label: Text(l10n.openTableAction),
            ),
        },
        const SizedBox(height: 16),
        if (!bill.hasOpenSession)
          Padding(
            padding: const EdgeInsets.only(top: 32),
            child: EmptyState(
              icon: Icons.table_bar_outlined,
              title: l10n.tableClosedEmptyTitle,
              subtitle: l10n.tableClosedEmptySubtitle,
            ),
          )
        else if (bill.orders.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 32),
            child: EmptyState(
              icon: Icons.receipt_long_outlined,
              title: l10n.sessionNoOrders,
            ),
          )
        else
          for (final order in bill.orders) ...[
            _OrderBillCard(
              order: order,
              busy: busy,
              money: (amount) => _money(ref, amount),
              onToggleItem: (item) => onToggleItem(order, item),
            ),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

class _TotalColumn extends StatelessWidget {
  const _TotalColumn({
    required this.label,
    required this.value,
    this.color,
    this.emphasized = false,
  });

  final String label;
  final String value;
  final Color? color;
  final bool emphasized;

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
          const SizedBox(height: 4),
          Text(
            value,
            style: (emphasized
                    ? theme.textTheme.headlineSmall
                    : theme.textTheme.titleLarge)
                ?.copyWith(color: color, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

/// One order on the bill: header, note, then a tappable row per unit —
/// tap to tick it paid as cash lands on the table.
class _OrderBillCard extends StatelessWidget {
  const _OrderBillCard({
    required this.order,
    required this.busy,
    required this.money,
    required this.onToggleItem,
  });

  final OrderInfo order;
  final bool busy;
  final String Function(double amount) money;
  final void Function(OrderItemInfo item) onToggleItem;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.55);

    final (orderLabel, orderColor) = switch (order.status) {
      'delivered' => (l10n.orderStatusDelivered, status.paid),
      'cancelled' => (l10n.orderStatusCancelled, dimmed),
      _ => (l10n.orderStatusPending, status.pending),
    };

    return BarmadaCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('#${order.id}',
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(width: 10),
              StatusChip(label: orderLabel, color: orderColor),
              const Spacer(),
              Text(money(order.total), style: theme.textTheme.titleMedium),
            ],
          ),
          if (order.note != null && order.note!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              '“${order.note}”',
              style: TextStyle(
                color: status.info,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
          const SizedBox(height: 8),
          for (final item in order.items)
            InkWell(
              onTap: busy ? null : () => onToggleItem(item),
              borderRadius: BorderRadius.circular(8),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                child: Row(
                  children: [
                    Icon(
                      item.isPaid
                          ? Icons.check_circle
                          : Icons.radio_button_unchecked,
                      size: 22,
                      color: item.isPaid ? status.paid : dimmed,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        item.productName ?? '#${item.productId}',
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: item.isPaid ? dimmed : null,
                          decoration:
                              item.isPaid ? TextDecoration.lineThrough : null,
                          decorationColor: dimmed,
                        ),
                      ),
                    ),
                    Text(
                      money(item.price),
                      style: theme.textTheme.bodyLarge?.copyWith(
                        color: item.isPaid ? status.paid : null,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
