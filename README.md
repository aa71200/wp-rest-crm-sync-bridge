# WP REST CRM Sync Bridge

WordPress REST API plugin demonstrating secure inbound CRM webhooks and admin-authenticated outbound user syncing with schema validation and idempotent retries.

## What This Demonstrates

- **Strict `WP_REST_Controller` Design:** Registers versioned routes (`codexawp-crm/v1`) with explicit `args` schemas, enums, email validation, and sanitization callbacks.
- **HMAC-SHA256 Webhook Security:** Verifies `X-WPRB-Signature` over `timestamp.raw_body` using `hash_equals` and enforces a 5-minute replay protection window (`X-WPRB-Timestamp`).
- **Idempotent Event Processing:** Deduplicates repeated webhook deliveries by `event_id` via transients so retried CRM callbacks do not cause duplicate writes.
- **Safe Outbound Sync:** Uses `wp_remote_post` with explicit timeouts, deterministic `Idempotency-Key` headers, bearer authentication, and structured `WP_Error` handling.

## Endpoints

- `POST /wp-json/codexawp-crm/v1/webhook/contact` — Signed inbound CRM contact update.
- `POST /wp-json/codexawp-crm/v1/sync/user/{id}` — Admin-only (`manage_options`) outbound sync for a WordPress user.

## Configuration

Define in `wp-config.php` (or store via options):

```php
define( 'WPRB_WEBHOOK_SECRET', 'replace-with-random-secret' );
define( 'WPRB_CRM_ENDPOINT', 'https://crm.example.com/api/contacts' );
define( 'WPRB_CRM_API_KEY', 'replace-with-crm-token' );
```

## Staging Verification Steps

1. Activate the plugin and configure `WPRB_WEBHOOK_SECRET`.
2. Send a signed `POST` request to `/wp-json/codexawp-crm/v1/webhook/contact` with `event_id`, `email`, `status`, and `tags` and verify HTTP `200` plus updated user meta.
3. Replay the exact same payload and confirm `duplicate: true` is returned without re-writing meta.
4. Alter the request body without recalculating `X-WPRB-Signature` and confirm HTTP `401` (`wprb_invalid_signature`).
