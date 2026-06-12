import 'package:barmada_api/barmada_api.dart';
import 'package:barmada_core/barmada_core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// The authed client, or null while not signed in.
final apiClientProvider = Provider<BarmadaClient?>((ref) {
  final session = ref.watch(sessionControllerProvider).value;
  return session is Authed ? session.client : null;
});

/// One poll of /board. The screen invalidates this every 5 seconds —
/// the same cadence as the web board.
final boardProvider = FutureProvider.autoDispose<BoardSnapshot>((ref) async {
  final client = ref.watch(apiClientProvider);
  if (client == null) {
    throw ApiException('Not signed in.', code: ApiErrorCode.notSignedIn);
  }
  return client.board();
});
