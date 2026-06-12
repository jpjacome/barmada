import 'package:barmada_ui/barmada_ui.dart';
import 'package:flutter/material.dart';

import 'session_gate.dart';

class BarmadaStaffApp extends StatelessWidget {
  const BarmadaStaffApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Barmada Staff',
      debugShowCheckedModeBanner: false,
      theme: buildBarmadaTheme(Brightness.light),
      darkTheme: buildBarmadaTheme(Brightness.dark),
      // Dark is the brand default: bars are dim and the aubergine-and-
      // sage identity comes from the web app's dark theme.
      themeMode: ThemeMode.dark,
      home: const SessionGate(),
    );
  }
}
