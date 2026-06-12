/// Response models for the Barmada staff API.
///
/// Parsing is deliberately tolerant: numeric fields accept ints, doubles
/// and decimal strings (PHP decimals serialize either way depending on
/// the database driver), and optional fields never throw.
library;

double asDouble(Object? value, [double fallback = 0]) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? fallback;
  return fallback;
}

int asInt(Object? value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? fallback;
  return fallback;
}

bool asBool(Object? value, [bool fallback = false]) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) return value == '1' || value.toLowerCase() == 'true';
  return fallback;
}

String? asStringOrNull(Object? value) => value?.toString();

DateTime? asDateTime(Object? value) =>
    value is String ? DateTime.tryParse(value) : null;

/// `GET /api/v1/meta` — public server discovery for the add-server flow.
class ServerMeta {
  ServerMeta({
    required this.product,
    required this.name,
    required this.apiVersion,
    required this.features,
  });

  factory ServerMeta.fromJson(Map<String, dynamic> json) {
    final api = json['api'];
    return ServerMeta(
      product: asStringOrNull(json['product']) ?? '',
      name: asStringOrNull(json['name']) ?? 'Barmada',
      apiVersion: api is Map ? asInt(api['version'], 1) : 1,
      features: (json['features'] as List? ?? const [])
          .map((e) => e.toString())
          .toList(),
    );
  }

  final String product;
  final String name;
  final int apiVersion;
  final List<String> features;

  bool get isBarmada => product == 'barmada';
  bool supports(String feature) => features.contains(feature);
}

/// Per-venue presentation settings, reported with the signed-in user.
class VenueSettings {
  VenueSettings({
    required this.currencySymbol,
    required this.guestLocale,
    required this.timezone,
    required this.dayCutoffHour,
    this.businessName,
  });

  factory VenueSettings.fromJson(Map<String, dynamic> json) => VenueSettings(
        currencySymbol: asStringOrNull(json['currency_symbol']) ?? r'$',
        guestLocale: asStringOrNull(json['guest_locale']) ??
            asStringOrNull(json['locale']) ??
            'es',
        timezone: asStringOrNull(json['timezone']) ??
            asStringOrNull(json['business_timezone']) ??
            'UTC',
        dayCutoffHour: asInt(json['day_cutoff_hour']),
        businessName: asStringOrNull(json['business_name']),
      );

  final String currencySymbol;
  final String guestLocale;
  final String timezone;
  final int dayCutoffHour;
  final String? businessName;
}

/// The authenticated account.
class ApiUser {
  ApiUser({
    required this.id,
    required this.name,
    required this.email,
    required this.isAdmin,
    required this.isEditor,
    required this.isStaff,
    this.username,
    this.businessName,
    this.editorId,
    this.venue,
  });

  factory ApiUser.fromJson(Map<String, dynamic> json) => ApiUser(
        id: asInt(json['id']),
        name: asStringOrNull(json['name']) ?? '',
        email: asStringOrNull(json['email']) ?? '',
        isAdmin: asBool(json['is_admin']),
        isEditor: asBool(json['is_editor']),
        isStaff: asBool(json['is_staff']),
        username: asStringOrNull(json['username']),
        businessName: asStringOrNull(json['business_name']),
        editorId: json['editor_id'] == null ? null : asInt(json['editor_id']),
        venue: json['venue'] is Map<String, dynamic>
            ? VenueSettings.fromJson(json['venue'] as Map<String, dynamic>)
            : null,
      );

  final int id;
  final String name;
  final String email;
  final bool isAdmin;
  final bool isEditor;
  final bool isStaff;
  final String? username;
  final String? businessName;
  final int? editorId;
  final VenueSettings? venue;

  String get roleLabel =>
      isAdmin ? 'Platform admin' : (isEditor ? 'Owner' : 'Staff');
}

/// `POST /api/v1/auth/login`.
class AuthResult {
  AuthResult({required this.token, required this.abilities, required this.user});

  factory AuthResult.fromJson(Map<String, dynamic> json) => AuthResult(
        token: asStringOrNull(json['token']) ?? '',
        abilities: (json['abilities'] as List? ?? const [])
            .map((e) => e.toString())
            .toList(),
        user: ApiUser.fromJson(json['user'] as Map<String, dynamic>),
      );

  final String token;
  final List<String> abilities;
  final ApiUser user;
}

/// One unit-line of an order (items are exploded one row per unit).
class OrderItemInfo {
  OrderItemInfo({
    required this.id,
    required this.productId,
    required this.itemIndex,
    required this.price,
    required this.isPaid,
    this.productName,
  });

  factory OrderItemInfo.fromJson(Map<String, dynamic> json) {
    final product = json['product'];
    return OrderItemInfo(
      id: asInt(json['id']),
      productId: asInt(json['product_id']),
      itemIndex: asInt(json['item_index']),
      price: asDouble(json['price']),
      isPaid: asBool(json['is_paid']),
      productName: asStringOrNull(json['product_name']) ??
          (product is Map ? asStringOrNull(product['name']) : null),
    );
  }

  final int id;
  final int productId;
  final int itemIndex;
  final double price;
  final bool isPaid;
  final String? productName;
}

/// An order as the board and session screens see it.
class OrderInfo {
  OrderInfo({
    required this.id,
    required this.tableId,
    required this.status,
    required this.total,
    required this.paid,
    required this.left,
    required this.items,
    this.tableNumber,
    this.tableSessionId,
    this.note,
    this.createdBy,
    this.createdAt,
  });

  factory OrderInfo.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(OrderItemInfo.fromJson)
        .toList();
    final total = json.containsKey('total')
        ? asDouble(json['total'])
        : asDouble(json['total_amount']);
    final paid = json.containsKey('paid')
        ? asDouble(json['paid'])
        : asDouble(json['amount_paid']);
    final left = json.containsKey('left')
        ? asDouble(json['left'])
        : asDouble(json['amount_left'], total - paid);
    return OrderInfo(
      id: asInt(json['id']),
      tableId: asInt(json['table_id']),
      status: asStringOrNull(json['status']) ?? 'pending',
      total: total,
      paid: paid,
      left: left,
      items: items,
      tableNumber: json['table_number'] == null
          ? null
          : asInt(json['table_number']),
      tableSessionId: json['table_session_id'] == null
          ? null
          : asInt(json['table_session_id']),
      note: asStringOrNull(json['note']),
      createdBy:
          json['created_by'] == null ? null : asInt(json['created_by']),
      createdAt: asDateTime(json['created_at']),
    );
  }

  final int id;
  final int tableId;
  final String status;
  final double total;
  final double paid;
  final double left;
  final List<OrderItemInfo> items;
  final int? tableNumber;
  final int? tableSessionId;
  final String? note;
  final int? createdBy;
  final DateTime? createdAt;

  bool get isPending => status == 'pending';

  /// Items grouped for display: "2 × Pilsener".
  Map<String, int> get groupedItems {
    final counts = <String, int>{};
    for (final item in items) {
      final name = item.productName ?? 'Product #${item.productId}';
      counts[name] = (counts[name] ?? 0) + 1;
    }
    return counts;
  }
}

/// A guest device waiting for staff approval.
class ApprovalRequestInfo {
  ApprovalRequestInfo({
    required this.id,
    required this.tableId,
    required this.scope,
    this.tableNumber,
    this.tableStatus,
    this.requestedAt,
  });

  factory ApprovalRequestInfo.fromJson(Map<String, dynamic> json) =>
      ApprovalRequestInfo(
        id: asInt(json['id']),
        tableId: asInt(json['table_id']),
        scope: asStringOrNull(json['scope']) ?? 'additional_guest',
        tableNumber: json['table_number'] == null
            ? null
            : asInt(json['table_number']),
        tableStatus: asStringOrNull(json['table_status']),
        requestedAt: asDateTime(json['requested_at']),
      );

  final int id;
  final int tableId;

  /// `first_guest` (table awaiting approval) or `additional_guest`.
  final String scope;
  final int? tableNumber;
  final String? tableStatus;
  final DateTime? requestedAt;

  bool get isFirstGuest => scope == 'first_guest';
}

/// A guest tap: "bring the bill" or "call a waiter".
class ServiceRequestInfo {
  ServiceRequestInfo({
    required this.id,
    required this.type,
    this.tableId,
    this.tableNumber,
    this.status,
    this.time,
  });

  factory ServiceRequestInfo.fromJson(Map<String, dynamic> json) =>
      ServiceRequestInfo(
        id: asInt(json['id']),
        type: asStringOrNull(json['type']) ?? 'waiter',
        tableId: json['table_id'] == null ? null : asInt(json['table_id']),
        tableNumber: json['table_number'] == null
            ? null
            : asInt(json['table_number']),
        status: asStringOrNull(json['status']),
        time: asStringOrNull(json['time']) ??
            asStringOrNull(json['requested_at']),
      );

  final int id;
  final String type;
  final int? tableId;
  final int? tableNumber;
  final String? status;
  final String? time;

  bool get isBill => type == 'bill';
}

/// `GET /api/v1/board` — one poll, everything live service needs.
class BoardSnapshot {
  BoardSnapshot({
    required this.pendingOrders,
    required this.approvalRequests,
    required this.serviceRequests,
  });

  factory BoardSnapshot.fromJson(Map<String, dynamic> json) => BoardSnapshot(
        pendingOrders: (json['pending_orders'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(OrderInfo.fromJson)
            .toList(),
        approvalRequests: (json['approval_requests'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(ApprovalRequestInfo.fromJson)
            .toList(),
        serviceRequests: (json['service_requests'] as List? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(ServiceRequestInfo.fromJson)
            .toList(),
      );

  final List<OrderInfo> pendingOrders;
  final List<ApprovalRequestInfo> approvalRequests;
  final List<ServiceRequestInfo> serviceRequests;

  bool get isQuiet =>
      pendingOrders.isEmpty &&
      approvalRequests.isEmpty &&
      serviceRequests.isEmpty;
}

/// One row of the tables grid.
class TableInfo {
  TableInfo({
    required this.id,
    required this.tableNumber,
    required this.status,
    this.reference,
    this.archivedAt,
    this.currentSessionId,
  });

  factory TableInfo.fromJson(Map<String, dynamic> json) {
    final session = json['current_session'];
    return TableInfo(
      id: asInt(json['id']),
      tableNumber: asInt(json['table_number']),
      status: asStringOrNull(json['status']) ?? 'closed',
      reference: asStringOrNull(json['reference']),
      archivedAt: asDateTime(json['archived_at']),
      currentSessionId:
          session is Map ? asInt(session['id']) : null,
    );
  }

  final int id;
  final int tableNumber;
  final String status;
  final String? reference;
  final DateTime? archivedAt;
  final int? currentSessionId;

  bool get isOpen => status == 'open';
  bool get isAwaitingApproval => status == 'pending_approval';
}

/// The bill for a table's current session.
class SessionBill {
  SessionBill({
    required this.orders,
    required this.total,
    required this.paid,
    required this.left,
    this.sessionId,
  });

  factory SessionBill.fromJson(Map<String, dynamic> json) {
    final totals = json['totals'];
    final session = json['session'];
    return SessionBill(
      orders: (json['orders'] as List? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(OrderInfo.fromJson)
          .toList(),
      total: totals is Map ? asDouble(totals['total']) : 0,
      paid: totals is Map ? asDouble(totals['paid']) : 0,
      left: totals is Map ? asDouble(totals['left']) : 0,
      sessionId: session is Map ? asInt(session['id']) : null,
    );
  }

  final List<OrderInfo> orders;
  final double total;
  final double paid;
  final double left;
  final int? sessionId;
}

/// A catalog product as live service needs it.
class ProductInfo {
  ProductInfo({
    required this.id,
    required this.name,
    required this.price,
    required this.isAvailable,
    this.description,
    this.categoryName,
    this.photo,
  });

  factory ProductInfo.fromJson(Map<String, dynamic> json) {
    final category = json['category'];
    return ProductInfo(
      id: asInt(json['id']),
      name: asStringOrNull(json['name']) ?? '',
      price: asDouble(json['price']),
      isAvailable: asBool(json['is_available'], true),
      description: asStringOrNull(json['description']),
      categoryName:
          category is Map ? asStringOrNull(category['name']) : null,
      photo: asStringOrNull(json['photo']),
    );
  }

  final int id;
  final String name;
  final double price;
  final bool isAvailable;
  final String? description;
  final String? categoryName;
  final String? photo;
}
