/// Errors surfaced by [BarmadaClient] calls.
///
/// Every failure becomes one of these — transport problems, validation
/// rejections (422 with a user-facing message) and auth failures — so
/// UI code has exactly one error vocabulary to deal with.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  /// User-presentable message (the server's `message` field when given).
  final String message;

  /// HTTP status, when the failure came from a response.
  final int? statusCode;

  bool get isValidation => statusCode == 422;
  bool get isNotFound => statusCode == 404;

  @override
  String toString() => 'ApiException($statusCode): $message';
}

/// The token is missing, expired or revoked — the session must end.
class UnauthenticatedException extends ApiException {
  UnauthenticatedException([String? message])
      : super(message ?? 'Your session has expired. Please sign in again.',
            statusCode: 401);
}
