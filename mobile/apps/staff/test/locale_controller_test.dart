import 'package:barmada_staff/features/settings/locale_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('system is the default and forces no locale', () async {
    SharedPreferences.setMockInitialValues({});
    expect(await loadLocaleSetting(), LocaleSetting.system);
    expect(LocaleSetting.system.locale, isNull);
  });

  test('a saved choice is restored at startup', () async {
    SharedPreferences.setMockInitialValues({'barmada.locale': 'es'});
    expect(await loadLocaleSetting(), LocaleSetting.spanish);
    expect(LocaleSetting.spanish.locale, const Locale('es'));
  });

  test('unknown stored values fall back to system', () async {
    SharedPreferences.setMockInitialValues({'barmada.locale': 'klingon'});
    expect(await loadLocaleSetting(), LocaleSetting.system);
  });

  test('setting a language updates state and persists', () async {
    SharedPreferences.setMockInitialValues({});
    final container = ProviderContainer();
    addTearDown(container.dispose);

    expect(container.read(localeControllerProvider), LocaleSetting.system);

    await container
        .read(localeControllerProvider.notifier)
        .set(LocaleSetting.spanish);

    expect(container.read(localeControllerProvider), LocaleSetting.spanish);
    expect(container.read(localeControllerProvider).locale, const Locale('es'));

    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getString('barmada.locale'), 'es');

    // And back round-trips through the same persistence.
    expect(await loadLocaleSetting(), LocaleSetting.spanish);
  });
}
