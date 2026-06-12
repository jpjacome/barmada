/// Typed client for the Barmada staff API (`/api/v1`).
///
/// Tracks the committed OpenAPI document in the server repo
/// (`openapi/api-v1.json`). Hand-written for now — revisit codegen once
/// the surface stabilises and CI exists.
library;

export 'src/barmada_client.dart';
export 'src/exceptions.dart';
export 'src/models.dart';
