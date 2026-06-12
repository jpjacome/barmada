import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_es.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
      : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('es')
  ];

  /// App name shown by the OS task switcher.
  ///
  /// In en, this message translates to:
  /// **'Barmada Staff'**
  String get appTitle;

  /// Title when the app fails to restore its session at startup.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong'**
  String get startupErrorTitle;

  /// Generic retry button.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get retry;

  /// Tagline under the wordmark on the first-run screen.
  ///
  /// In en, this message translates to:
  /// **'Run the bar from your pocket.'**
  String get setupTagline;

  /// Label above the server address field. Self-hosting is the product: the server belongs to the venue.
  ///
  /// In en, this message translates to:
  /// **'Your server'**
  String get setupServerLabel;

  /// Example addresses inside the server field. URLs are locale-independent; keep as-is unless the examples should differ.
  ///
  /// In en, this message translates to:
  /// **'https://bar.example  ·  http://10.0.2.2:8000'**
  String get setupServerHint;

  /// Developer-facing hint under the server field. `php artisan serve` is a command and stays untranslated.
  ///
  /// In en, this message translates to:
  /// **'Tip: testing against `php artisan serve` on your laptop? Use http://10.0.2.2:8000 from the Android emulator, or your laptop\'s LAN IP from a real phone.'**
  String get setupEmulatorTip;

  /// Validation message when the server field is submitted empty.
  ///
  /// In en, this message translates to:
  /// **'Enter your server address.'**
  String get setupEmptyUrl;

  /// Snackbar after a server responds to /meta.
  ///
  /// In en, this message translates to:
  /// **'Connected to {serverName}.'**
  String setupConnected(String serverName);

  /// Button that validates and saves the server address.
  ///
  /// In en, this message translates to:
  /// **'Connect'**
  String get connect;

  /// Login email field label.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get emailLabel;

  /// Login password field label.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get passwordLabel;

  /// Login submit button.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// Link on the login screen back to the add-server flow.
  ///
  /// In en, this message translates to:
  /// **'Use a different server'**
  String get useDifferentServer;

  /// Default device label sent to the server when creating the API token; venues see it in their device list.
  ///
  /// In en, this message translates to:
  /// **'Staff phone'**
  String get defaultDeviceName;

  /// Bottom tab: the live orders board.
  ///
  /// In en, this message translates to:
  /// **'Board'**
  String get tabBoard;

  /// Bottom tab: tables.
  ///
  /// In en, this message translates to:
  /// **'Tables'**
  String get tabTables;

  /// Bottom tab: product catalog.
  ///
  /// In en, this message translates to:
  /// **'Products'**
  String get tabProducts;

  /// Bottom tab: sales analytics.
  ///
  /// In en, this message translates to:
  /// **'Analytics'**
  String get tabAnalytics;

  /// Bottom tab and title of the account/settings page.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get tabMore;

  /// Title on placeholder pages for not-yet-built features.
  ///
  /// In en, this message translates to:
  /// **'Coming soon'**
  String get comingSoon;

  /// Roadmap note on the Products placeholder page. 'Sold out' matches the guest menu's wording.
  ///
  /// In en, this message translates to:
  /// **'Catalog with one-tap sold-out toggles — next chunk of Phase 1.'**
  String get productsPlaceholder;

  /// Roadmap note on the Analytics placeholder page.
  ///
  /// In en, this message translates to:
  /// **'Business-day sales, top products and service ops — next chunk of Phase 1.'**
  String get analyticsPlaceholder;

  /// Chip on the More page showing the venue's currency symbol.
  ///
  /// In en, this message translates to:
  /// **'Currency {symbol}'**
  String currencyChip(String symbol);

  /// Title of the language picker on the More page.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get languageLabel;

  /// Language option: follow the device language.
  ///
  /// In en, this message translates to:
  /// **'System'**
  String get languageSystem;

  /// Language option endonym — keep 'English' in every translation.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get languageEnglish;

  /// Language option endonym — keep 'Español' in every translation.
  ///
  /// In en, this message translates to:
  /// **'Español'**
  String get languageSpanish;

  /// Button ending the staff session.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// Button removing the saved server (back to first-run).
  ///
  /// In en, this message translates to:
  /// **'Forget this server'**
  String get forgetServer;

  /// Footer on the More page; second line is the product credo.
  ///
  /// In en, this message translates to:
  /// **'Barmada Staff · Phase 1 scaffold\nSelf-hosted, zero commission, yours.'**
  String get aboutFooter;

  /// Title when the board poll fails.
  ///
  /// In en, this message translates to:
  /// **'Cannot reach the server'**
  String get boardErrorTitle;

  /// Hint under the board error explaining pull-to-refresh.
  ///
  /// In en, this message translates to:
  /// **'Pull down to retry.'**
  String get boardErrorRetryHint;

  /// Empty-board title when nothing needs attention.
  ///
  /// In en, this message translates to:
  /// **'All quiet'**
  String get boardQuietTitle;

  /// Empty-board subtitle.
  ///
  /// In en, this message translates to:
  /// **'No pending orders, approvals or service requests right now.'**
  String get boardQuietSubtitle;

  /// Attention chip: a table's first guest is waiting for approval. Tapping approves.
  ///
  /// In en, this message translates to:
  /// **'Approve table {table}'**
  String approveTableChip(Object table);

  /// Attention chip: an additional guest wants to join an open table. Tapping approves.
  ///
  /// In en, this message translates to:
  /// **'New guest · table {table}'**
  String newGuestChip(Object table);

  /// Attention chip: a guest asked for the bill. Tapping marks it handled.
  ///
  /// In en, this message translates to:
  /// **'Bill · table {table}'**
  String billChip(Object table);

  /// Attention chip: a guest called a waiter. Tapping marks it handled.
  ///
  /// In en, this message translates to:
  /// **'Waiter · table {table}'**
  String waiterChip(Object table);

  /// Shown when alerts exist but no orders are pending.
  ///
  /// In en, this message translates to:
  /// **'No pending orders'**
  String get noPendingOrders;

  /// Order card title: which table ordered.
  ///
  /// In en, this message translates to:
  /// **'Table {table}'**
  String orderTableTitle(Object table);

  /// Order card action marking the order as delivered. The guest web app translates the matching status as 'Entregado'.
  ///
  /// In en, this message translates to:
  /// **'Delivered'**
  String get delivered;

  /// Table state chip: the table has a live session. 'Table' is feminine in Spanish (mesa).
  ///
  /// In en, this message translates to:
  /// **'Open'**
  String get statusOpen;

  /// Table state chip: no session.
  ///
  /// In en, this message translates to:
  /// **'Closed'**
  String get statusClosed;

  /// Table state chip: a first guest scanned and waits for staff approval.
  ///
  /// In en, this message translates to:
  /// **'Pending approval'**
  String get statusPendingApproval;

  /// Empty tables grid title.
  ///
  /// In en, this message translates to:
  /// **'No tables yet'**
  String get noTablesYet;

  /// Empty tables grid hint — table creation is web-only for now.
  ///
  /// In en, this message translates to:
  /// **'Create your tables in the web dashboard; they appear here instantly.'**
  String get noTablesSubtitle;

  /// When the current session started. {time} arrives pre-formatted for the locale.
  ///
  /// In en, this message translates to:
  /// **'Opened at {time}'**
  String openedAtTime(String time);

  /// Bill column: session total. The guest web app uses 'Total' too.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get totalLabel;

  /// Bill column: amount already ticked as paid. Guest web app: 'Pagado'.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get paidLabel;

  /// Bill column: amount left to pay. Guest web app: 'Pendiente'.
  ///
  /// In en, this message translates to:
  /// **'Remaining'**
  String get remainingLabel;

  /// Button starting a new session on a closed table.
  ///
  /// In en, this message translates to:
  /// **'Open table'**
  String get openTableAction;

  /// Button approving the waiting first guest (also adopts their device).
  ///
  /// In en, this message translates to:
  /// **'Approve table'**
  String get approveTableAction;

  /// Button ticking every remaining item on the session as paid.
  ///
  /// In en, this message translates to:
  /// **'Mark all paid'**
  String get settleAllAction;

  /// Button ending the session.
  ///
  /// In en, this message translates to:
  /// **'Close table'**
  String get closeTableAction;

  /// Confirm dialog title before closing a table with an unpaid balance.
  ///
  /// In en, this message translates to:
  /// **'Close table {table}?'**
  String closeTableTitle(Object table);

  /// Dialog body. {amount} arrives formatted with the venue currency symbol.
  ///
  /// In en, this message translates to:
  /// **'{amount} is still unpaid on this bill.'**
  String closeUnpaidWarning(String amount);

  /// Dialog action: mark the remaining balance paid, then close (the web's pay-and-close).
  ///
  /// In en, this message translates to:
  /// **'Settle & close'**
  String get closeAndSettle;

  /// Dialog action: close leaving the balance unpaid.
  ///
  /// In en, this message translates to:
  /// **'Close anyway'**
  String get closeWithoutSettling;

  /// Generic dialog dismiss. Guest web app: 'Cancelar'.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// Snackbar after opening a table.
  ///
  /// In en, this message translates to:
  /// **'Table {table} is open.'**
  String tableOpenedSnack(Object table);

  /// Snackbar after closing a table.
  ///
  /// In en, this message translates to:
  /// **'Table {table} closed.'**
  String tableClosedSnack(Object table);

  /// Open session with an empty bill. The guest web app translates this exact line as 'Aún no hay pedidos en esta sesión.'
  ///
  /// In en, this message translates to:
  /// **'No orders yet this session.'**
  String get sessionNoOrders;

  /// Session screen body when the table has no session.
  ///
  /// In en, this message translates to:
  /// **'This table is closed'**
  String get tableClosedEmptyTitle;

  /// Hint under the closed-table message.
  ///
  /// In en, this message translates to:
  /// **'Open it to start a new session.'**
  String get tableClosedEmptySubtitle;

  /// Order state chip on the bill. Order is masculine in Spanish (pedido).
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get orderStatusPending;

  /// Order state chip on the bill — matches the guest web app's 'Entregado'.
  ///
  /// In en, this message translates to:
  /// **'Delivered'**
  String get orderStatusDelivered;

  /// Order state chip on the bill.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get orderStatusCancelled;

  /// Session-screen chip: a guest asked for the bill. Tapping marks it handled.
  ///
  /// In en, this message translates to:
  /// **'Bill requested'**
  String get billRequestedChip;

  /// Session-screen chip: a guest called a waiter. Tapping marks it handled.
  ///
  /// In en, this message translates to:
  /// **'Waiter called'**
  String get waiterCalledChip;

  /// Transport failure: DNS, refused connection, timeout, no network.
  ///
  /// In en, this message translates to:
  /// **'Could not reach the server. Check the address and your connection.'**
  String get errorNetwork;

  /// HTTP error without a server-provided message.
  ///
  /// In en, this message translates to:
  /// **'Request failed ({status}).'**
  String errorRequestFailed(int status);

  /// The API token is no longer valid.
  ///
  /// In en, this message translates to:
  /// **'Your session has expired. Please sign in again.'**
  String get errorSessionExpired;

  /// The address answered /meta but did not identify as Barmada.
  ///
  /// In en, this message translates to:
  /// **'That address responded, but it does not look like a Barmada server.'**
  String get errorNotBarmadaServer;

  /// An action needed a configured server but none exists.
  ///
  /// In en, this message translates to:
  /// **'Add a server first.'**
  String get errorAddServerFirst;

  /// An action needed an authed session but nobody is signed in.
  ///
  /// In en, this message translates to:
  /// **'Not signed in.'**
  String get errorNotSignedIn;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'es'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'es':
      return AppLocalizationsEs();
  }

  throw FlutterError(
      'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
      'an issue with the localizations generation tool. Please file an issue '
      'on GitHub with a reproducible sample app and the gen-l10n configuration '
      'that was used.');
}
