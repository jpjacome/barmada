// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Barmada Staff';

  @override
  String get startupErrorTitle => 'Something went wrong';

  @override
  String get retry => 'Try again';

  @override
  String get setupTagline => 'Run the bar from your pocket.';

  @override
  String get setupServerLabel => 'Your server';

  @override
  String get setupServerHint => 'https://bar.example  ·  http://10.0.2.2:8000';

  @override
  String get setupEmulatorTip =>
      'Tip: testing against `php artisan serve` on your laptop? Use http://10.0.2.2:8000 from the Android emulator, or your laptop\'s LAN IP from a real phone.';

  @override
  String get setupEmptyUrl => 'Enter your server address.';

  @override
  String setupConnected(String serverName) {
    return 'Connected to $serverName.';
  }

  @override
  String get connect => 'Connect';

  @override
  String get emailLabel => 'Email';

  @override
  String get passwordLabel => 'Password';

  @override
  String get signIn => 'Sign in';

  @override
  String get useDifferentServer => 'Use a different server';

  @override
  String get defaultDeviceName => 'Staff phone';

  @override
  String get tabBoard => 'Board';

  @override
  String get tabTables => 'Tables';

  @override
  String get tabProducts => 'Products';

  @override
  String get tabAnalytics => 'Analytics';

  @override
  String get tabMore => 'More';

  @override
  String get comingSoon => 'Coming soon';

  @override
  String get tablesPlaceholder =>
      'Table grid with sessions, item payment ticking and bills — next chunk of Phase 1.';

  @override
  String get productsPlaceholder =>
      'Catalog with one-tap sold-out toggles — next chunk of Phase 1.';

  @override
  String get analyticsPlaceholder =>
      'Business-day sales, top products and service ops — next chunk of Phase 1.';

  @override
  String currencyChip(String symbol) {
    return 'Currency $symbol';
  }

  @override
  String get languageLabel => 'Language';

  @override
  String get languageSystem => 'System';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageSpanish => 'Español';

  @override
  String get signOut => 'Sign out';

  @override
  String get forgetServer => 'Forget this server';

  @override
  String get aboutFooter =>
      'Barmada Staff · Phase 1 scaffold\nSelf-hosted, zero commission, yours.';

  @override
  String get boardErrorTitle => 'Cannot reach the server';

  @override
  String get boardErrorRetryHint => 'Pull down to retry.';

  @override
  String get boardQuietTitle => 'All quiet';

  @override
  String get boardQuietSubtitle =>
      'No pending orders, approvals or service requests right now.';

  @override
  String approveTableChip(Object table) {
    return 'Approve table $table';
  }

  @override
  String newGuestChip(Object table) {
    return 'New guest · table $table';
  }

  @override
  String billChip(Object table) {
    return 'Bill · table $table';
  }

  @override
  String waiterChip(Object table) {
    return 'Waiter · table $table';
  }

  @override
  String get noPendingOrders => 'No pending orders';

  @override
  String orderTableTitle(Object table) {
    return 'Table $table';
  }

  @override
  String get delivered => 'Delivered';

  @override
  String get errorNetwork =>
      'Could not reach the server. Check the address and your connection.';

  @override
  String errorRequestFailed(int status) {
    return 'Request failed ($status).';
  }

  @override
  String get errorSessionExpired =>
      'Your session has expired. Please sign in again.';

  @override
  String get errorNotBarmadaServer =>
      'That address responded, but it does not look like a Barmada server.';

  @override
  String get errorAddServerFirst => 'Add a server first.';

  @override
  String get errorNotSignedIn => 'Not signed in.';
}
