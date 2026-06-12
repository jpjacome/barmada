# Barmada — Bar & Restaurant Management Platform

Barmada is a self-hosted, multi-tenant QR-ordering and table-management platform for small bars and restaurants, built with **Laravel 12 + Livewire 3**. Guests scan a printed QR at their table and order from their phone — **no app, no account, no payment wall**. Staff watch a live board, get sound alerts, and track payment item by item. Each venue ("editor") runs in a fully isolated tenant; a platform admin oversees all of them.

**Why Barmada instead of a commercial QR-ordering SaaS?**

> Order without paying online, with no commission ever, on hardware you already own, on a server you control.

- **No forced guest payment** — guests order freely; the bar collects cash or card the way it always has. Fits tab-and-rounds bar culture that payment-first products fight against.
- **No commission, no subscription** — self-hosted on commodity PHP hosting; the venue keeps 100% of revenue and owns its data.
- **No hardware lock-in** — any browser is the staff board; printing uses the browser's print dialog.
- **Per-session security on permanent QRs** — printed codes never change, but each table session rotates a token and every guest device is staff-approved (device cookie, NAT/CGNAT-safe).

## Features

### Guests (QR flow)
- Scan → device-approval waiting room → visual menu (photos, descriptions, prices) in the venue's language and currency
- Sticky cart with running total, order review with optional notes ("no ice"), sold-out items clearly marked
- **"My table" page**: orders this session with live statuses, running bill (total / paid / remaining), **request the bill** and **call a waiter** buttons

### Venue (editor + staff accounts)
- 3-step self-serve onboarding (tables auto-created), per-bar table numbering, table reference labels
- Live orders board: pending orders with chronometers, **sound + tab-flash alerts** (mutable), device-approval queue, guest **service-request panel**, cancellable orders
- Item-level payment tracking (tap items as they're paid), per-order and whole-table settle, explicit table close
- Product catalog with categories, photos, icons, descriptions and a one-tap **availability (86) toggle**
- **Client invoice capture** per session (name, tax ID, …) printed on the bill — built for LatAm/EU invoice requests
- **Printing via the browser**: per-table bill, per-order ticket, and a bulk all-tables QR sheet
- Staff accounts (create / edit / delete), scoped to the venue
- Analytics: sales, AOV, top/least products, category mix, peak hours (venue clock), session durations, table turnover, QR-scan conversion, staff order counts — bucketed on the venue's **business day** (timezone + cutoff hour), exportable to PDF and CSV
- Business settings: currency symbol, guest-menu language (EN/ES), timezone, business-day cutoff
- Table archiving (retire a table, keep its reporting history), order archive (XML) with owner-only downloads

### Platform admin
- Establishments list, one-click impersonation, transactional tenant deletion, global theme logos

### Engineering
- Enforced multi-tenant isolation: global query scope + policies on every model and Livewire action
- Guest endpoints rate-limited; uploads validated and stored safely; device approval middleware on all tokenized routes
- **Test suite: 115+ tests** covering tenant isolation, authorization, the full first-customer flow, device cookies, availability, notes, analytics exports and more

## Requirements

- PHP ≥ 8.2 (sqlite/mysql PDO, gd, mbstring, xml)
- Composer
- Any web server pointing its document root at `public/` (see `docs/SECURITY-DEPLOYMENT.md`)

## Installation

```bash
git clone https://github.com/jpjacome/barmada.git
cd barmada
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env (sqlite works out of the box)
php artisan migrate
php artisan user:create-admin admin@example.com   # prompts for password
```

Serve locally with `php artisan serve`. Each venue registers itself at `/register`; print the QR sheet from the Tables page and you're in business.

## Tests

```bash
php artisan test
```

## Documentation

- `docs/SECURITY-DEPLOYMENT.md` — production deployment & security runbook (web root, secret rotation, cookies)
- `docs/tables-payment-system.md` — order/payment data model

## License

Proprietary — see `EULA.txt`. © DR PIXEL.
