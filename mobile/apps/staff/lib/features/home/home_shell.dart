import 'package:flutter/material.dart';

import '../board/board_screen.dart';
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

  static const _pages = <Widget>[
    BoardScreen(),
    PlaceholderScreen(
      title: 'Tables',
      icon: Icons.table_bar_outlined,
      message: 'Table grid with sessions, item payment ticking and bills — '
          'next chunk of Phase 1.',
    ),
    PlaceholderScreen(
      title: 'Products',
      icon: Icons.local_bar_outlined,
      message: 'Catalog with one-tap 86 availability — next chunk of Phase 1.',
    ),
    PlaceholderScreen(
      title: 'Analytics',
      icon: Icons.query_stats_outlined,
      message: 'Business-day sales, top products and service ops — '
          'next chunk of Phase 1.',
    ),
    MoreScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.grid_view_outlined),
            selectedIcon: Icon(Icons.grid_view_rounded),
            label: 'Board',
          ),
          NavigationDestination(
            icon: Icon(Icons.table_bar_outlined),
            selectedIcon: Icon(Icons.table_bar),
            label: 'Tables',
          ),
          NavigationDestination(
            icon: Icon(Icons.local_bar_outlined),
            selectedIcon: Icon(Icons.local_bar),
            label: 'Products',
          ),
          NavigationDestination(
            icon: Icon(Icons.query_stats_outlined),
            selectedIcon: Icon(Icons.query_stats),
            label: 'Analytics',
          ),
          NavigationDestination(
            icon: Icon(Icons.more_horiz_outlined),
            selectedIcon: Icon(Icons.more_horiz),
            label: 'More',
          ),
        ],
      ),
    );
  }
}
