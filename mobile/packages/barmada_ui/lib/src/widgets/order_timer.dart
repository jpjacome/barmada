import 'dart:async';

import 'package:flutter/material.dart';

import '../theme.dart';

/// The board chronometer: celadon while healthy, raspberry and pulsing
/// once an order crosses the overdue threshold (5 minutes, like the web).
class OrderTimer extends StatefulWidget {
  const OrderTimer({
    super.key,
    required this.since,
    this.overdueAfter = const Duration(minutes: 5),
    this.style,
  });

  final DateTime since;
  final Duration overdueAfter;
  final TextStyle? style;

  @override
  State<OrderTimer> createState() => _OrderTimerState();
}

class _OrderTimerState extends State<OrderTimer>
    with SingleTickerProviderStateMixin {
  Timer? _ticker;
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 750),
      lowerBound: 0.45,
      upperBound: 1,
    );
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _pulse.dispose();
    super.dispose();
  }

  String _format(Duration elapsed) {
    final minutes = elapsed.inMinutes.toString().padLeft(2, '0');
    final seconds = (elapsed.inSeconds % 60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  @override
  Widget build(BuildContext context) {
    final status = Theme.of(context).statusColors;
    final elapsed = DateTime.now().difference(widget.since.toLocal());
    final overdue = elapsed >= widget.overdueAfter;

    if (overdue && !_pulse.isAnimating) {
      _pulse.repeat(reverse: true);
    } else if (!overdue && _pulse.isAnimating) {
      _pulse.stop();
      _pulse.value = 1;
    }

    final text = Text(
      _format(elapsed.isNegative ? Duration.zero : elapsed),
      style: (widget.style ?? const TextStyle(fontSize: 18)).copyWith(
        color: overdue ? status.pending : status.paid,
        fontWeight: FontWeight.w700,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );

    return overdue ? FadeTransition(opacity: _pulse, child: text) : text;
  }
}
