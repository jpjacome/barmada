import 'package:barmada_api/barmada_api.dart';
import 'package:test/test.dart';

/// Fixtures mirror the real controller payloads in the server repo
/// (see openapi/api-v1.json and the Api\V1 controllers).
void main() {
  test('meta parses and reports capabilities', () {
    final meta = ServerMeta.fromJson({
      'product': 'barmada',
      'name': 'Barmada',
      'api': {'version': 1, 'auth': 'sanctum-bearer'},
      'features': ['board', 'item-payments', 'push'],
    });

    expect(meta.isBarmada, isTrue);
    expect(meta.apiVersion, 1);
    expect(meta.supports('push'), isTrue);
    expect(meta.supports('quantum'), isFalse);
  });

  test('auth result parses user, abilities and venue settings', () {
    final auth = AuthResult.fromJson({
      'token': '1|abcdef',
      'token_type': 'Bearer',
      'abilities': ['role:editor', 'role:staff'],
      'user': {
        'id': 7,
        'username': 'cantina',
        'name': 'La Cantina',
        'business_name': 'La Cantina del Puerto',
        'email': 'owner@cantina.ec',
        'is_admin': false,
        'is_editor': true,
        'is_staff': false,
        'editor_id': 7,
        'venue': {
          'currency_symbol': r'$',
          'guest_locale': 'es',
          'timezone': 'America/Guayaquil',
          'day_cutoff_hour': 4,
        },
      },
    });

    expect(auth.token, '1|abcdef');
    expect(auth.abilities, contains('role:editor'));
    expect(auth.user.isEditor, isTrue);
    expect(auth.user.roleLabel, 'Owner');
    expect(auth.user.venue?.timezone, 'America/Guayaquil');
    expect(auth.user.venue?.dayCutoffHour, 4);
  });

  test('order parses OrderResource shape with grouped items', () {
    final order = OrderInfo.fromJson({
      'id': 142,
      'table_id': 4,
      'table_number': 4,
      'table_session_id': 9,
      'status': 'pending',
      'note': 'sin hielo',
      'created_by': null,
      'created_at': '2026-06-12T21:47:00+00:00',
      'total': 8.0,
      'paid': 2.5,
      'left': 5.5,
      'items': [
        {
          'id': 1,
          'product_id': 11,
          'product_name': 'Pilsener',
          'item_index': 0,
          'price': 2.5,
          'is_paid': true,
        },
        {
          'id': 2,
          'product_id': 11,
          'product_name': 'Pilsener',
          'item_index': 1,
          'price': 2.5,
          'is_paid': false,
        },
        {
          'id': 3,
          'product_id': 12,
          'product_name': 'Nachos Grande',
          'item_index': 2,
          'price': 3.0,
          'is_paid': false,
        },
      ],
    });

    expect(order.isPending, isTrue);
    expect(order.left, 5.5);
    expect(order.groupedItems, {'Pilsener': 2, 'Nachos Grande': 1});
    expect(order.createdAt, isNotNull);
  });

  test('session bill parses TableBill shape with decimal strings', () {
    final bill = SessionBill.fromJson({
      'table': {'id': 4, 'table_number': 4, 'status': 'open'},
      'session': {'id': 9, 'opened_at': '2026-06-12T20:30:00+00:00'},
      'orders': [
        {
          'id': 142,
          'created_at': '2026-06-12T21:47:00+00:00',
          'status': 'pending',
          'note': null,
          'total_amount': '8.00',
          'amount_paid': '2.50',
          'amount_left': '5.50',
          'items': [
            {
              'id': 1,
              'product_id': 11,
              'item_index': 0,
              'price': '2.50',
              'is_paid': true,
              'product': {
                'id': 11,
                'name': 'Pilsener',
                'icon_type': 'bootstrap',
                'icon_value': 'bi-cup',
              },
            },
          ],
        },
      ],
      'totals': {'total': 8, 'paid': 2.5, 'left': 5.5},
      'invoice': null,
      'service_requests': [],
    });

    expect(bill.sessionId, 9);
    expect(bill.total, 8.0);
    expect(bill.orders.single.items.single.price, 2.5);
    expect(bill.orders.single.items.single.productName, 'Pilsener');
    expect(bill.hasOpenSession, isTrue);
    expect(bill.openedAt, DateTime.parse('2026-06-12T20:30:00+00:00'));
    expect(bill.table?.status, 'open');
    expect(bill.serviceRequests, isEmpty);
  });

  test('session bill carries table state and service requests', () {
    final bill = SessionBill.fromJson({
      'table': {
        'id': 4,
        'table_number': 4,
        'reference': 'Terraza',
        'status': 'pending_approval',
        'archived_at': null,
      },
      'session': {
        'id': 9,
        'session_number': 3,
        'status': 'open',
        'opened_at': '2026-06-12T20:30:00+00:00',
      },
      'orders': [],
      'totals': {'total': 0, 'paid': 0, 'left': 0},
      'invoice': null,
      'service_requests': [
        {'id': 31, 'type': 'bill', 'requested_at': '2026-06-12T22:01:00+00:00'},
        {'id': 32, 'type': 'waiter', 'requested_at': null},
      ],
    });

    expect(bill.table?.isAwaitingApproval, isTrue);
    expect(bill.table?.reference, 'Terraza');
    expect(bill.sessionStatus, 'open');
    expect(bill.serviceRequests, hasLength(2));
    expect(bill.serviceRequests.first.isBill, isTrue);
    expect(bill.serviceRequests.last.isBill, isFalse);
  });

  test('session bill tolerates a closed table (null session)', () {
    final bill = SessionBill.fromJson({
      'table': {'id': 4, 'table_number': 4, 'status': 'closed'},
      'session': null,
      'orders': [],
      'totals': {'total': 0, 'paid': 0, 'left': 0},
      'invoice': null,
      'service_requests': [],
    });

    expect(bill.hasOpenSession, isFalse);
    expect(bill.sessionId, isNull);
    expect(bill.openedAt, isNull);
    expect(bill.table?.status, 'closed');
  });

  test('board snapshot parses all three sections', () {
    final board = BoardSnapshot.fromJson({
      'pending_orders': [
        {
          'id': 1,
          'table_id': 4,
          'table_number': 4,
          'status': 'pending',
          'total': 5,
          'paid': 0,
          'left': 5,
          'items': const [],
        },
      ],
      'approval_requests': [
        {
          'id': 31,
          'table_id': 7,
          'table_number': 7,
          'table_status': 'pending_approval',
          'scope': 'first_guest',
          'requested_at': '2026-06-12T21:40:00+00:00',
        },
      ],
      'service_requests': [
        {'id': 5, 'type': 'bill', 'table_number': 2, 'time': '21:45'},
      ],
      'server_time': '2026-06-12T21:47:00+00:00',
    });

    expect(board.isQuiet, isFalse);
    expect(board.pendingOrders.single.tableNumber, 4);
    expect(board.approvalRequests.single.isFirstGuest, isTrue);
    expect(board.serviceRequests.single.isBill, isTrue);
  });

  test('base url normalization', () {
    expect(BarmadaClient.normalizeBaseUrl('https://bar.example/'),
        'https://bar.example');
    expect(BarmadaClient.normalizeBaseUrl('http://10.0.2.2:8000/api/v1'),
        'http://10.0.2.2:8000');
    expect(BarmadaClient.normalizeBaseUrl('  https://bar.example//  '.trim()),
        'https://bar.example');
  });

  test('analytics summary parses, incl. hour map and decimal strings', () {
    final summary = AnalyticsSummary.fromJson({
      'range': 'today',
      'currency_symbol': '€',
      'summary': {
        'total_sales': '152.50',
        'order_count': 23,
        'top_product': 'Pilsener',
        'average_order_value': 6.63,
        'peak_hour': 21,
        // PHP assoc array -> JSON object with string keys.
        'hour_distribution': {'20': 5, '21': 14, '22': 4},
      },
    });

    expect(summary.currencySymbol, '€');
    expect(summary.totalSales, 152.5);
    expect(summary.orderCount, 23);
    expect(summary.peakHour, 21);
    expect(summary.hourDistribution[21], 14);
    expect(summary.topProduct, 'Pilsener');
  });

  test('analytics summary tolerates an empty venue (array-shaped hours)', () {
    final summary = AnalyticsSummary.fromJson({
      'range': '7days',
      'currency_symbol': r'$',
      'summary': {
        'total_sales': 0,
        'order_count': 0,
        'top_product': null,
        'average_order_value': 0,
        'peak_hour': null,
        // Empty PHP assoc array serializes as a JSON ARRAY.
        'hour_distribution': [],
      },
    });

    expect(summary.orderCount, 0);
    expect(summary.peakHour, isNull);
    expect(summary.hourDistribution, isEmpty);
  });

  test('analytics products and service ops parse the real shapes', () {
    final products = ProductStats.fromJson({
      'range': 'today',
      'products': {
        'top_products': [
          {
            'product_id': 11,
            'name': 'Pilsener',
            'quantity': 14,
            'revenue': '35.00'
          },
        ],
        'least_products': [],
        'category_sales': [
          {'category_id': 2, 'name': 'Beers', 'quantity': 18, 'revenue': 47.5},
        ],
        'category_orders': [],
      },
    });
    expect(products.topProducts.single.name, 'Pilsener');
    expect(products.topProducts.single.revenue, 35.0);
    expect(products.categorySales.single.id, 2);

    final ops = ServiceOpsStats.fromJson({
      'range': 'today',
      'service_ops': {
        'most_used_table': 4,
        'avg_session_duration': 73.4,
        'sessions_today': 9,
        'session_reopenings': 1,
        'table_turnover': 1.5,
        'downtime_per_table': null,
        'qr_scans': 31,
        'qr_to_order_conversion': 74.2,
        'avg_time_qr_to_order': 2.4,
        'staff_order_counts': [
          {'name': 'Guests (QR)', 'orders': 19},
          {'name': 'Ana', 'orders': 4},
        ],
        'table_usage_distribution': [
          {'table': 4, 'orders': 11},
        ],
      },
    });
    expect(ops.sessions, 9);
    expect(ops.mostUsedTable, '4');
    expect(ops.avgSessionDurationMinutes, 73.4);
    expect(ops.downtimePerTableMinutes, isNull);
    expect(ops.staffOrderCounts.first.label, 'Guests (QR)');
    expect(ops.staffOrderCounts.first.orders, 19);
    expect(ops.tableUsage.single.label, '4');
  });

  test('tolerant numeric and boolean coercions', () {
    expect(asDouble('2.50'), 2.5);
    expect(asDouble(3), 3.0);
    expect(asDouble(null, 1.5), 1.5);
    expect(asInt('42'), 42);
    expect(asBool(1), isTrue);
    expect(asBool('true'), isTrue);
    expect(asBool('0'), isFalse);
  });
}
