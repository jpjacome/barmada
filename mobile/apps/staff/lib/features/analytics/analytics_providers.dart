import 'package:barmada_api/barmada_api.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../board/board_providers.dart';

/// Everything the analytics screen shows, fetched together so the tab
/// renders in one piece.
class AnalyticsBundle {
  const AnalyticsBundle({
    required this.summary,
    required this.products,
    required this.serviceOps,
  });

  final AnalyticsSummary summary;
  final ProductStats products;
  final ServiceOpsStats serviceOps;
}

/// One fetch of all three analytics endpoints for a range
/// (today | 7days | 30days). No polling — this is a read of history;
/// pull-to-refresh re-fetches.
final analyticsProvider = FutureProvider.autoDispose
    .family<AnalyticsBundle, String>((ref, range) async {
  final client = ref.watch(apiClientProvider);
  if (client == null) {
    throw ApiException('Not signed in.', code: ApiErrorCode.notSignedIn);
  }
  final results = await Future.wait([
    client.analyticsSummary(range: range),
    client.analyticsProducts(range: range),
    client.analyticsServiceOps(range: range),
  ]);
  return AnalyticsBundle(
    summary: results[0] as AnalyticsSummary,
    products: results[1] as ProductStats,
    serviceOps: results[2] as ServiceOpsStats,
  );
});
