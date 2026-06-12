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
  String get statusOpen => 'Open';

  @override
  String get statusClosed => 'Closed';

  @override
  String get statusPendingApproval => 'Pending approval';

  @override
  String get noTablesYet => 'No tables yet';

  @override
  String get noTablesSubtitle =>
      'Create your tables in the web dashboard; they appear here instantly.';

  @override
  String openedAtTime(String time) {
    return 'Opened at $time';
  }

  @override
  String get totalLabel => 'Total';

  @override
  String get paidLabel => 'Paid';

  @override
  String get remainingLabel => 'Remaining';

  @override
  String get openTableAction => 'Open table';

  @override
  String get approveTableAction => 'Approve table';

  @override
  String get settleAllAction => 'Mark all paid';

  @override
  String get closeTableAction => 'Close table';

  @override
  String closeTableTitle(Object table) {
    return 'Close table $table?';
  }

  @override
  String closeUnpaidWarning(String amount) {
    return '$amount is still unpaid on this bill.';
  }

  @override
  String get closeAndSettle => 'Settle & close';

  @override
  String get closeWithoutSettling => 'Close anyway';

  @override
  String get cancel => 'Cancel';

  @override
  String tableOpenedSnack(Object table) {
    return 'Table $table is open.';
  }

  @override
  String tableClosedSnack(Object table) {
    return 'Table $table closed.';
  }

  @override
  String get sessionNoOrders => 'No orders yet this session.';

  @override
  String get tableClosedEmptyTitle => 'This table is closed';

  @override
  String get tableClosedEmptySubtitle => 'Open it to start a new session.';

  @override
  String get orderStatusPending => 'Pending';

  @override
  String get orderStatusDelivered => 'Delivered';

  @override
  String get orderStatusCancelled => 'Cancelled';

  @override
  String get billRequestedChip => 'Bill requested';

  @override
  String get waiterCalledChip => 'Waiter called';

  @override
  String get newOrderAction => 'New order';

  @override
  String newOrderSheetTitle(Object table) {
    return 'New order · table $table';
  }

  @override
  String itemsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count items',
      one: '1 item',
      zero: 'No items',
    );
    return '$_temp0';
  }

  @override
  String get reviewOrder => 'Review order';

  @override
  String get confirmOrder => 'Confirm order';

  @override
  String get orderNoteHint => 'Order note (optional)';

  @override
  String orderPlacedSnack(Object table) {
    return 'Order placed for table $table.';
  }

  @override
  String get soldOut => 'Sold out';

  @override
  String get searchProducts => 'Search products…';

  @override
  String get categoryOthers => 'Others';

  @override
  String get noProductsYet => 'No products yet';

  @override
  String get noProductsSubtitle => 'Create your catalog in the web dashboard.';

  @override
  String get noSearchMatches => 'No products match your search.';

  @override
  String productMarkedSoldOut(String name) {
    return '$name marked sold out.';
  }

  @override
  String productBackOnSale(String name) {
    return '$name is back on sale.';
  }

  @override
  String get analyticsOwnerOnlyTitle => 'Owner access only';

  @override
  String get analyticsOwnerOnlySubtitle =>
      'Analytics are available to the venue owner\'s account.';

  @override
  String get rangeToday => 'Today';

  @override
  String get range7days => '7 days';

  @override
  String get range30days => '30 days';

  @override
  String get totalSalesLabel => 'Total sales';

  @override
  String get ordersLabel => 'Orders';

  @override
  String get avgOrderLabel => 'Avg order';

  @override
  String get peakHourLabel => 'Peak hour';

  @override
  String get topProductLabel => 'Top product';

  @override
  String get analyticsNoSales => 'No sales in this period yet.';

  @override
  String get ordersByHourTitle => 'Orders by hour';

  @override
  String get topProductsTitle => 'Top products';

  @override
  String get byCategoryTitle => 'By category';

  @override
  String get serviceTitle => 'Service';

  @override
  String get sessionsLabel => 'Sessions';

  @override
  String get avgSessionLabel => 'Avg session';

  @override
  String get mostUsedTableLabel => 'Busiest table';

  @override
  String get qrScansLabel => 'QR scans';

  @override
  String get qrConversionLabel => 'QR → order';

  @override
  String get turnoverLabel => 'Turnover';

  @override
  String get whoTookOrdersTitle => 'Who took the orders';

  @override
  String get guestsQrLabel => 'Guests (QR)';

  @override
  String minutesShort(String value) {
    return '$value min';
  }

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
