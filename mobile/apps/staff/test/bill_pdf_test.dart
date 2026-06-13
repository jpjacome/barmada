import 'dart:io';
import 'dart:typed_data';

import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_staff/features/tables/bill_pdf.dart';
import 'package:flutter_test/flutter_test.dart';

/// BillPdfGenerator is pure Dart — no platform channels, no Flutter
/// widgets. Tests load the bundled Roboto TTFs straight from disk
/// (flutter test runs with cwd = package root) and generate with
/// compress: false so content streams stay string-searchable.
void main() {
  const generator = BillPdfGenerator();

  late final ByteData baseFont;
  late final ByteData boldFont;

  setUpAll(() {
    baseFont = File('assets/fonts/Roboto-Regular.ttf')
        .readAsBytesSync()
        .buffer
        .asByteData();
    boldFont = File('assets/fonts/Roboto-Bold.ttf')
        .readAsBytesSync()
        .buffer
        .asByteData();
  });

  SessionBill makeBill({List<OrderInfo> orders = const []}) => SessionBill(
        orders: orders,
        total: orders.fold(0.0, (s, o) => s + o.total),
        paid: orders.fold(0.0, (s, o) => s + o.paid),
        left: orders.fold(0.0, (s, o) => s + o.left),
        sessionId: 9,
        sessionStatus: 'open',
        openedAt: DateTime.utc(2026, 6, 13, 20, 0),
        table: TableInfo(id: 4, tableNumber: 4, status: 'open'),
      );

  OrderInfo makeOrder({
    required int id,
    required double total,
    List<OrderItemInfo> items = const [],
    String? note,
  }) =>
      OrderInfo(
        id: id,
        tableId: 4,
        status: 'pending',
        total: total,
        paid: 0,
        left: total,
        items: items,
        note: note,
      );

  OrderItemInfo makeItem(String name, double price, {bool paid = false}) =>
      OrderItemInfo(
        id: 1,
        productId: 11,
        itemIndex: 0,
        price: price,
        isPaid: paid,
        productName: name,
      );

  /// Content-search helper: built-in Helvetica + compress:false writes
  /// literal ASCII into the content streams (embedded TTFs encode text
  /// as glyph indices). Multi-word strings are split into kerned TJ
  /// segments, so probes are single words only.
  Future<String> rawPdf(SessionBill bill,
      {String venue = 'Cantina', String symbol = r'$'}) async {
    final bytes = await generator.generate(
      bill: bill,
      venueName: venue,
      currencySymbol: symbol,
      compress: false,
    );
    return String.fromCharCodes(bytes);
  }

  test('generates a valid PDF (magic header) with the euro symbol', () async {
    final bytes = await generator.generate(
      bill: makeBill(orders: [makeOrder(id: 1, total: 5)]),
      venueName: 'La Cantina',
      currencySymbol: '€',
      baseFont: baseFont,
      boldFont: boldFont,
    );
    expect(bytes, isNotEmpty);
    expect(String.fromCharCodes(bytes.take(4)), equals('%PDF'));
  });

  test('PDF contains the venue name and table number', () async {
    final raw = await rawPdf(
      makeBill(orders: [makeOrder(id: 42, total: 12.5)]),
      venue: 'Cantina',
    );
    expect(raw.contains('Cantina'), isTrue, reason: 'venue name not in PDF');
    expect(raw.contains('Table'), isTrue, reason: 'table header not in PDF');
  });

  test('PDF contains item names, prices and the note', () async {
    final raw = await rawPdf(makeBill(orders: [
      makeOrder(
        id: 1,
        total: 7.5,
        note: 'sin hielo',
        items: [
          makeItem('Pilsener', 2.5),
          makeItem('Empanada', 1.75, paid: true),
          makeItem('Pilsener', 2.5),
        ],
      ),
    ]));
    expect(raw.contains('Pilsener'), isTrue);
    expect(raw.contains('Empanada'), isTrue);
    expect(raw.contains('hielo'), isTrue); // note made it onto the bill
  });

  test('cancelled orders are excluded from the PDF', () async {
    final cancelled = OrderInfo(
      id: 99,
      tableId: 4,
      status: 'cancelled',
      total: 5,
      paid: 0,
      left: 5,
      items: [makeItem('Ghost item', 5)],
    );
    final kept = makeOrder(id: 1, total: 3, items: [makeItem('Pilsener', 3)]);

    final raw = await rawPdf(SessionBill(
      orders: [cancelled, kept],
      total: 8,
      paid: 0,
      left: 8,
      sessionId: 9,
      table: TableInfo(id: 4, tableNumber: 4, status: 'open'),
    ));
    expect(raw.contains('Ghost'), isFalse,
        reason: 'cancelled order leaked into the bill');
    expect(raw.contains('Pilsener'), isTrue);
  });

  test('totals section shows Total, Paid and Remaining', () async {
    final raw = await rawPdf(makeBill(orders: [
      makeOrder(id: 1, total: 10, items: [makeItem('Beer', 10)]),
    ]));
    expect(raw.contains('Total'), isTrue);
    expect(raw.contains('Paid'), isTrue);
    expect(raw.contains('Remaining'), isTrue);
  });

  test('works without embedded fonts too (ASCII venues)', () async {
    final bytes = await generator.generate(
      bill: makeBill(orders: [makeOrder(id: 1, total: 5)]),
      venueName: 'Plain Bar',
      currencySymbol: r'$',
    );
    expect(String.fromCharCodes(bytes.take(4)), equals('%PDF'));
  });
}
