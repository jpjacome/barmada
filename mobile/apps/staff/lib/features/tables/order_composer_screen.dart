import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../board/board_providers.dart';
import '../products/products_providers.dart';
import 'tables_providers.dart';

/// Manual order entry: staff takes the order for guests who don't scan.
/// Pick products (steppers, search), review with an optional note —
/// the same Revisar/Confirmar vocabulary guests see — and submit
/// through the same CreateOrder path as the QR flow.
class OrderComposerScreen extends ConsumerStatefulWidget {
  const OrderComposerScreen({
    super.key,
    required this.tableId,
    required this.tableNumber,
  });

  final int tableId;
  final int tableNumber;

  @override
  ConsumerState<OrderComposerScreen> createState() =>
      _OrderComposerScreenState();
}

class _OrderComposerScreenState extends ConsumerState<OrderComposerScreen> {
  final _search = TextEditingController();
  final _note = TextEditingController();
  final _cart = <int, int>{}; // product id -> quantity
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _search.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _search.dispose();
    _note.dispose();
    super.dispose();
  }

  void _add(ProductInfo product) {
    if (!product.isAvailable) return;
    setState(() => _cart[product.id] = (_cart[product.id] ?? 0) + 1);
  }

  void _remove(ProductInfo product) {
    final qty = _cart[product.id] ?? 0;
    setState(() {
      if (qty <= 1) {
        _cart.remove(product.id);
      } else {
        _cart[product.id] = qty - 1;
      }
    });
  }

  int get _itemCount => _cart.values.fold(0, (sum, qty) => sum + qty);

  double _total(List<ProductInfo> products) {
    var total = 0.0;
    for (final product in products) {
      total += product.price * (_cart[product.id] ?? 0);
    }
    return total;
  }

  Future<void> _review(List<ProductInfo> products) async {
    final selected = [
      for (final product in products)
        if ((_cart[product.id] ?? 0) > 0) product,
    ];
    if (selected.isEmpty) return;

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _ReviewSheet(
        tableNumber: widget.tableNumber,
        selected: selected,
        quantities: Map.of(_cart),
        total: _total(products),
        noteController: _note,
      ),
    );
    if (confirmed != true || !mounted) return;
    await _submit();
  }

  Future<void> _submit() async {
    final client = ref.read(apiClientProvider);
    if (client == null || _busy) return;
    setState(() => _busy = true);
    final l10n = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await client.createOrder(
        tableId: widget.tableId,
        products: Map.of(_cart),
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
      );
      // The new order must show up everywhere at once.
      ref.invalidate(tableSessionProvider(widget.tableId));
      ref.invalidate(tablesProvider);
      ref.invalidate(boardProvider);
      messenger.showSnackBar(SnackBar(
        content: Text(l10n.orderPlacedSnack(widget.tableNumber)),
      ));
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) {
        messenger.showSnackBar(SnackBar(content: Text(l10n.errorMessage(e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final products = ref.watch(productsProvider);
    final snapshot = products.valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.newOrderSheetTitle(widget.tableNumber)),
      ),
      body: switch ((snapshot, products)) {
        (null, AsyncError(:final error)) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                icon: Icons.wifi_off_outlined,
                title: l10n.boardErrorTitle,
                subtitle: l10n.errorMessage(error),
              ),
            ),
          ),
        (null, _) => const Center(child: CircularProgressIndicator()),
        (final List<ProductInfo> data, _) when data.isEmpty => EmptyState(
            icon: Icons.local_bar_outlined,
            title: l10n.noProductsYet,
            subtitle: l10n.noProductsSubtitle,
          ),
        (final List<ProductInfo> data, _) => _ComposerList(
            products: data,
            quantities: _cart,
            query: _search.text,
            searchController: _search,
            busy: _busy,
            onAdd: _add,
            onRemove: _remove,
          ),
      },
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l10n.itemsCount(_itemCount),
                      style: theme.textTheme.bodySmall?.copyWith(
                        color:
                            theme.colorScheme.onSurface.withValues(alpha: 0.6),
                      ),
                    ),
                    Text(
                      _money(ref, snapshot == null ? 0 : _total(snapshot)),
                      style: theme.textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
              FilledButton.icon(
                onPressed: _busy || _itemCount == 0 || snapshot == null
                    ? null
                    : () => _review(snapshot),
                style: FilledButton.styleFrom(
                  minimumSize: const Size(0, 48),
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                ),
                icon: const Icon(Icons.receipt_long_outlined, size: 18),
                label: Text(l10n.reviewOrder),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Venue currency symbol + amount; the symbol comes from settings.
String _money(WidgetRef ref, double amount) {
  final session = ref.read(sessionControllerProvider).value;
  final symbol = switch (session) {
    Authed(:final user) => user.venue?.currencySymbol ?? r'$',
    _ => r'$',
  };
  return '$symbol${amount.toStringAsFixed(2)}';
}

class _ComposerList extends ConsumerWidget {
  const _ComposerList({
    required this.products,
    required this.quantities,
    required this.query,
    required this.searchController,
    required this.busy,
    required this.onAdd,
    required this.onRemove,
  });

  final List<ProductInfo> products;
  final Map<int, int> quantities;
  final String query;
  final TextEditingController searchController;
  final bool busy;
  final void Function(ProductInfo product) onAdd;
  final void Function(ProductInfo product) onRemove;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    final needle = query.trim().toLowerCase();
    final visible = needle.isEmpty
        ? products
        : products
            .where((p) =>
                p.name.toLowerCase().contains(needle) ||
                (p.categoryName ?? '').toLowerCase().contains(needle))
            .toList();

    final groups = <String, List<ProductInfo>>{};
    for (final product in visible) {
      groups
          .putIfAbsent(product.categoryName ?? l10n.categoryOthers, () => [])
          .add(product);
    }
    final names = groups.keys.toList()
      ..sort((a, b) {
        if (a == l10n.categoryOthers) return 1;
        if (b == l10n.categoryOthers) return -1;
        return a.toLowerCase().compareTo(b.toLowerCase());
      });

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        TextField(
          controller: searchController,
          decoration: InputDecoration(
            hintText: l10n.searchProducts,
            prefixIcon: const Icon(Icons.search, size: 20),
            suffixIcon: query.isEmpty
                ? null
                : IconButton(
                    icon: const Icon(Icons.clear, size: 18),
                    onPressed: searchController.clear,
                  ),
            isDense: true,
          ),
        ),
        const SizedBox(height: 8),
        if (visible.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 48),
            child: EmptyState(
              icon: Icons.search_off_outlined,
              title: l10n.noSearchMatches,
            ),
          )
        else
          for (final name in names) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
              child: Text(
                name,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: theme.colorScheme.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            for (final product in groups[name]!) ...[
              _ComposerRow(
                product: product,
                quantity: quantities[product.id] ?? 0,
                busy: busy,
                onAdd: () => onAdd(product),
                onRemove: () => onRemove(product),
              ),
              const SizedBox(height: 8),
            ],
          ],
      ],
    );
  }
}

class _ComposerRow extends ConsumerWidget {
  const _ComposerRow({
    required this.product,
    required this.quantity,
    required this.busy,
    required this.onAdd,
    required this.onRemove,
  });

  final ProductInfo product;
  final int quantity;
  final bool busy;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.55);
    final soldOut = !product.isAvailable;
    final selected = quantity > 0;

    return BarmadaCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      borderColor: soldOut
          ? theme.colorScheme.onSurface.withValues(alpha: 0.25)
          : selected
              ? status.paid.withValues(alpha: 0.85)
              : null,
      borderWidth: selected ? 1.5 : 1,
      onTap: busy || soldOut ? null : onAdd,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    color: soldOut ? dimmed : null,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Text(
                      _money(ref, product.price),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: soldOut ? dimmed : null,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                    ),
                    if (soldOut) ...[
                      const SizedBox(width: 8),
                      StatusChip(label: l10n.soldOut, color: status.pending),
                    ],
                  ],
                ),
              ],
            ),
          ),
          if (soldOut)
            const SizedBox.shrink()
          else if (!selected)
            IconButton(
              onPressed: busy ? null : onAdd,
              icon: const Icon(Icons.add_circle_outline),
              color: theme.colorScheme.primary,
            )
          else
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  onPressed: busy ? null : onRemove,
                  icon: const Icon(Icons.remove_circle_outline),
                  color: theme.colorScheme.primary,
                ),
                SizedBox(
                  width: 24,
                  child: Text(
                    '$quantity',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                IconButton(
                  onPressed: busy ? null : onAdd,
                  icon: const Icon(Icons.add_circle_outline),
                  color: theme.colorScheme.primary,
                ),
              ],
            ),
        ],
      ),
    );
  }
}

/// The pre-submit recap — the staff twin of the guest "Review order"
/// step, with the optional kitchen/bar note.
class _ReviewSheet extends ConsumerWidget {
  const _ReviewSheet({
    required this.tableNumber,
    required this.selected,
    required this.quantities,
    required this.total,
    required this.noteController,
  });

  final int tableNumber;
  final List<ProductInfo> selected;
  final Map<int, int> quantities;
  final double total;
  final TextEditingController noteController;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.6);

    return Padding(
      // Keep the sheet above the keyboard while typing the note.
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.newOrderSheetTitle(tableNumber),
                style: theme.textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 12),
              Flexible(
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    for (final product in selected)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${quantities[product.id]} × ${product.name}',
                                style: theme.textTheme.bodyLarge,
                              ),
                            ),
                            Text(
                              _money(
                                  ref, product.price * quantities[product.id]!),
                              style: theme.textTheme.bodyLarge?.copyWith(
                                fontFeatures: const [
                                  FontFeature.tabularFigures()
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
              const Divider(height: 20),
              Row(
                children: [
                  Text(l10n.totalLabel,
                      style:
                          theme.textTheme.bodyMedium?.copyWith(color: dimmed)),
                  const Spacer(),
                  Text(
                    _money(ref, total),
                    style: theme.textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: noteController,
                maxLength: 280,
                maxLines: 2,
                minLines: 1,
                decoration: InputDecoration(
                  labelText: l10n.orderNoteHint,
                  counterText: '',
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => Navigator.of(context).pop(true),
                style: FilledButton.styleFrom(minimumSize: const Size(0, 50)),
                icon: const Icon(Icons.check, size: 18),
                label: Text(l10n.confirmOrder),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(l10n.cancel),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
