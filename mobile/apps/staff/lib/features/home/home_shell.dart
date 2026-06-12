import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';
import '../board/board_screen.dart';
import '../products/products_screen.dart';
import '../tables/tables_screen.dart';
import 'more_screen.dart';
import 'placeholder_screen.dart';

/// The 5-tab shell from the mockups: Board, Tables, Products,
/// Analytics, More. Board is home.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    // Built per-frame so labels follow the locale; the IndexedStack keeps
    // each tab's State alive across rebuilds (children match by type+slot).
    final pages = <Widget>[
      const BoardScreen(),
      const TablesScreen(),
      const ProductsScreen(),
      PlaceholderScreen(
        title: l10n.tabAnalytics,
        icon: Icons.query_stats_outlined,
        message: l10n.analyticsPlaceholder,
      ),
      const MoreScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.grid_view_outlined),
            selectedIcon: const Icon(Icons.grid_view_rounded),
            label: l10n.tabBoard,
          ),
          NavigationDestination(
            icon: const Icon(Icons.table_bar_outlined),
            selectedIcon: const Icon(Icons.table_bar),
            label: l10n.tabTables,
          ),
          NavigationDestination(
            icon: const Icon(Icons.local_bar_outlined),
            selectedIcon: const Icon(Icons.local_bar),
            label: l10n.tabProducts,
          ),
          NavigationDestination(
            icon: const Icon(Icons.query_stats_outlined),
            selectedIcon: const Icon(Icons.query_stats),
            label: l10n.tabAnalytics,
          ),
          NavigationDestination(
            icon: const Icon(Icons.more_horiz_outlined),
            selectedIcon: const Icon(Icons.more_horiz),
            label: l10n.tabMore,
          ),
        ],
      ),
    );
  }
}
