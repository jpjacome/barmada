# Push notifications (staff mobile app)

Barmada can wake the staff app's registered devices when something needs
attention at the venue:

| Event | Fired when |
| --- | --- |
| `order.created` | Any order is placed — guest QR, staff form or API |
| `approval.requested` | A new guest device asks to join a table (first guest on a closed table, or an additional device on an open one) |
| `service.requested` | A guest taps *Request the bill* or *Call a waiter* (first tap per type per session) |

Pushes go to every device registered by the venue's editor and staff
accounts (`POST /api/v1/devices` from the app). Delivery is best-effort
by design: the app's foreground polling remains the source of truth, so
a venue with `PUSH_DRIVER=none` loses nothing except lock-screen alerts.

## Drivers

### `none` (default)
No pushes are sent and nothing leaves the server. Zero configuration.

### `fcm` — your own Firebase project
For self-hosters who run their own Firebase project (and their own app
build, or a store build configured for their project):

1. Create a Firebase project and enable the **Firebase Cloud Messaging
   API (v1)**.
2. Project settings → Service accounts → **Generate new private key**;
   store the JSON outside the web root, e.g. `storage/app/fcm.json`.
3. Configure:

```env
PUSH_DRIVER=fcm
FCM_PROJECT_ID=your-project-id
FCM_CREDENTIALS_PATH=/var/www/barmada/storage/app/fcm.json
```

No SDK is involved: the server signs the Google OAuth assertion itself
(openssl) and talks to FCM HTTP v1 directly. Stale device tokens
(uninstalled apps) are cleaned up automatically when FCM reports them
unregistered.

With `PUSH_INCLUDE_CONTENT=true` (default) notifications carry a title
like *“New order — Table 4”* so the OS can display them with the app
fully backgrounded. Set it to `false` for pure “wake and sync” data
messages with no content.

### `relay` — the hosted Barmada Push Relay
For self-hosted servers using the **official store app**, which only
accepts pushes signed with the app publisher's Firebase keys. The relay
(per the mobile proposal §5.3, the Home Assistant model) is
**payload-light by design**: it receives device tokens and an event name
— never order data, table numbers or amounts — and forwards a wake-up to
FCM/APNs.

```env
PUSH_DRIVER=relay
PUSH_RELAY_URL=https://push.barmada.example
PUSH_RELAY_KEY=your-relay-key
```

> The relay service itself has not shipped yet; this driver defines the
> server-side contract so existing installs only need an `.env` change
> when it does.

## Dispatch mode

`PUSH_DISPATCH=sync` (default) sends inline in the request — fine for a
venue with a handful of staff devices. For busier venues set
`PUSH_DISPATCH=queue` and run a worker (`php artisan queue:work`); the
default queue connection is `database`, so no extra infrastructure is
required.

## Capability discovery

`GET /api/v1/meta` advertises `"push"` in its feature list when a driver
is configured, so the app knows whether to expect wake-ups or lean on
its polling fallback.
