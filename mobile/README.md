# Barmada Mobile

**Barmada Staff — the bar in your pocket.** Flutter monorepo for the Barmada mobile apps, consuming the `/api/v1` staff API of the [Barmada server](https://github.com/jpjacome/barmada).

Phase 1 scaffold (see the *Flutter Mobile Apps Proposal* and *Design System* docs): the workspace, the typed API client, session plumbing, the design system as code, and a working staff-app shell — add-server → login → live polling board with one-tap actions.

## Layout

```
barmada-mobile/                  Dart pub workspace (+ melos scripts)
├── packages/
│   ├── barmada_api/             Typed client for /api/v1 (pure Dart, dio)
│   ├── barmada_core/            Server registry, auth, Riverpod session
│   └── barmada_ui/              Design system: theme + BarmadaCard,
│                                StatusChip, OrderTimer, AlertChip…
└── apps/
    └── staff/                   Barmada Staff (Android-first)
```

- **barmada_api** tracks the server's committed OpenAPI document (`openapi/api-v1.json`). It is hand-written for now (typed methods + tolerant parsing, fully unit-tested against the real payload shapes); revisit generator-based codegen once CI exists.
- **barmada_core** owns the session state machine: `SetupRequired → LoggedOut(server) → Authed`. Servers are a *list* (self-hosting means a staffer can work at two venues); tokens live in the platform keystore.
- **barmada_ui** is the Notion design system verbatim: aubergine `#1B1223`, sage-outlined cards, Crimson Text display / Inter Tight body, celadon-teal-raspberry semantics, the overdue pulse.

## Getting started

Requirements: [Flutter](https://docs.flutter.dev/get-started/install) ≥ 3.27 (workspace resolution; built against 3.44 stable), Android Studio or the Android SDK for device builds.

```bash
git clone <this repo>
cd barmada-mobile
flutter pub get          # resolves the whole workspace
flutter analyze          # should be clean
cd apps/staff
flutter run              # pick your device/emulator
```

Tests:

```bash
cd packages/barmada_api && dart test
cd packages/barmada_ui  && flutter test
cd apps/staff           && flutter test
```

### Pointing the app at your server

The first screen asks for your Barmada server address:

| You are running | Use |
| --- | --- |
| `php artisan serve` on the same laptop, app in the **Android emulator** | `http://10.0.2.2:8000` |
| `php artisan serve --host=0.0.0.0`, app on a **real phone** (same wifi) | `http://<laptop-LAN-IP>:8000` |
| A deployed venue server | `https://your-domain` |

Plain-HTTP is enabled for development builds (`usesCleartextTraffic`); production venues should serve HTTPS.

Sign in with an editor or staff account from your server (`/register` on the web app creates a venue).

## What works today

- **Add server** with `/api/v1/meta` validation and capability discovery
- **Login** (Sanctum token per device, stored in the keystore), logout, forget server
- **Live board**: pending orders with the overdue chronometer, grouped items and notes; approval requests (first-guest and additional-guest); service requests — polling every 5 s with one-tap **Delivered / Approve / Done** actions
- **Tables**: the live grid (open / pending-approval / closed at a glance) and per-table session screens — running bill with **item-by-item payment ticking**, totals (total / paid / remaining in the venue's currency), open / approve / mark-all-paid, and close with an explicit **settle & close** choice when money is still on the table
- **Products**: the catalog grouped by category with photos and venue-currency prices, a search box for rush moments, and **one-tap 86** — tap a row (or its switch) to mark sold out / back on sale; the guest menu reflects it on its next poll
- **Manual order entry**: the **New order** button on any open table — searchable catalog with quantity steppers, sold-out items blocked, then a review sheet (the guest flow's *Revisar/Confirmar pedido* vocabulary) with an optional kitchen/bar note, submitted through the same CreateOrder path as the QR flow
- **Analytics** (owner accounts): business-day sales headline, orders-by-hour chart, top products with category split, and service ops (sessions, turnover, QR funnel, who-took-the-orders) over Today / 7 / 30 days — the same `VenueAnalytics` read models as the web dashboard
- Dark Barmada theme by default (light theme included), 5-tab shell — **every tab is real now**
- **Bilingual UI (English / Español)**: follows the device language, with a live in-app override under **More → Language**

## Roadmap (next chunks of Phase 1)

1. Push wake-ups (`firebase_messaging` — the server side shipped in barmada PR #29) + notification chime
2. Settings; QR-scan-to-table; print/share
3. Store packaging (icons, splash, signing, CI)

## Localization (i18n)

The staff app ships in **English and Spanish** via Flutter's first-party
`gen-l10n` pipeline. It follows the device language by default; **More →
Language** forces System / English / Español, persisted per device and applied
live. The app language is deliberately independent of the venue's
*guest-language* setting — that one governs the QR web flow; this one is
whatever the staffer holding the phone reads best.

How it's wired:

- Catalogs: `apps/staff/lib/l10n/app_en.arb` (template, with translator notes)
  and `app_es.arb`. Generated `app_localizations*.dart` files are committed;
  regenerate with `dart run melos run l10n` (also happens on `flutter pub get`).
- Spanish vocabulary intentionally matches the web guest flow's `lang/es.json`
  (Pedido, Mesa, Cuenta, Mesero, Entregado…). Keep them aligned.
- **Adding a string**: add it to *both* ARB files, regenerate, use
  `AppLocalizations.of(context).yourKey`. The `l10n_parity_test` fails if the
  catalogs drift (missing keys, mismatched `{placeholders}`, empty values).
- **Adding a language**: drop in `app_xx.arb` with the same keys, list it in
  `l10n_parity_test.dart`, add a `LocaleSetting` entry + picker segment, and
  declare it in `ios/Runner/Info.plist` (`CFBundleLocalizations`).
- Error texts: messages invented by the client carry an `ApiErrorCode`
  (`barmada_api`) and are rendered through the `ApiErrorL10n` extension;
  server-provided messages (validation/domain errors) display verbatim.
  `barmada_ui` stays locale-agnostic — widgets take their texts as parameters.

## Conventions

- `flutter analyze` must stay clean; `dart format .` before committing
- No code generation except `gen-l10n` (no build_runner) — keep the
  clone-and-run loop instant
- Melos scripts available: `dart run melos run analyze | test | format | l10n`
