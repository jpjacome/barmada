import 'package:barmada_api/barmada_api.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'auth_repository.dart';
import 'server_registry.dart';

/// What the app should show right now.
sealed class AppSession {
  const AppSession();
}

/// No server configured yet: show the add-server flow.
class SetupRequired extends AppSession {
  const SetupRequired();
}

/// A server is configured but nobody is signed in.
class LoggedOut extends AppSession {
  const LoggedOut(this.server);
  final ServerInfo server;
}

/// Signed in and ready: [client] carries the bearer token.
class Authed extends AppSession {
  const Authed(
      {required this.server, required this.user, required this.client});
  final ServerInfo server;
  final ApiUser user;
  final BarmadaClient client;
}

final serverRegistryProvider =
    Provider<ServerRegistry>((ref) => ServerRegistry());
final authRepositoryProvider =
    Provider<AuthRepository>((ref) => AuthRepository());

final sessionControllerProvider =
    AsyncNotifierProvider<SessionController, AppSession>(SessionController.new);

/// The session state machine:
/// SetupRequired -> LoggedOut(server) -> Authed(server, user).
class SessionController extends AsyncNotifier<AppSession> {
  ServerRegistry get _registry => ref.read(serverRegistryProvider);
  AuthRepository get _auth => ref.read(authRepositoryProvider);

  @override
  Future<AppSession> build() async {
    final server = await _registry.activeServer();
    if (server == null) return const SetupRequired();

    final token = await _auth.tokenFor(server);
    if (token == null || token.isEmpty) return LoggedOut(server);

    final client = BarmadaClient(baseUrl: server.url, token: token);
    try {
      final user = await client.currentUser();
      return Authed(server: server, user: user, client: client);
    } on UnauthenticatedException {
      await _auth.forgetToken(server);
      return LoggedOut(server);
    } on ApiException {
      // Server unreachable right now: optimistically keep the session so
      // the app can open offline-ish; API calls will surface errors.
      return Authed(
        server: server,
        user: ApiUser(
          id: 0,
          name: server.name,
          email: '',
          isAdmin: false,
          isEditor: false,
          isStaff: true,
        ),
        client: client,
      );
    }
  }

  /// Validates a server URL via /meta and stores it as active.
  Future<ServerMeta> addServer(String url) async {
    final normalized = BarmadaClient.normalizeBaseUrl(url);
    final meta = await BarmadaClient(baseUrl: normalized).meta();
    if (!meta.isBarmada) {
      throw ApiException(
          'That address responded, but it does not look like a Barmada server.',
          code: ApiErrorCode.notBarmadaServer);
    }
    await _registry.saveServer(ServerInfo(url: normalized, name: meta.name));
    state = AsyncData(LoggedOut(ServerInfo(url: normalized, name: meta.name)));
    return meta;
  }

  Future<void> signIn({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final current = state.value;
    final server = switch (current) {
      LoggedOut(:final server) => server,
      Authed(:final server) => server,
      _ => await _registry.activeServer(),
    };
    if (server == null) {
      throw ApiException('Add a server first.',
          code: ApiErrorCode.noActiveServer);
    }
    final result = await _auth.signIn(
      server: server,
      email: email,
      password: password,
      deviceName: deviceName,
    );
    state = AsyncData(Authed(
      server: server,
      user: result.user,
      client: BarmadaClient(baseUrl: server.url, token: result.token),
    ));
  }

  Future<void> signOut() async {
    final current = state.value;
    if (current is Authed) {
      await _auth.signOut(current.server);
      state = AsyncData(LoggedOut(current.server));
    }
  }

  /// Drop the active server entirely (back to setup).
  Future<void> forgetServer() async {
    final current = state.value;
    final server = switch (current) {
      LoggedOut(:final server) => server,
      Authed(:final server) => server,
      _ => null,
    };
    if (server != null) {
      await _auth.forgetToken(server);
      await _registry.removeServer(server);
    }
    state = const AsyncData(SetupRequired());
  }
}
