import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';

/// An honest placeholder: names what is coming so the shell reads as a
/// roadmap, not a dead end.
class PlaceholderScreen extends StatelessWidget {
  const PlaceholderScreen({
    super.key,
    required this.title,
    required this.icon,
    required this.message,
  });

  final String title;
  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: EmptyState(
        icon: icon,
        title: AppLocalizations.of(context).comingSoon,
        subtitle: message,
      ),
    );
  }
}
