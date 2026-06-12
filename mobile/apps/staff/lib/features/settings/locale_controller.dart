import 'dart:ui';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The user's language choice: follow the device, or force one.
///
/// This is a *device* preference, deliberately independent of the venue's
/// guest-language setting — that one governs what guests see on the QR web
/// flow; this one is whatever the staffer holding the phone reads best.
enum LocaleSetting {
  system(null),
  english(Locale('en')),
  spanish(Locale('es'));

  const LocaleSetting(this.locale);

  /// The locale to force, or null to follow the device.
  final Locale? locale;

  String get storedValue => switch (this) {
        LocaleSetting.system => 'system',
        LocaleSetting.english => 'en',
        LocaleSetting.spanish => 'es',
      };

  static LocaleSetting fromStored(String? value) => switch (value) {
        'en' => LocaleSetting.english,
        'es' => LocaleSetting.spanish,
        _ => LocaleSetting.system,
      };
}

const _kLocaleKey = 'barmada.locale';

/// Reads the persisted choice. Called once in main() before runApp so the
/// very first frame is already in the right language — no locale flicker.
Future<LocaleSetting> loadLocaleSetting() async {
  final prefs = await SharedPreferences.getInstance();
  return LocaleSetting.fromStored(prefs.getString(_kLocaleKey));
}

/// Seeded in main() with the value [loadLocaleSetting] read from disk.
final initialLocaleSettingProvider =
    Provider<LocaleSetting>((_) => LocaleSetting.system);

final localeControllerProvider =
    NotifierProvider<LocaleController, LocaleSetting>(LocaleController.new);

/// Holds the language choice and persists changes. The app widget watches
/// this, so picking a language re-renders everything live — no restart.
class LocaleController extends Notifier<LocaleSetting> {
  @override
  LocaleSetting build() => ref.watch(initialLocaleSettingProvider);

  Future<void> set(LocaleSetting setting) async {
    state = setting;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kLocaleKey, setting.storedValue);
  }
}
