import 'dart:async';

import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'board_providers.dart';

/// The live orders board — the staff app's home screen.
///
/// Polls `/board` every 5 seconds (the web cadence); push wake-ups make
/// this instant once FCM is configured. Shows the attention strip
/// (approvals + service requests), then pending order cards with the
/// chronometer and one-tap actions.
class BoardScreen extends ConsumerStatefulWidget {
  const BoardScreen({super.key});

  @override
  ConsumerState<BoardScreen> createState() => _BoardScreenState();
}

class _BoardScreenState extends ConsumerState<BoardScreen> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) ref.invalidate(boardProvider);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _run(Future<void> Function(BarmadaClient client) action) async {
    final client = ref.read(apiClientProvider);
    if (client == null) return;
    try {
      await action(client);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      ref.invalidate(boardProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionControllerProvider).value;
    final venueName = switch (session) {
      Authed(:final user, :final server) =>
        user.venue?.businessName ?? user.businessName ?? server.name,
      _ => 'Barmada',
    };

    final board = ref.watch(boardProvider);
    final snapshot = board.valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(venueName),
        actions: [
          if (board.isLoading && snapshot != null)
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
        onRefresh: () async => ref.invalidate(boardProvider),
        child: switch ((snapshot, board)) {
          (null, AsyncError(:final error)) => _ErrorView(error: error),
          (null, _) => const Center(child: CircularProgressIndicator()),
          (final BoardSnapshot data, _) => _BoardList(
              snapshot: data,
              onApprove: (request) => _run((c) async {
                if (request.isFirstGuest) {
                  await c.approveTable(request.tableId);
                } else {
                  await c.approveRequest(request.id);
                }
              }),
              onServiceDone: (request) =>
                  _run((c) => c.resolveServiceRequest(request.id)),
              onDeliver: (order) => _run((c) async {
                await c.deliverOrder(order.id);
              }),
            ),
        },
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.error});

  final Object error;

  @override
  Widget build(BuildContext context) {
    final message =
        error is ApiException ? (error as ApiException).message : '$error';
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 120),
        EmptyState(
          icon: Icons.wifi_off_outlined,
          title: 'Cannot reach the server',
          subtitle: '$message\nPull down to retry.',
        ),
      ],
    );
  }
}

class _BoardList extends StatelessWidget {
  const _BoardList({
    required this.snapshot,
    required this.onApprove,
    required this.onServiceDone,
    required this.onDeliver,
  });

  final BoardSnapshot snapshot;
  final void Function(ApprovalRequestInfo request) onApprove;
  final void Function(ServiceRequestInfo request) onServiceDone;
  final void Function(OrderInfo order) onDeliver;

  @override
  Widget build(BuildContext context) {
    final status = Theme.of(context).statusColors;

    if (snapshot.isQuiet) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 120),
          EmptyState(
            icon: Icons.nightlife_outlined,
            title: 'All quiet',
            subtitle:
                'No pending orders, approvals or service requests right now.',
          ),
        ],
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        if (snapshot.approvalRequests.isNotEmpty ||
            snapshot.serviceRequests.isNotEmpty) ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final request in snapshot.approvalRequests)
                AlertChip(
                  icon: Icons.person_add_alt_outlined,
                  color: status.pending,
                  label: request.isFirstGuest
                      ? 'Approve table ${request.tableNumber ?? request.tableId}'
                      : 'New guest · table ${request.tableNumber ?? request.tableId}',
                  onTap: () => onApprove(request),
                ),
              for (final request in snapshot.serviceRequests)
                AlertChip(
                  icon: request.isBill
                      ? Icons.receipt_long_outlined
                      : Icons.notifications_active_outlined,
                  color: status.info,
                  label:
                      '${request.isBill ? 'Bill' : 'Waiter'} · table ${request.tableNumber ?? '?'}',
                  onTap: () => onServiceDone(request),
                ),
            ],
          ),
          const SizedBox(height: 16),
        ],
        for (final order in snapshot.pendingOrders) ...[
          _OrderCard(order: order, onDeliver: () => onDeliver(order)),
          const SizedBox(height: 12),
        ],
        if (snapshot.pendingOrders.isEmpty)
          const Padding(
            padding: EdgeInsets.only(top: 32),
            child: EmptyState(
              icon: Icons.check_circle_outline,
              title: 'No pending orders',
            ),
          ),
      ],
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, required this.onDeliver});

  final OrderInfo order;
  final VoidCallback onDeliver;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.55);
    final overdue = order.createdAt != null &&
        DateTime.now().difference(order.createdAt!.toLocal()) >=
            const Duration(minutes: 5);

    return BarmadaCard(
      borderColor: overdue ? status.pending : null,
      borderWidth: overdue ? 2 : 1,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                'Table ${order.tableNumber ?? order.tableId}',
                style: theme.textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(width: 8),
              Text('#${order.id}', style: TextStyle(color: dimmed)),
              const Spacer(),
              if (order.createdAt != null)
                OrderTimer(since: order.createdAt!),
            ],
          ),
          const SizedBox(height: 10),
          for (final entry in order.groupedItems.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text('${entry.value} × ${entry.key}',
                  style: theme.textTheme.bodyLarge),
            ),
          if (order.note != null && order.note!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              '“${order.note}”',
              style: TextStyle(
                color: status.info,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Text(
                order.total.toStringAsFixed(2),
                style: theme.textTheme.headlineSmall,
              ),
              const Spacer(),
              FilledButton.icon(
                onPressed: onDeliver,
                style: FilledButton.styleFrom(
                  minimumSize: const Size(0, 44),
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                ),
                icon: const Icon(Icons.check, size: 18),
                label: const Text('Delivered'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
