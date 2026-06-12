import 'package:flutter/material.dart';

/// The signature Barmada surface: an OUTLINED card — thin sage border on
/// the dark surface with a barely-there sage sheen — not a floating
/// shadowed one. Mirrors the web's `.content-card`.
class BarmadaCard extends StatelessWidget {
  const BarmadaCard({
    super.key,
    required this.child,
    this.borderColor,
    this.borderWidth = 1,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
  });

  final Widget child;
  final Color? borderColor;
  final double borderWidth;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final border = borderColor ?? scheme.primary.withValues(alpha: 0.75);

    final card = Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border, width: borderWidth),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            scheme.primary.withValues(alpha: 0.05),
            Colors.transparent,
          ],
        ),
      ),
      child: Padding(padding: padding, child: child),
    );

    if (onTap == null) return card;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: card,
    );
  }
}
