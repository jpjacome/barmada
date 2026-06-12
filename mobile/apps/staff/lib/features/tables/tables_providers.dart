import 'package:barmada_api/barmada_api.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../board/board_providers.dart';

/// One poll of `GET /tables` (active tables only). The grid invalidates
/// this every 5 seconds — the board cadence.
final tablesProvider = FutureProvider.autoDispose<List<TableInfo>>((ref) async {
  final client = ref.watch(apiClientProvider);
  if (client == null) {
    throw ApiException('Not signed in.', code: ApiErrorCode.notSignedIn);
  }
  return client.tables();
});

/// One poll of `GET /tables/{id}/session` — the live bill for one table.
final tableSessionProvider =
    FutureProvider.autoDispose.family<SessionBill, int>((ref, tableId) async {
  final client = ref.watch(apiClientProvider);
  if (client == null) {
    throw ApiException('Not signed in.', code: ApiErrorCode.notSignedIn);
  }
  return client.tableSession(tableId);
});
