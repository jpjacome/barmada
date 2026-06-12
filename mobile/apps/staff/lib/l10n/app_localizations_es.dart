// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Spanish Castilian (`es`).
class AppLocalizationsEs extends AppLocalizations {
  AppLocalizationsEs([String locale = 'es']) : super(locale);

  @override
  String get appTitle => 'Barmada Staff';

  @override
  String get startupErrorTitle => 'Algo salió mal';

  @override
  String get retry => 'Reintentar';

  @override
  String get setupTagline => 'Maneja el bar desde tu bolsillo.';

  @override
  String get setupServerLabel => 'Tu servidor';

  @override
  String get setupServerHint => 'https://bar.example  ·  http://10.0.2.2:8000';

  @override
  String get setupEmulatorTip =>
      'Consejo: ¿probando contra `php artisan serve` en tu computadora? Usa http://10.0.2.2:8000 desde el emulador de Android, o la IP local de tu computadora desde un teléfono real.';

  @override
  String get setupEmptyUrl => 'Escribe la dirección de tu servidor.';

  @override
  String setupConnected(String serverName) {
    return 'Conectado a $serverName.';
  }

  @override
  String get connect => 'Conectar';

  @override
  String get emailLabel => 'Correo electrónico';

  @override
  String get passwordLabel => 'Contraseña';

  @override
  String get signIn => 'Iniciar sesión';

  @override
  String get useDifferentServer => 'Usar otro servidor';

  @override
  String get defaultDeviceName => 'Teléfono del personal';

  @override
  String get tabBoard => 'Tablero';

  @override
  String get tabTables => 'Mesas';

  @override
  String get tabProducts => 'Productos';

  @override
  String get tabAnalytics => 'Estadísticas';

  @override
  String get tabMore => 'Más';

  @override
  String get comingSoon => 'Muy pronto';

  @override
  String get analyticsPlaceholder =>
      'Ventas por día de negocio, productos más vendidos y operación de servicio — el siguiente bloque de la fase 1.';

  @override
  String currencyChip(String symbol) {
    return 'Moneda $symbol';
  }

  @override
  String get languageLabel => 'Idioma';

  @override
  String get languageSystem => 'Sistema';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageSpanish => 'Español';

  @override
  String get signOut => 'Cerrar sesión';

  @override
  String get forgetServer => 'Olvidar este servidor';

  @override
  String get aboutFooter =>
      'Barmada Staff · Estructura de la fase 1\nAutoalojado, cero comisiones, tuyo.';

  @override
  String get boardErrorTitle => 'No hay conexión con el servidor';

  @override
  String get boardErrorRetryHint => 'Desliza hacia abajo para reintentar.';

  @override
  String get boardQuietTitle => 'Todo tranquilo';

  @override
  String get boardQuietSubtitle =>
      'No hay pedidos pendientes, aprobaciones ni solicitudes de servicio ahora mismo.';

  @override
  String approveTableChip(Object table) {
    return 'Aprobar mesa $table';
  }

  @override
  String newGuestChip(Object table) {
    return 'Cliente nuevo · mesa $table';
  }

  @override
  String billChip(Object table) {
    return 'Cuenta · mesa $table';
  }

  @override
  String waiterChip(Object table) {
    return 'Mesero · mesa $table';
  }

  @override
  String get noPendingOrders => 'No hay pedidos pendientes';

  @override
  String orderTableTitle(Object table) {
    return 'Mesa $table';
  }

  @override
  String get delivered => 'Entregado';

  @override
  String get statusOpen => 'Abierta';

  @override
  String get statusClosed => 'Cerrada';

  @override
  String get statusPendingApproval => 'Pendiente de aprobación';

  @override
  String get noTablesYet => 'Aún no hay mesas';

  @override
  String get noTablesSubtitle =>
      'Crea tus mesas en el panel web; aparecerán aquí al instante.';

  @override
  String openedAtTime(String time) {
    return 'Abierta a las $time';
  }

  @override
  String get totalLabel => 'Total';

  @override
  String get paidLabel => 'Pagado';

  @override
  String get remainingLabel => 'Pendiente';

  @override
  String get openTableAction => 'Abrir mesa';

  @override
  String get approveTableAction => 'Aprobar mesa';

  @override
  String get settleAllAction => 'Marcar todo pagado';

  @override
  String get closeTableAction => 'Cerrar mesa';

  @override
  String closeTableTitle(Object table) {
    return '¿Cerrar la mesa $table?';
  }

  @override
  String closeUnpaidWarning(String amount) {
    return 'Aún quedan $amount sin pagar en esta cuenta.';
  }

  @override
  String get closeAndSettle => 'Cobrar y cerrar';

  @override
  String get closeWithoutSettling => 'Cerrar igualmente';

  @override
  String get cancel => 'Cancelar';

  @override
  String tableOpenedSnack(Object table) {
    return 'La mesa $table está abierta.';
  }

  @override
  String tableClosedSnack(Object table) {
    return 'Mesa $table cerrada.';
  }

  @override
  String get sessionNoOrders => 'Aún no hay pedidos en esta sesión.';

  @override
  String get tableClosedEmptyTitle => 'Esta mesa está cerrada';

  @override
  String get tableClosedEmptySubtitle =>
      'Ábrela para iniciar una nueva sesión.';

  @override
  String get orderStatusPending => 'Pendiente';

  @override
  String get orderStatusDelivered => 'Entregado';

  @override
  String get orderStatusCancelled => 'Cancelado';

  @override
  String get billRequestedChip => 'Cuenta solicitada';

  @override
  String get waiterCalledChip => 'Mesero llamado';

  @override
  String get soldOut => 'Agotado';

  @override
  String get searchProducts => 'Buscar productos…';

  @override
  String get categoryOthers => 'Otros';

  @override
  String get noProductsYet => 'Aún no hay productos';

  @override
  String get noProductsSubtitle => 'Crea tu catálogo en el panel web.';

  @override
  String get noSearchMatches => 'Ningún producto coincide con tu búsqueda.';

  @override
  String productMarkedSoldOut(String name) {
    return '$name marcado como agotado.';
  }

  @override
  String productBackOnSale(String name) {
    return '$name vuelve a estar a la venta.';
  }

  @override
  String get errorNetwork =>
      'No se pudo conectar con el servidor. Revisa la dirección y tu conexión.';

  @override
  String errorRequestFailed(int status) {
    return 'La solicitud falló ($status).';
  }

  @override
  String get errorSessionExpired =>
      'Tu sesión ha expirado. Inicia sesión de nuevo.';

  @override
  String get errorNotBarmadaServer =>
      'Esa dirección respondió, pero no parece un servidor de Barmada.';

  @override
  String get errorAddServerFirst => 'Primero agrega un servidor.';

  @override
  String get errorNotSignedIn => 'No has iniciado sesión.';
}
