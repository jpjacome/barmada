import 'package:dio/dio.dart';

import 'exceptions.dart';
import 'models.dart';

/// The Barmada staff API client.
///
/// One instance per (server, token) pair. The base URL is the server's
/// root (e.g. `https://bar.example` or `http://10.0.2.2:8000`); the
/// `/api/v1` prefix is handled here.
class BarmadaClient {
  BarmadaClient({required String baseUrl, String? token, Dio? dio})
      : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = '${normalizeBaseUrl(baseUrl)}/api/v1'
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 15)
      ..headers['Accept'] = 'application/json';
    if (token != null && token.isNotEmpty) {
      _dio.options.headers['Authorization'] = 'Bearer $token';
    }
  }

  final Dio _dio;

  /// Trims trailing slashes and a trailing `/api/v1` if the user pasted it.
  static String normalizeBaseUrl(String input) {
    var url = input.trim();
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    if (url.endsWith('/api/v1')) {
      url = url.substring(0, url.length - '/api/v1'.length);
    }
    return url;
  }

  // ---------------------------------------------------------------- core

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Object? data,
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.request<dynamic>(
        path,
        data: data,
        queryParameters: query,
        options: Options(method: method),
      );
      final body = response.data;
      if (body is Map<String, dynamic>) return body;
      return <String, dynamic>{};
    } on DioException catch (e) {
      throw _mapError(e);
    }
  }

  ApiException _mapError(DioException e) {
    final status = e.response?.statusCode;
    final data = e.response?.data;
    String? message;
    if (data is Map && data['message'] is String) {
      message = data['message'] as String;
    }
    if (status == 401) return UnauthenticatedException(message);
    if (status != null) {
      return ApiException(
        message ?? 'Request failed ($status).',
        statusCode: status,
        // Only coded when the text is ours; a server message passes through.
        code: message == null ? ApiErrorCode.requestFailed : null,
      );
    }
    return ApiException(
      'Could not reach the server. Check the address and your connection.',
      code: ApiErrorCode.network,
    );
  }

  // ---------------------------------------------------------------- meta

  Future<ServerMeta> meta() async =>
      ServerMeta.fromJson(await _request('GET', '/meta'));

  // ---------------------------------------------------------------- auth

  Future<AuthResult> login({
    required String email,
    required String password,
    required String deviceName,
  }) async =>
      AuthResult.fromJson(await _request('POST', '/auth/login', data: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      }));

  Future<void> logout() async => _request('POST', '/auth/logout');

  Future<ApiUser> currentUser() async {
    final body = await _request('GET', '/auth/user');
    return ApiUser.fromJson(body['user'] as Map<String, dynamic>);
  }

  // -------------------------------------------------------------- devices

  Future<void> registerDevice({
    required String deviceUuid,
    String? name,
    String? platform,
    String? fcmToken,
    String? appVersion,
  }) async =>
      _request('POST', '/devices', data: {
        'device_uuid': deviceUuid,
        if (name != null) 'name': name,
        if (platform != null) 'platform': platform,
        if (fcmToken != null) 'fcm_token': fcmToken,
        if (appVersion != null) 'app_version': appVersion,
      });

  Future<void> unregisterDevice(String deviceUuid) async =>
      _request('DELETE', '/devices/$deviceUuid');

  // ---------------------------------------------------------------- board

  Future<BoardSnapshot> board() async =>
      BoardSnapshot.fromJson(await _request('GET', '/board'));

  // --------------------------------------------------------------- orders

  Future<List<OrderInfo>> orders({String? status, int? tableId}) async {
    final body = await _request('GET', '/orders', query: {
      if (status != null) 'status': status,
      if (tableId != null) 'table_id': tableId,
    });
    return (body['data'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(OrderInfo.fromJson)
        .toList();
  }

  Future<OrderInfo> order(int id) async => _orderFrom(
        await _request('GET', '/orders/$id'),
      );

  Future<OrderInfo> createOrder({
    required int tableId,
    required Map<int, int> products,
    String? note,
  }) async =>
      _orderFrom(await _request('POST', '/orders', data: {
        'table_id': tableId,
        'products': products.map((id, qty) => MapEntry(id.toString(), qty)),
        if (note != null && note.isNotEmpty) 'note': note,
      }));

  Future<OrderInfo> setOrderStatus(int id, String status) async => _orderFrom(
      await _request('PATCH', '/orders/$id/status', data: {'status': status}));

  Future<OrderInfo> deliverOrder(int id) => setOrderStatus(id, 'delivered');
  Future<OrderInfo> reopenOrder(int id) => setOrderStatus(id, 'pending');
  Future<OrderInfo> cancelOrder(int id) => setOrderStatus(id, 'cancelled');

  Future<void> deleteOrder(int id) async => _request('DELETE', '/orders/$id');

  Future<OrderInfo> toggleItemPaid({
    required int orderId,
    required int productId,
    required int itemIndex,
  }) async =>
      _orderFrom(await _request('POST', '/orders/$orderId/items/toggle-paid',
          data: {'product_id': productId, 'item_index': itemIndex}));

  Future<OrderInfo> settleOrder(int id) async =>
      _orderFrom(await _request('POST', '/orders/$id/settle'));

  OrderInfo _orderFrom(Map<String, dynamic> body) =>
      OrderInfo.fromJson((body['data'] ?? body) as Map<String, dynamic>);

  // --------------------------------------------------------------- tables

  Future<List<TableInfo>> tables({bool includeArchived = false}) async {
    final body = await _request('GET', '/tables',
        query: {if (includeArchived) 'include_archived': 1});
    final active = (body['tables'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(TableInfo.fromJson);
    final archived = (body['archived'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(TableInfo.fromJson);
    return [...active, ...archived];
  }

  Future<SessionBill> tableSession(int tableId) async =>
      SessionBill.fromJson(await _request('GET', '/tables/$tableId/session'));

  Future<void> openTable(int tableId) async =>
      _request('POST', '/tables/$tableId/open');

  Future<void> closeTable(int tableId, {bool settle = false}) async =>
      _request('POST', '/tables/$tableId/close',
          data: {if (settle) 'settle': true});

  Future<void> approveTable(int tableId) async =>
      _request('POST', '/tables/$tableId/approve');

  Future<void> settleTable(int tableId) async =>
      _request('POST', '/tables/$tableId/settle');

  Future<void> archiveTable(int tableId) async =>
      _request('POST', '/tables/$tableId/archive');

  Future<void> restoreTable(int tableId) async =>
      _request('POST', '/tables/$tableId/restore');

  // ------------------------------------------------------------ approvals

  Future<List<ApprovalRequestInfo>> approvalRequests() async {
    final body = await _request('GET', '/approval-requests');
    final rows = body['approval_requests'] ?? body['data'] ?? const [];
    return (rows as List)
        .whereType<Map<String, dynamic>>()
        .map(ApprovalRequestInfo.fromJson)
        .toList();
  }

  Future<void> approveRequest(int requestId) async =>
      _request('POST', '/approval-requests/$requestId/approve');

  // ----------------------------------------------------- service requests

  Future<List<ServiceRequestInfo>> serviceRequests({String? status}) async {
    final body = await _request('GET', '/service-requests',
        query: {if (status != null) 'status': status});
    return (body['service_requests'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(ServiceRequestInfo.fromJson)
        .toList();
  }

  Future<void> resolveServiceRequest(int id) async =>
      _request('POST', '/service-requests/$id/done');

  // -------------------------------------------------------------- catalog

  Future<List<ProductInfo>> products({bool? available}) async {
    final body = await _request('GET', '/products',
        query: {if (available != null) 'available': available ? 1 : 0});
    return (body['products'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(ProductInfo.fromJson)
        .toList();
  }

  Future<ProductInfo> toggleProductAvailability(int productId) async {
    final body =
        await _request('POST', '/products/$productId/toggle-availability');
    return ProductInfo.fromJson(body['product'] as Map<String, dynamic>);
  }

  // ------------------------------------------------------------- settings
  //
  // Editor-only on the server (staff tokens get 403); staff inherit the
  // venue's settings through /auth/user.

  Future<BusinessSettings> settings() async =>
      BusinessSettings.fromJson(await _request('GET', '/settings'));

  Future<BusinessSettings> updateSettings({
    required String currencySymbol,
    required String locale,
    String? businessTimezone,
    int? dayCutoffHour,
  }) async =>
      BusinessSettings.fromJson(await _request('PATCH', '/settings', data: {
        'currency_symbol': currencySymbol,
        'locale': locale,
        if (businessTimezone != null && businessTimezone.isNotEmpty)
          'business_timezone': businessTimezone,
        if (dayCutoffHour != null) 'day_cutoff_hour': dayCutoffHour,
      }));

  // ------------------------------------------------------------ analytics
  //
  // Editor-only on the server (staff tokens get 403). Ranges:
  // today (default) | 7days | 30days | month — business-day bucketed
  // in the venue's timezone.

  Future<AnalyticsSummary> analyticsSummary({String range = 'today'}) async =>
      AnalyticsSummary.fromJson(
          await _request('GET', '/analytics/summary', query: {'range': range}));

  Future<ProductStats> analyticsProducts({String range = 'today'}) async =>
      ProductStats.fromJson(await _request('GET', '/analytics/products',
          query: {'range': range}));

  Future<ServiceOpsStats> analyticsServiceOps({String range = 'today'}) async =>
      ServiceOpsStats.fromJson(await _request('GET', '/analytics/service-ops',
          query: {'range': range}));
}
