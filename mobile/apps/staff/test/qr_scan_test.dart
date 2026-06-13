import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_staff/features/tables/qr_scan_screen.dart';
import 'package:flutter_test/flutter_test.dart';

/// parseQr is pure Dart — fully testable without the camera platform channel.
void main() {
  group('parseQr — URL parser', () {
    test('parses the standard guest QR format', () {
      final result = parseQr('https://bar.example/qr-entry/lacantina/4');
      expect(result?.username, 'lacantina');
      expect(result?.tableNumber, 4);
    });

    test('tolerates an http scheme (dev server)', () {
      final result = parseQr('http://10.0.2.2:8000/qr-entry/myvenue/12');
      expect(result?.username, 'myvenue');
      expect(result?.tableNumber, 12);
    });

    test('tolerates extra path prefix segments', () {
      // /es/qr-entry/{username}/{table_number} — locale prefix in the URL
      final result = parseQr('https://bar.example/es/qr-entry/myvenue/7');
      expect(result?.username, 'myvenue');
      expect(result?.tableNumber, 7);
    });

    test('returns null for a random QR (product barcode, wifi, etc.)', () {
      expect(parseQr('https://example.com/product/123'), isNull);
      expect(parseQr('WIFI:T:WPA;S:CafeWifi;P:hunter2;;'), isNull);
      expect(parseQr('not-a-url'), isNull);
    });

    test('returns null when table_number is not an integer', () {
      expect(parseQr('https://bar.example/qr-entry/venue/abc'), isNull);
    });

    test('returns null when path is too short', () {
      expect(parseQr('https://bar.example/qr-entry/venue'), isNull);
    });
  });

  group('QrPayload — data class', () {
    test('fields are accessible', () {
      const payload = QrPayload(username: 'lacantina', tableNumber: 4);
      expect(payload.username, 'lacantina');
      expect(payload.tableNumber, 4);
    });
  });

  group('resolveQr — client API method', () {
    test('calls the correct endpoint and parses the table', () async {
      // Arrange: a fake client that intercepts the scan endpoint.
      final calls = <({String username, int tableNumber})>[];
      final fakeClient = _FakeClient(calls);

      // Act.
      final result = await fakeClient.resolveQr(
        username: 'lacantina',
        tableNumber: 4,
      );

      // Assert.
      expect(calls.single.username, 'lacantina');
      expect(calls.single.tableNumber, 4);
      expect(result.id, 42);
      expect(result.tableNumber, 4);
      expect(result.status, 'open');
    });
  });
}

class _FakeClient extends BarmadaClient {
  _FakeClient(this._calls) : super(baseUrl: 'http://fake');

  final List<({String username, int tableNumber})> _calls;

  @override
  Future<TableInfo> resolveQr({
    required String username,
    required int tableNumber,
  }) async {
    _calls.add((username: username, tableNumber: tableNumber));
    return TableInfo(id: 42, tableNumber: tableNumber, status: 'open');
  }
}
