import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Guards the translation catalogs against drift: every language must
/// define exactly the same messages with exactly the same placeholders.
/// A key added to app_en.arb without its app_es.arb twin fails here —
/// before it silently falls back to English on someone's phone.
void main() {
  const arbDir = 'lib/l10n';
  const template = 'app_en.arb';
  const translations = ['app_es.arb'];

  Map<String, dynamic> readArb(String name) {
    final file = File('$arbDir/$name');
    expect(file.existsSync(), isTrue, reason: '$name is missing');
    return jsonDecode(file.readAsStringSync()) as Map<String, dynamic>;
  }

  /// Message keys only — drops @@locale and @key metadata entries.
  Set<String> messageKeys(Map<String, dynamic> arb) =>
      arb.keys.where((k) => !k.startsWith('@')).toSet();

  /// Placeholder names used inside a message string, e.g. {table}.
  Set<String> placeholdersIn(String message) =>
      RegExp(r'\{(\w+)\}').allMatches(message).map((m) => m[1]!).toSet();

  test('every translation has exactly the template keys', () {
    final templateKeys = messageKeys(readArb(template));
    expect(templateKeys, isNotEmpty);

    for (final name in translations) {
      final keys = messageKeys(readArb(name));
      expect(keys.difference(templateKeys), isEmpty,
          reason: '$name has keys missing from $template');
      expect(templateKeys.difference(keys), isEmpty,
          reason: '$name is missing keys from $template');
    }
  });

  test('placeholders match per message across languages', () {
    final en = readArb(template);
    for (final name in translations) {
      final other = readArb(name);
      for (final key in messageKeys(en)) {
        final enValue = en[key];
        final otherValue = other[key];
        if (enValue is! String || otherValue is! String) continue;
        expect(placeholdersIn(otherValue), placeholdersIn(enValue),
            reason: 'placeholders for "$key" differ between '
                '$template and $name');
      }
    }
  });

  test('no translation is left empty', () {
    for (final name in [template, ...translations]) {
      final arb = readArb(name);
      for (final key in messageKeys(arb)) {
        final value = arb[key];
        expect(value is String && value.trim().isNotEmpty, isTrue,
            reason: '"$key" in $name is empty');
      }
    }
  });
}
