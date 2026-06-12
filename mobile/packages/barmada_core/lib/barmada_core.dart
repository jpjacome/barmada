/// Session plumbing shared by the Barmada apps: the saved-server
/// registry (self-hosted means many servers), token storage, and the
/// Riverpod session state machine the UI gates on.
library;

export 'src/app_session.dart';
export 'src/auth_repository.dart';
export 'src/server_registry.dart';
