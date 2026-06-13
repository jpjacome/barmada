import 'package:barmada_api/barmada_api.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../l10n/api_error_l10n.dart';
import '../../l10n/app_localizations.dart';
import '../board/board_providers.dart';
import 'table_session_screen.dart';

/// Parses a Barmada guest QR URL into the two fields the API needs.
///
/// Guest QR codes encode: `https://{host}/qr-entry/{username}/{table_number}`.
/// Returns null when the scanned text doesn't match.
QrPayload? parseQr(String raw) {
  try {
    final uri = Uri.tryParse(raw);
    if (uri == null) return null;
    final segments = uri.pathSegments;
    if (segments.length < 3) return null;
    // Accept /qr-entry/{username}/{table_number} anywhere in the path.
    final idx = segments.indexOf('qr-entry');
    if (idx < 0 || idx + 2 >= segments.length) return null;
    final username = segments[idx + 1];
    final tableNumber = int.tryParse(segments[idx + 2]);
    if (username.isEmpty || tableNumber == null) return null;
    return QrPayload(username: username, tableNumber: tableNumber);
  } catch (_) {
    return null;
  }
}

class QrPayload {
  const QrPayload({required this.username, required this.tableNumber});
  final String username;
  final int tableNumber;
}

/// Staff QR-scan screen: point the camera at any guest table QR code to
/// jump straight to that table's session screen.
///
/// The camera stays live until a valid Barmada QR is decoded. One
/// resolution attempt runs at a time — the controller is paused while
/// the API call is in flight, then resumed on failure so the staffer
/// can retry by pointing at another code.
class QrScanScreen extends ConsumerStatefulWidget {
  const QrScanScreen({super.key});

  @override
  ConsumerState<QrScanScreen> createState() => _QrScanScreenState();
}

class _QrScanScreenState extends ConsumerState<QrScanScreen> {
  final _camera = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _resolving = false;

  @override
  void dispose() {
    _camera.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_resolving) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null) return;

    final payload = parseQr(raw);
    if (payload == null) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
              content: Text(AppLocalizations.of(context).scanQrInvalidUrl)));
      }
      return;
    }

    setState(() => _resolving = true);
    await _camera.stop();

    final client = ref.read(apiClientProvider);
    if (!mounted) return;
    final l10n = AppLocalizations.of(context);

    try {
      final table = await client!.resolveQr(
        username: payload.username,
        tableNumber: payload.tableNumber,
      );
      if (!mounted) return;

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text(l10n.scanQrSuccess(table.tableNumber)),
          duration: const Duration(seconds: 2),
        ));

      // Replace this screen with the session screen so Back goes to Tables.
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => TableSessionScreen(
            tableId: table.id,
            tableNumber: table.tableNumber,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(l10n.errorMessage(e))));
      setState(() => _resolving = false);
      await _camera.start();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.scanQrTitle)),
      body: Stack(
        children: [
          MobileScanner(
            controller: _camera,
            onDetect: _onDetect,
          ),
          // Dim overlay with a cut-out finder square.
          CustomPaint(
            size: MediaQuery.of(context).size,
            painter: _ViewfinderPainter(color: scheme.primary),
          ),
          Positioned(
            bottom: 48,
            left: 0,
            right: 0,
            child: Center(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: _resolving
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                            strokeWidth: 2.5, color: Colors.white),
                      )
                    : Text(
                        l10n.scanQrHint,
                        style: const TextStyle(color: Colors.white),
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Draws a transparent square in the centre over a semi-opaque overlay,
/// with a sage-coloured border matching the Barmada design system.
class _ViewfinderPainter extends CustomPainter {
  const _ViewfinderPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    const side = 260.0;
    final cx = size.width / 2;
    final cy = size.height / 2;
    final rect =
        Rect.fromCenter(center: Offset(cx, cy), width: side, height: side);

    final overlay = Paint()..color = Colors.black.withValues(alpha: 0.55);
    canvas.drawPath(
      Path.combine(
        PathOperation.difference,
        Path()..addRect(Rect.fromLTWH(0, 0, size.width, size.height)),
        Path()
          ..addRRect(RRect.fromRectAndRadius(rect, const Radius.circular(12))),
      ),
      overlay,
    );

    canvas.drawRRect(
      RRect.fromRectAndRadius(rect, const Radius.circular(12)),
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5,
    );
  }

  @override
  bool shouldRepaint(covariant _ViewfinderPainter old) => old.color != color;
}
