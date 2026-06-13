import 'package:audioplayers/audioplayers.dart';
import 'package:barmada_api/barmada_api.dart';

/// Decides when a board poll deserves a chime: any order, approval
/// request or service request id not seen before. The first snapshot
/// after (re)start primes silently — reopening the app over a busy
/// board must not ring.
///
/// Pure logic, no I/O — the sound itself lives in [AlertSounds].
class BoardAlertDetector {
  Set<int>? _orders;
  Set<int>? _approvals;
  Set<int>? _service;

  /// Registers [snapshot]; returns true when it brings something new.
  bool register(BoardSnapshot snapshot) {
    final orders = snapshot.pendingOrders.map((o) => o.id).toSet();
    final approvals = snapshot.approvalRequests.map((r) => r.id).toSet();
    final service = snapshot.serviceRequests.map((r) => r.id).toSet();

    final primed = _orders != null;
    final hasNew = primed &&
        (orders.difference(_orders!).isNotEmpty ||
            approvals.difference(_approvals!).isNotEmpty ||
            service.difference(_service!).isNotEmpty);

    _orders = orders;
    _approvals = approvals;
    _service = service;
    return hasNew;
  }
}

/// Plays the bar chime. Failures (headless tests, muted platforms,
/// missing plugin) are swallowed — alerting must never crash service.
class AlertSounds {
  AlertSounds._();

  static final _player = AudioPlayer();

  static Future<void> chime() async {
    try {
      await _player.stop();
      await _player.play(AssetSource('sounds/new_order.wav'));
    } catch (_) {
      // No audio available — the visual board still tells the story.
    }
  }
}
