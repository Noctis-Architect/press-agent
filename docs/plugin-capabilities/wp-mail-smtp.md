# WP Mail SMTP (free) - capability map

Slug `wp-mail-smtp`; main file `wp-mail-smtp/wp_mail_smtp.php`; analyzed **4.10.3** (header `wp_mail_smtp.php:3`; readme stable 4.10.3, WP 5.5+, PHP 7.4+ - `readme.txt:4-7`; version constant `wp_mail_smtp.php:239-245`). Paths relative to `vendor/plugins/wp-mail-smtp/`.

## Storage
- One non-autoloaded option `wp_mail_smtp` (`src/Options.php:200`); groups: `mail`, `smtp`, `general`, plus one group per mailer (`src/Options.php:30-150`).
- Defaults: `mail.from_email=admin_email`, `from_name=site name`, `mailer=mail`, `return_path=true`, `from_email_force=true`, `from_name_force=false`; `smtp.auth=true`, `autotls=true` (`src/Options.php:273-291`).
- Only the primary connection exists in free (`src/Connection.php:10,36`; `src/ConnectionsManager.php:40`).
- SMTP password is encrypted with sodium before save (`src/Options.php:1601`); key in `wp_mail_smtp_mail_key` or `WPMS_CRYPTO_KEY` (`src/Helpers/Crypto.php:23-42`).
- Constants `WPMS_*` override DB values when `WPMS_ON=true` (`src/Options.php:553-623,1010-1015`; examples `wp_mail_smtp.php:43-64`).
- Test-email defaults: option `wp_mail_smtp_test_email` (`src/Admin/Pages/TestTab.php:51`); debug events: table `{prefix}wpmailsmtp_debug_events` (`src/Admin/DebugEvents/DebugEvents.php:441`; schema `src/Admin/DebugEvents/Migration.php:47`); versions `wp_mail_smtp_version`, `wp_mail_smtp_initial_version` (`src/Core.php:683-686`).

## Settings (`wp_mail_smtp[...]`)
- `mail.mailer` string enum; invalid falls back to `mail` (`src/Options.php:279,1503-1508`). safe.
- `mail.from_email` string, validated email then sanitize_email; invalid replaced by default sender (`src/Options.php:277,1510-1518`). safe.
- `mail.from_name` string, `sanitize_text_field` (`src/Options.php:278,1500-1501`). safe.
- `mail.from_email_force`, `mail.from_name_force`, `mail.return_path` bools (`src/Options.php:280-282,1522`). safe.
- `smtp.host` string, `sanitize_text_field` (`src/Providers/OptionsAbstract.php:237`; `src/Options.php:1578-1582`). safe.
- `smtp.port` int (`src/Providers/OptionsAbstract.php:291`; `src/Options.php:1584-1586`). safe.
- `smtp.encryption` `none|ssl|tls` (`src/Providers/OptionsAbstract.php:251-280`; `src/Options.php:1578-1582`). safe.
- `smtp.autotls`, `smtp.auth` bools (`src/Providers/OptionsAbstract.php:305,327`; `src/Options.php:1587-1591`). safe.
- `smtp.user` string, `sanitize_text_field` (`src/Providers/OptionsAbstract.php:346`; `src/Options.php:1578-1582`). safe.
- `smtp.pass` secret: plaintext accepted, encrypted before save; UI shows `*************` for constant-defined passwords and a hidden field otherwise (`src/Options.php:1594-1601`; `src/Providers/OptionsAbstract.php:366,400`). **Write-only**; never return decrypted/ciphertext.
- `general.do_not_send` bool kill switch (`src/Admin/Pages/MiscTab.php:105`; checked `src/Core.php:874`). safe.
- Save path: `Options::init()->set($data,false,false)` (`src/Options.php:1430`), called by settings screen at `src/Admin/Pages/SettingsTab.php:434`; it sanitizes, encrypts and saves non-autoloaded (`src/Options.php:1465-1476`). Direct `update_option()` is unsafe.
- Read path: `Options::init()->get($group,$key)` (`src/Options.php:394`); raw DB read populates `$options` at `src/Options.php:309-322`; pass is decrypted in `get_existing_option_value()` (`:467-474`).

## Actions
- `update_settings`: `Options::init()->set($options,false,false)`; partial nested array, merges existing (`src/Options.php:1430-1456`).
- `send_test_email`: `(new TestEmail())->with_connection($conn)->as_html($html)->with_domain_check($bool)->send($recipient)`; returns `SUCCESS`/`FAILED`/`FAILED_DOMAIN_CHECK` or null (`src/TestEmail/TestEmail.php:127,159,175,266`). Admin path: `src/Admin/Pages/TestTab.php:173-190`.
- `list_debug_events`: `new EventsCollection([...])` then `get_count()`/`get()` (`src/Admin/DebugEvents/EventsCollection.php:67,289,312`); optional ability `wp-mail-smtp/get-debug-events` (`src/Abilities/AbilityRegistrar.php:164`, read-only/mcp public `src/Abilities/AbstractAbility.php:40-53`).
- No email-send API beyond `wp_mail()`; the plugin hooks the PHPMailer pipeline.

## REST
- **No general settings REST API.** Admin uses admin-post/AJAX; the only REST surface is the signed setup wizard at namespace `wp-mail-smtp/setup-wizard/v1` (`src/Admin/SetupWizard/RestApi.php:70,190`), protected by `X-Timestamp/X-Nonce/X-Signature` (`:207-225`). Do not use it for PressAgent automation.
- Debug events are exposed through the WordPress Abilities API (WP 6.9+) as `wp-mail-smtp/get-debug-events`, not a plugin-owned REST route (`src/Abilities/AbilityRegistrar.php:30,164`).

## Objects
- **smtp_connection** (option): only id `primary`; read options via `wp_mail_smtp()->get_connections_manager()->get_primary_connection()->get_options()` (`src/ConnectionsManager.php:40`; `src/Connection.php:54`); update via `Options::set()`; no create/delete in free.
- **debug_event** (table `{prefix}wpmailsmtp_debug_events`): columns id, content, initiator, event_type, created_at (`src/Admin/DebugEvents/Migration.php:47-55`); read-only for PressAgent via `EventsCollection`. Not an email log.

## Detection
- `function_exists('wp_mail_smtp')` and `defined('WPMS_PLUGIN_VER')`; version is `WPMS_PLUGIN_VER` (`wp_mail_smtp.php:245`).

## Pro notes (documented only, not in source)
- Pro adds email log, reports/stats, alerts, backup connections, smart conditional routing, rate limiting, optimized sending, managed emails and multisite (`readme.txt:103-173`). The free source has no Pro log/stats implementation (`src/Abilities/AbilityRegistrar.php:15-17`); do not expose these.

## Gotchas
- Never return `smtp.pass`: DB value is ciphertext, `Options::get()` decrypts it; expose `pass_set` only and mask (`src/Options.php:467-474,1601`; `src/Providers/OptionsAbstract.php:366,400`).
- `Options::set()` is mandatory for writes: it encrypts the password and skips keys fixed by constants (`src/Options.php:1430,1566-1640`); `update_option()` bypasses all of it.
- If `WPMS_ON` is true, `WPMS_MAILER`, `WPMS_SMTP_HOST/PORT/SSL/AUTH/USER/PASS/AUTOTLS` and `WPMS_MAIL_FROM*` override stored values; report them as locked, not saved (`src/Options.php:553-623,1010-1015`).
- The option is non-autoloaded and cached in memory per request; re-init `Options` before reading back a direct write (`src/Options.php:309-322`).
- `general.do_not_send`/`WPMS_DO_NOT_SEND` blocks all sends; test email will fail while blocked (`src/Core.php:874`; `wp_mail_smtp.php:45`).
- Mailer-specific groups only apply when `mail.mailer` selects that mailer; writing `smtp.*` while mailer=`gmail` has no effect (`src/Options.php:1566-1573`).
- Test email performs a real send and optional domain check; rate-limit/confirm before invoking repeatedly (`src/TestEmail/TestEmail.php:266-345`).
- Debug events are diagnostics, not an email log; email logging is Pro-only (`readme.txt:109`).
