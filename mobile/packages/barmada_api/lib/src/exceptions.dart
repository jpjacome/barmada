/// Where a client-side failure came from, so app code can translate it.
///
/// Server-provided messages (validation rejections, domain errors) carry
/// no code: they arrive ready to show and pass through verbatim. Codes
/// exist only for messages *this client* invents — those are the ones a
/// bilingual UI must render in the user's language. The [ApiException.message]
/// stays as an English fallback for logs and non-localized callers.
enum ApiErrorCode {
  /// Transport failure — DNS, refused connection, timeout, no network.
  network,

  /// The server answered with an HTTP error but no usable message.
  requestFailed,

  /// 401 — the token is missing, expired or revoked.
  sessionExpired,

  /// /meta answered but did not identify itself as a Barmada server.
  notBarmadaServer,

  /// An action needed an active server but none is configured.
  noActiveServer,

  /// An action needed an authed session but nobody is signed in.
  notSignedIn,
}

/// Errors surfaced by [BarmadaClient] calls.
///
/// Every failure becomes one of these — transport problems, validation
/// rejections (422 with a user-facing message) and auth failures — so
/// UI code has exactly one error vocabulary to deal with.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.code});

  /// User-presentable message (the server's `message` field when given,
  /// otherwise an English fallback for the [code]).
  final String message;

  /// HTTP status, when the failure came from a response.
  final int? statusCode;

  /// Set when the message was generated client-side; null when the text
  /// came from the server and should be shown as-is.
  final ApiErrorCode? code;

  bool get isValidation => statusCode == 422;
  bool get isNotFound => statusCode == 404;

  @override
  String toString() => 'ApiException($statusCode): $message';
}

/// The token is missing, expired or revoked — the session must end.
///
/// Always coded [ApiErrorCode.sessionExpired]: a raw 401 body ("Unauthenticated.")
/// is never friendlier than the app's own localized phrasing.
class UnauthenticatedException extends ApiException {
  UnauthenticatedException([String? message])
      : super(message ?? 'Your session has expired. Please sign in again.',
            statusCode: 401, code: ApiErrorCode.sessionExpired);
}
