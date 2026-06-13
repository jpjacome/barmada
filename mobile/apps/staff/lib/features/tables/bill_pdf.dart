import 'dart:typed_data';

import 'package:barmada_api/barmada_api.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

/// Generates a receipt-style PDF for a table's bill.
///
/// Pure Dart — no Flutter widgets, no platform channels, fully testable.
/// Call [generate] with the bill data and get back raw PDF bytes ready to
/// write to a temp file and hand to share_plus.
///
/// Pass [baseFont]/[boldFont] TTF bytes (the app ships Roboto under
/// assets/fonts/) for full Unicode coverage — the PDF built-in Helvetica
/// cannot draw "€" and other non-WinAnsi currency symbols.
class BillPdfGenerator {
  const BillPdfGenerator();

  /// Builds the bill PDF and returns the raw bytes.
  ///
  ///   [bill]           — the live session data (orders, totals).
  ///   [venueName]      — shown as the bill header.
  ///   [currencySymbol] — venue setting, never a locale guess.
  ///   [locale]         — date/time formatting (e.g. 'es' or 'en').
  ///   [baseFont]/[boldFont] — TTF bytes for Unicode text.
  ///   [compress]       — leave true in production; tests pass false so
  ///                      content streams stay string-searchable.
  Future<List<int>> generate({
    required SessionBill bill,
    required String venueName,
    required String currencySymbol,
    String locale = 'en',
    ByteData? baseFont,
    ByteData? boldFont,
    bool compress = true,
  }) async {
    // Locale data for DateFormat (no-op when already initialized).
    await initializeDateFormatting(locale);

    final theme = (baseFont != null && boldFont != null)
        ? pw.ThemeData.withFont(
            base: pw.Font.ttf(baseFont),
            bold: pw.Font.ttf(boldFont),
          )
        : null;

    final doc = pw.Document(
      creator: 'Barmada Staff',
      title: 'Bill — table ${bill.table?.tableNumber ?? ''}',
      compress: compress,
      theme: theme,
    );

    doc.addPage(pw.Page(
      pageFormat: PdfPageFormat.roll80,
      margin: const pw.EdgeInsets.all(12),
      build: (context) => _buildContent(
        context,
        bill: bill,
        venueName: venueName,
        symbol: currencySymbol,
        locale: locale,
      ),
    ));

    return doc.save();
  }

  pw.Widget _buildContent(
    pw.Context context, {
    required SessionBill bill,
    required String venueName,
    required String symbol,
    required String locale,
  }) {
    final tableNum = bill.table?.tableNumber;
    final openedAt = bill.openedAt;
    final dateFmt = DateFormat.yMd(locale);
    final timeFmt = DateFormat.jm(locale);

    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.stretch,
      children: [
        // ── Header ──────────────────────────────────────────────
        pw.Center(
          child: pw.Text(
            venueName,
            style: pw.TextStyle(
              fontSize: 16,
              fontWeight: pw.FontWeight.bold,
            ),
          ),
        ),
        pw.SizedBox(height: 4),
        if (tableNum != null)
          pw.Center(
            child: pw.Text(
              'Table $tableNum',
              style: const pw.TextStyle(fontSize: 12),
            ),
          ),
        if (openedAt != null)
          pw.Center(
            child: pw.Text(
              '${dateFmt.format(openedAt.toLocal())}  '
              '${timeFmt.format(openedAt.toLocal())}',
              style: const pw.TextStyle(fontSize: 9),
            ),
          ),
        pw.Divider(thickness: 0.5),

        // ── Orders (cancelled excluded, like every other surface) ──
        for (final order in bill.orders)
          if (order.status != 'cancelled') ...[
            _orderSection(order, symbol),
            pw.SizedBox(height: 4),
          ],

        pw.Divider(thickness: 0.5),

        // ── Totals ──────────────────────────────────────────────
        _totalRow('Total', _fmt(symbol, bill.total)),
        _totalRow('Paid', _fmt(symbol, bill.paid), color: PdfColors.green700),
        _totalRow(
          'Remaining',
          _fmt(symbol, bill.left),
          bold: true,
          color: bill.left > 0 ? PdfColors.red800 : PdfColors.green700,
        ),

        pw.Divider(thickness: 0.5),
        pw.Center(
          child: pw.Text(
            'Thank you!',
            style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
          ),
        ),
      ],
    );
  }

  pw.Widget _orderSection(OrderInfo order, String symbol) {
    final note = order.note;

    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.stretch,
      children: [
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text('Order #${order.id}',
                style:
                    pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
            pw.Text(_fmt(symbol, order.total),
                style:
                    pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
          ],
        ),
        if (note != null && note.isNotEmpty)
          pw.Text('"$note"',
              style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
        for (final item in order.items)
          pw.Padding(
            padding: const pw.EdgeInsets.only(left: 8, top: 2),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                // Paid items: struck through and greened — no glyph
                // dependence, works with any font.
                pw.Text(
                  item.productName ?? '#${item.productId}',
                  style: pw.TextStyle(
                    fontSize: 9,
                    color: item.isPaid ? PdfColors.grey600 : PdfColors.black,
                    decoration: item.isPaid
                        ? pw.TextDecoration.lineThrough
                        : pw.TextDecoration.none,
                  ),
                ),
                pw.Text(
                  _fmt(symbol, item.price),
                  style: pw.TextStyle(
                    fontSize: 9,
                    color: item.isPaid ? PdfColors.green700 : PdfColors.black,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  pw.Widget _totalRow(
    String label,
    String value, {
    bool bold = false,
    PdfColor? color,
  }) =>
      pw.Padding(
        padding: const pw.EdgeInsets.symmetric(vertical: 2),
        child: pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text(
              label,
              style: pw.TextStyle(
                fontSize: 10,
                fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
                color: color ?? PdfColors.black,
              ),
            ),
            pw.Text(
              value,
              style: pw.TextStyle(
                fontSize: 10,
                fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
                color: color ?? PdfColors.black,
              ),
            ),
          ],
        ),
      );

  String _fmt(String symbol, double amount) =>
      '$symbol${amount.toStringAsFixed(2)}';
}
