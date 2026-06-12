import 'package:barmada_api/barmada_api.dart';

import 'app_localizations.dart';

/// Renders any error in the user's language.
///
/// Client-generated [ApiException]s carry an [ApiErrorCode] and map to a
/// localized string here. Server-provided messages (validation rejections,
/// domain errors) carry no code and pass through verbatim — the server
/// speaks for the venue, not for the device's language setting.
extension ApiErrorL10n on AppLocalizations {
  String errorMessage(Object error) {
    if (error is ApiException) {
      return switch (error.code) {
        ApiErrorCode.network => errorNetwork,
        ApiErrorCode.requestFailed => errorRequestFailed(error.statusCode ?? 0),
        ApiErrorCode.sessionExpired => errorSessionExpired,
        ApiErrorCode.notBarmadaServer => errorNotBarmadaServer,
        ApiErrorCode.noActiveServer => errorAddServerFirst,
        ApiErrorCode.notSignedIn => errorNotSignedIn,
        null => error.message,
      };
    }
    return '$error';
  }
}
