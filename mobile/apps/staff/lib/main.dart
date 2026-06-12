import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'features/settings/locale_controller.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Read the saved language before the first frame so the app never
  // flashes one language and settles into another.
  final localeSetting = await loadLocaleSetting();
  runApp(ProviderScope(
    overrides: [
      initialLocaleSettingProvider.overrideWithValue(localeSetting),
    ],
    child: const BarmadaStaffApp(),
  ));
}
