import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_staff/features/board/board_alerts.dart';
import 'package:flutter_test/flutter_test.dart';

BoardSnapshot _board({
  List<int> orders = const [],
  List<int> approvals = const [],
  List<int> service = const [],
}) =>
    BoardSnapshot(
      pendingOrders: [
        for (final id in orders)
          OrderInfo(
            id: id,
            tableId: 1,
            status: 'pending',
            total: 1,
            paid: 0,
            left: 1,
            items: const [],
          ),
      ],
      approvalRequests: [
        for (final id in approvals)
          ApprovalRequestInfo(id: id, tableId: 1, scope: 'first_guest'),
      ],
      serviceRequests: [
        for (final id in service) ServiceRequestInfo(id: id, type: 'bill'),
      ],
    );

void main() {
  test('the first snapshot primes silently, even when busy', () {
    final detector = BoardAlertDetector();
    expect(detector.register(_board(orders: [1, 2], approvals: [9])), isFalse);
  });

  test('a new order rings; the same board again does not', () {
    final detector = BoardAlertDetector();
    detector.register(_board(orders: [1]));

    expect(detector.register(_board(orders: [1, 2])), isTrue);
    expect(detector.register(_board(orders: [1, 2])), isFalse);
  });

  test('new approval and service requests ring too', () {
    final detector = BoardAlertDetector();
    detector.register(_board());

    expect(detector.register(_board(approvals: [5])), isTrue);
    expect(detector.register(_board(approvals: [5], service: [7])), isTrue);
  });

  test('items leaving the board never ring, new ones after do', () {
    final detector = BoardAlertDetector();
    detector.register(_board(orders: [1, 2]));

    // Both delivered: quieter board, no chime.
    expect(detector.register(_board()), isFalse);
    // A brand-new order later rings.
    expect(detector.register(_board(orders: [3])), isTrue);
  });
}
