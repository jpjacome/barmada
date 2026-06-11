# Security Deployment & Operations Runbook

This runbook covers the security items that **cannot be fixed in application code**
and must be handled by whoever deploys and operates Barmada. It accompanies the
code remediation delivered in the `security/audit-remediation` branch.

Items are ordered by urgency. Treat the "Critical" section as release-blocking.

---

## CRITICAL — Document root & secret exposure (finding C-2)

**Problem:** The application has been deployed with the project root (not
`public/`) as the web server's document root. That makes `.env`, `composer.json`,
source code, storage and VCS metadata directly downloadable
(e.g. `https://host/.env`, `https://host/storage/...`).

### 1. Point the web server at `public/` only

**Nginx**
```nginx
server {
    server_name your-domain;
    root /var/www/barmada/public;     # NOT /var/www/barmada
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    # Deny dotfiles (defense in depth)
    location ~ /\.(?!well-known) { deny all; }
}
```

**Apache** — set `DocumentRoot /var/www/barmada/public`, ensure
`AllowOverride All` so `public/.htaccess` is honored, and confirm the shipped
`public/.htaccess` is present.

### 2. Rotate every secret that could have been exposed

Because the root was web-served, assume `.env` and `APP_KEY` were disclosed.
Rotate **all** of the following, then deploy the new `.env`:

- `APP_KEY` — `php artisan key:generate` (note: this invalidates existing
  encrypted values and all current sessions; users must re-login).
- Database credentials (DB user password).
- Mail credentials, AWS keys, and any third-party API keys/tokens.
- Any signing/queue/cache secrets.

### 3. Confirm the leak is closed

After re-pointing the root, verify these all return **403/404**, not file
contents:
`/.env`, `/composer.json`, `/.git/config`, `/storage/logs/laravel.log`,
`/storage/app/archive/...`.

---

## CRITICAL — Admin account hygiene (findings C-1, C-2)

- The old unauthenticated `/reset-admin` route (which reset the admin to a
  hard-coded password) has been removed in code. **Immediately set a strong,
  unique admin password** if the route was ever live, since anyone could have
  used it.
- Do **not** ship the seeded demo accounts (`admin@golems.bar`,
  `editor@golems.bar`, `editor@example.com`) to production. Seeders are for
  local/dev only — never run `db:seed` in production.
- Create production admins with the CLI, which now prompts for the password
  (so it is not stored in shell history): `php artisan user:create-admin admin@yourdomain`.

---

## REQUIRED — Post-deploy steps for this release

Run these as part of deploying the `security/audit-remediation` branch.

### 1. De-duplicate table numbers BEFORE migrating

This release adds a composite unique index on `tables (editor_id, table_number)`.
If any tenant already has duplicate table numbers, the migration will fail.
Find and resolve duplicates first:
```sql
SELECT editor_id, table_number, COUNT(*) c
FROM tables GROUP BY editor_id, table_number HAVING c > 1;
```
Then run migrations:
```bash
php artisan migrate --force
```

### 2. Relocate existing order archives off the public disk

Order XML exports now write to `storage/app/archive/{editor_id}/` (private) and
are served only through the authorized, owner-checked download route. Any
**pre-existing** archives under the public path were world-downloadable — move
them and remove the public copies:
```bash
mkdir -p storage/app/archive
cp -rn storage/app/public/archive/* storage/app/archive/ 2>/dev/null || true
rm -rf storage/app/public/archive
```

### 3. Production environment values

Ensure the production `.env` has:
```
APP_ENV=production
APP_DEBUG=false              # never true in production (leaks stack traces)
SESSION_SECURE_COOKIE=true   # HTTPS-only cookies (config defaults to true)
```

### 4. Storage symlink

Product images/photos are still served from the public disk, so keep the
symlink: `php artisan storage:link`. (Order archives are deliberately NOT under
this symlink.)

---

## RECOMMENDED — Dependency hygiene (finding M-5)

Run regularly (CI is a good home for the first two):
```bash
composer audit            # flags known CVEs in PHP dependencies
composer outdated --direct # surfaces upgradable direct dependencies
npm audit                 # JS/build dependencies
```
- Keep `composer.lock` and `package-lock.json` committed (they are) so
  deployments are reproducible.
- Apply security updates promptly; pin major versions and test upgrades in
  staging.

---

## RECOMMENDED — HTTP security headers

These are infrastructure-level (web server or a global middleware) and are not
set by application code today. Add at the web server or via a response
middleware:

- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- `X-Content-Type-Options: nosniff`  (important: prevents MIME-sniffing of
  uploaded SVG/product assets)
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- A `Content-Security-Policy` appropriate to the app's asset origins.

Production already forces HTTPS at the framework level
(`AppServiceProvider::boot`), but HSTS should also be set at the edge.

---

## Reference — what the code remediation already covers

For traceability, the branch addresses (in code) the unauthenticated admin
reset (C-1), multi-tenant data isolation via a global scope + policies +
per-action authorization (C-3, C-4, C-5), endpoint authentication/ownership
(H-1, H-2), output escaping (H-3), guest-flow device approval / throttling /
quantity caps (H-4, H-6, M-4), upload hardening (H-5), owner-only archive
downloads (H-7), mass-assignment guards and per-tenant table uniqueness,
session/debug defaults, removal of committed planning/info-disclosure files,
safe logging, CSV formula neutralization, impersonation-leave control, the CLI
password prompt, the broadcast-channel lockdown, and the URL-root fallback.

The items in **this** document are the remainder that only an operator can close.
