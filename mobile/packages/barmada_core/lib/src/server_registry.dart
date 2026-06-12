import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// A Barmada server the user has connected to.
class ServerInfo {
  const ServerInfo({required this.url, required this.name});

  factory ServerInfo.fromJson(Map<String, dynamic> json) => ServerInfo(
        url: json['url'] as String,
        name: json['name'] as String? ?? 'Barmada',
      );

  /// Normalized root URL (no trailing slash, no /api/v1).
  final String url;

  /// Display name reported by the server's /meta endpoint.
  final String name;

  /// Stable identifier for keying stored credentials.
  String get id => Uri.encodeComponent(url);

  Map<String, dynamic> toJson() => {'url': url, 'name': name};
}

/// Persists the servers this device knows and which one is active.
///
/// Self-hosting is the product: one staffer may work at two venues on
/// two different servers, so this is a list, not a single value.
class ServerRegistry {
  static const _kServers = 'barmada.servers';
  static const _kActive = 'barmada.active_server';

  Future<List<ServerInfo>> servers() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kServers);
    if (raw == null || raw.isEmpty) return const [];
    final list = jsonDecode(raw);
    if (list is! List) return const [];
    return list
        .whereType<Map<String, dynamic>>()
        .map(ServerInfo.fromJson)
        .toList();
  }

  Future<ServerInfo?> activeServer() async {
    final prefs = await SharedPreferences.getInstance();
    final id = prefs.getString(_kActive);
    if (id == null) return null;
    final all = await servers();
    for (final server in all) {
      if (server.id == id) return server;
    }
    return null;
  }

  Future<void> saveServer(ServerInfo server, {bool activate = true}) async {
    final prefs = await SharedPreferences.getInstance();
    final all = await servers();
    final next = [
      for (final existing in all)
        if (existing.id != server.id) existing,
      server,
    ];
    await prefs.setString(
        _kServers, jsonEncode([for (final s in next) s.toJson()]));
    if (activate) {
      await prefs.setString(_kActive, server.id);
    }
  }

  Future<void> removeServer(ServerInfo server) async {
    final prefs = await SharedPreferences.getInstance();
    final all = await servers();
    final next = [
      for (final existing in all)
        if (existing.id != server.id) existing,
    ];
    await prefs.setString(
        _kServers, jsonEncode([for (final s in next) s.toJson()]));
    if (prefs.getString(_kActive) == server.id) {
      await prefs.remove(_kActive);
    }
  }
}
