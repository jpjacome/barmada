import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'features/settings/locale_controller.dart';
import 'l10n/app_localizations.dart';
import 'session_gate.dart';

class BarmadaStaffApp extends ConsumerWidget {
  const BarmadaStaffApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final localeSetting = ref.watch(localeControllerProvider);

    return MaterialApp(
      onGenerateTitle: (context) => AppLocalizations.of(context).appTitle,
      debugShowCheckedModeBanner: false,
      theme: buildBarmadaTheme(Brightness.light),
      darkTheme: buildBarmadaTheme(Brightness.dark),
      // Dark is the brand default: bars are dim and the aubergine-and-
      // sage identity comes from the web app's dark theme.
      themeMode: ThemeMode.dark,
      // null = follow the device; a value forces that language live.
      locale: localeSetting.locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: const SessionGate(),
    );
  }
}
