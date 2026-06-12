import 'dart:async';

import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../board/board_providers.dart';
import 'products_providers.dart';

/// The catalog with one-tap 86: find the item, flip the switch, the
/// guest menu updates on the next poll. Grouped by category, searchable
/// because rushes don't wait for scrolling.
class ProductsScreen extends ConsumerStatefulWidget {
  const ProductsScreen({super.key});

  @override
  ConsumerState<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends ConsumerState<ProductsScreen> {
  Timer? _poll;
  final _search = TextEditingController();
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) ref.invalidate(productsProvider);
    });
    _search.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _poll?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _toggle(ProductInfo product) async {
    final client = ref.read(apiClientProvider);
    if (client == null || _busy) return;
    setState(() => _busy = true);
    final l10n = AppLocalizations.of(context);
    try {
      final updated = await client.toggleProductAvailability(product.id);
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            content: Text(updated.isAvailable
                ? l10n.productBackOnSale(updated.name)
                : l10n.productMarkedSoldOut(updated.name)),
            duration: const Duration(seconds: 2),
          ));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(l10n.errorMessage(e))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
      ref.invalidate(productsProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final products = ref.watch(productsProvider);
    final snapshot = products.valueOrNull;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.tabProducts),
        actions: [
          if (products.isLoading && snapshot != null)
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
        onRefresh: () async => ref.invalidate(productsProvider),
        child: switch ((snapshot, products)) {
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
          (final List<ProductInfo> data, _) when data.isEmpty => ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                const SizedBox(height: 120),
                EmptyState(
                  icon: Icons.local_bar_outlined,
                  title: l10n.noProductsYet,
                  subtitle: l10n.noProductsSubtitle,
                ),
              ],
            ),
          (final List<ProductInfo> data, _) => _CatalogList(
              products: data,
              query: _search.text,
              searchField: TextField(
                controller: _search,
                decoration: InputDecoration(
                  hintText: l10n.searchProducts,
                  prefixIcon: const Icon(Icons.search, size: 20),
                  suffixIcon: _search.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () => _search.clear(),
                        ),
                  isDense: true,
                ),
              ),
              busy: _busy,
              onToggle: _toggle,
            ),
        },
      ),
    );
  }
}

class _CatalogList extends ConsumerWidget {
  const _CatalogList({
    required this.products,
    required this.query,
    required this.searchField,
    required this.busy,
    required this.onToggle,
  });

  final List<ProductInfo> products;
  final String query;
  final Widget searchField;
  final bool busy;
  final void Function(ProductInfo product) onToggle;

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

    // Group by category; uncategorized products land in "Others" last —
    // the same bucket the guest menu uses.
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
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        searchField,
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
              _ProductRow(product: product, busy: busy, onToggle: onToggle),
              const SizedBox(height: 8),
            ],
          ],
      ],
    );
  }
}

class _ProductRow extends ConsumerWidget {
  const _ProductRow({
    required this.product,
    required this.busy,
    required this.onToggle,
  });

  final ProductInfo product;
  final bool busy;
  final void Function(ProductInfo product) onToggle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final status = theme.statusColors;
    final dimmed = theme.colorScheme.onSurface.withValues(alpha: 0.55);
    final session = ref.watch(sessionControllerProvider).value;
    final (symbol, serverUrl) = switch (session) {
      Authed(:final user, :final server) => (
          user.venue?.currencySymbol ?? r'$',
          server.url
        ),
      _ => (r'$', null),
    };

    final soldOut = !product.isAvailable;

    return BarmadaCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      borderColor:
          soldOut ? theme.colorScheme.onSurface.withValues(alpha: 0.25) : null,
      onTap: busy ? null : () => onToggle(product),
      child: Row(
        children: [
          _Thumb(product: product, serverUrl: serverUrl, dimmed: soldOut),
          const SizedBox(width: 12),
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
                      '$symbol${product.price.toStringAsFixed(2)}',
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
          // The 86 switch: ON = on sale. The whole row is tappable too.
          Switch(
            value: product.isAvailable,
            activeTrackColor: status.paid.withValues(alpha: 0.6),
            onChanged: busy ? null : (_) => onToggle(product),
          ),
        ],
      ),
    );
  }
}

class _Thumb extends StatelessWidget {
  const _Thumb({
    required this.product,
    required this.serverUrl,
    required this.dimmed,
  });

  final ProductInfo product;
  final String? serverUrl;
  final bool dimmed;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fallback = Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        color: scheme.primary.withValues(alpha: dimmed ? 0.08 : 0.15),
      ),
      child: Icon(
        Icons.local_bar_outlined,
        size: 22,
        color: scheme.primary.withValues(alpha: dimmed ? 0.4 : 0.8),
      ),
    );

    final photo = product.photo;
    if (photo == null || photo.isEmpty || serverUrl == null) return fallback;

    // Photos live on the server's public disk: {server}/storage/{path}.
    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: Opacity(
        opacity: dimmed ? 0.45 : 1,
        child: Image.network(
          '$serverUrl/storage/$photo',
          width: 44,
          height: 44,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => fallback,
        ),
      ),
    );
  }
}
