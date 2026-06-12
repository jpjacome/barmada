import 'package:barmada_api/barmada_api.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../board/board_providers.dart';

/// One poll of `GET /products` — the whole catalog with availability.
/// The screen invalidates this every 5 seconds so an 86 from the web
/// dashboard (or another phone) shows up mid-service.
final productsProvider =
    FutureProvider.autoDispose<List<ProductInfo>>((ref) async {
  final client = ref.watch(apiClientProvider);
  if (client == null) {
    throw ApiException('Not signed in.', code: ApiErrorCode.notSignedIn);
  }
  return client.products();
});
