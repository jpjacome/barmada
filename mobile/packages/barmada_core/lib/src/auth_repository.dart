import 'package:barmada_api/barmada_api.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'server_registry.dart';

/// Stores API tokens (one per server) in the platform keystore and
/// performs the login/logout round-trips.
class AuthRepository {
  AuthRepository({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  String _tokenKey(ServerInfo server) => 'barmada.token.${server.id}';

  Future<String?> tokenFor(ServerInfo server) =>
      _storage.read(key: _tokenKey(server));

  Future<AuthResult> signIn({
    required ServerInfo server,
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final client = BarmadaClient(baseUrl: server.url);
    final result = await client.login(
      email: email,
      password: password,
      deviceName: deviceName,
    );
    await _storage.write(key: _tokenKey(server), value: result.token);
    return result;
  }

  /// Revokes the token server-side (best effort) and forgets it locally.
  Future<void> signOut(ServerInfo server) async {
    final token = await tokenFor(server);
    if (token != null && token.isNotEmpty) {
      try {
        await BarmadaClient(baseUrl: server.url, token: token).logout();
      } on ApiException {
        // Token may already be dead; local cleanup still applies.
      }
    }
    await _storage.delete(key: _tokenKey(server));
  }

  Future<void> forgetToken(ServerInfo server) =>
      _storage.delete(key: _tokenKey(server));
}
