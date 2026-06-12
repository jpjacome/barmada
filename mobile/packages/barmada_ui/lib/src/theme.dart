import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// The raw color tokens, verbatim from the web app's CSS variables.
abstract final class BarmadaColors {
  // Dark theme (the brand identity).
  static const aubergine = Color(0xFF1B1223); // --color-secondary (dark)
  static const sage = Color(0xFF73AB84); // --color-primary (dark)
  static const celadon = Color(0xFF99D19C); // --color-success (dark)
  static const teal = Color(0xFF79C7C5); // --color-warning (dark, = info)
  static const raspberry = Color(0xFFD1074A); // --color-danger (dark)

  // Light theme.
  static const slateText = Color(0xFF1F2937); // --color-accents (light)
  static const tealDark = Color(0xFF57B7B5); // readable teal on white
  static const slateBlue = Color(0xFF8CA3C6); // --color-success (light)
  static const softRed = Color(0xFFFA8888); // --color-danger (light)
}

/// Status semantics that don't fit Material's scheme slots.
/// Pending deliberately uses the danger hue: pending orders are urgent.
@immutable
class BarmadaStatusColors extends ThemeExtension<BarmadaStatusColors> {
  const BarmadaStatusColors({
    required this.paid,
    required this.pending,
    required this.info,
  });

  final Color paid;
  final Color pending;
  final Color info;

  static const dark = BarmadaStatusColors(
    paid: BarmadaColors.celadon,
    pending: BarmadaColors.raspberry,
    info: BarmadaColors.teal,
  );

  static const light = BarmadaStatusColors(
    paid: BarmadaColors.slateBlue,
    pending: BarmadaColors.softRed,
    info: BarmadaColors.tealDark,
  );

  @override
  BarmadaStatusColors copyWith({Color? paid, Color? pending, Color? info}) =>
      BarmadaStatusColors(
        paid: paid ?? this.paid,
        pending: pending ?? this.pending,
        info: info ?? this.info,
      );

  @override
  BarmadaStatusColors lerp(BarmadaStatusColors? other, double t) {
    if (other == null) return this;
    return BarmadaStatusColors(
      paid: Color.lerp(paid, other.paid, t) ?? paid,
      pending: Color.lerp(pending, other.pending, t) ?? pending,
      info: Color.lerp(info, other.info, t) ?? info,
    );
  }
}

extension BarmadaThemeX on ThemeData {
  BarmadaStatusColors get statusColors =>
      extension<BarmadaStatusColors>() ?? BarmadaStatusColors.dark;
}

ColorScheme _darkScheme() => const ColorScheme(
      brightness: Brightness.dark,
      primary: BarmadaColors.sage,
      onPrimary: BarmadaColors.aubergine,
      secondary: BarmadaColors.teal,
      onSecondary: BarmadaColors.aubergine,
      tertiary: BarmadaColors.celadon,
      onTertiary: BarmadaColors.aubergine,
      error: BarmadaColors.raspberry,
      onError: Colors.white,
      surface: BarmadaColors.aubergine,
      onSurface: Colors.white,
    );

ColorScheme _lightScheme() => const ColorScheme(
      brightness: Brightness.light,
      primary: BarmadaColors.tealDark,
      onPrimary: Colors.white,
      secondary: BarmadaColors.teal,
      onSecondary: BarmadaColors.slateText,
      tertiary: BarmadaColors.slateBlue,
      onTertiary: Colors.white,
      error: BarmadaColors.softRed,
      onError: Colors.white,
      surface: Colors.white,
      onSurface: BarmadaColors.slateText,
    );

/// Builds the Barmada theme. Dark is the default identity: bars are dim,
/// and the aubergine-and-sage look IS the brand.
ThemeData buildBarmadaTheme(Brightness brightness) {
  final scheme = brightness == Brightness.dark ? _darkScheme() : _lightScheme();
  final status = brightness == Brightness.dark
      ? BarmadaStatusColors.dark
      : BarmadaStatusColors.light;

  final base = ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: scheme.surface,
  );

  // Crimson Text for display (screen titles, big numerals), Inter Tight
  // for everything else — the web app's exact pairing.
  final textTheme = GoogleFonts.interTightTextTheme(base.textTheme).copyWith(
    displayLarge: GoogleFonts.crimsonText(
        textStyle: base.textTheme.displayLarge, color: scheme.onSurface),
    displayMedium: GoogleFonts.crimsonText(
        textStyle: base.textTheme.displayMedium, color: scheme.onSurface),
    displaySmall: GoogleFonts.crimsonText(
        textStyle: base.textTheme.displaySmall, color: scheme.onSurface),
    headlineMedium: GoogleFonts.crimsonText(
        textStyle: base.textTheme.headlineMedium, color: scheme.onSurface),
    headlineSmall: GoogleFonts.crimsonText(
        textStyle: base.textTheme.headlineSmall, color: scheme.onSurface),
  );

  return base.copyWith(
    textTheme: textTheme,
    extensions: [status],
    appBarTheme: AppBarTheme(
      backgroundColor: scheme.surface,
      foregroundColor: scheme.onSurface,
      elevation: 0,
      centerTitle: false,
      titleTextStyle: GoogleFonts.crimsonText(
        fontSize: 28,
        fontWeight: FontWeight.w600,
        color: scheme.onSurface,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: scheme.surface,
      indicatorColor: scheme.primary.withValues(alpha: 0.18),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected)
              ? scheme.primary
              : scheme.onSurface.withValues(alpha: 0.6),
        ),
      ),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 12,
          color: states.contains(WidgetState.selected)
              ? scheme.primary
              : scheme.onSurface.withValues(alpha: 0.6),
        ),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: scheme.primary,
        foregroundColor: scheme.onPrimary,
        minimumSize: const Size.fromHeight(48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: scheme.primary,
        side: BorderSide(color: scheme.primary),
        minimumSize: const Size(48, 44),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: scheme.primary.withValues(alpha: 0.5)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: scheme.primary.withValues(alpha: 0.5)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: scheme.primary, width: 2),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor:
          brightness == Brightness.dark ? const Color(0xFF2A1F33) : null,
      contentTextStyle: TextStyle(color: scheme.onSurface),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: scheme.primary.withValues(alpha: 0.4)),
      ),
    ),
  );
}
