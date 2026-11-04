# Really Simple Security (free) - capability map

Slug `really-simple-ssl`; main file `really-simple-ssl/rlrsssl-really-simple-ssl.php`; analyzed **9.8.4** (header `rlrsssl-really-simple-ssl.php:5`; readme stable 9.8.4, WP 6.6+, PHP 7.4+ - `readme.txt:5,8,9`; version constant `rlrsssl-really-simple-ssl.php:122`). Paths relative to `vendor/plugins/really-simple-ssl/`.

## Storage
- Settings: one array option `rsssl_options`; on multisite network activation it is a **site option** (`settings/config/config.php:14-16`; `functions.php:17-23`).
- Field definitions/type/defaults in `settings/config/fields/*.php`; loaded by `rsssl_fields()` (`settings/config/config.php:3-5`).
- Runtime flags are separate options: `rsssl_current_version`/`rsssl_previous_version` (`upgrade.php:13,300-302`), `rsssl_activation`, `rsssl_show_onboarding` (`rlrsssl-really-simple-ssl.php:42-43`), `rsssl_ssl_detection_overridden` (`class-admin.php:3354-3356`), `rsssl_email_verification_status` (`security/functions.php:2224-2231`).
- 2FA per-user state is user meta, not an option (`security/wordpress/two-fa/class-rsssl-two-factor-settings.php:377-383`).

## Settings (`rsssl_options[...]`)
- `ssl_enabled` hidden bool 0/1, default false (`settings/config/fields/general.php:10`); written by activation, not a safe direct toggle. **write via activate_ssl only**.
- `site_has_ssl` hidden bool 0/1, default false (`.../general.php:19`); detection/override writes it (`class-admin.php:1267-1269`). Do not set directly.
- `redirect` select `none|wp_redirect|htaccess`, default false (`.../encryption.php:9`); htaccess writes server config. safe with caution.
- `mixed_content_fixer` checkbox, default true (`.../encryption.php:42`). safe.
- `login_protection_enabled` checkbox, 2FA master (`.../two-fa.php:10`); `two_fa_forced_roles` role array (`.../two-fa.php:23`). safe.
- `enable_limited_login_attempts` checkbox (`.../limit-login-attempts.php:9`); `limit_login_attempts_amount` 3/5/10/15 default 5 (`:23`); `limit_login_attempts_locked_out_duration` default 30 (`:70`). safe.
- `enable_firewall` checkbox default false (`.../firewall.php:10`); `404_blocking_threshold` `disabled|lax|normal|strict` default lax (`:98`). safe.
- `enable_vulnerability_scanner` checkbox default false (`.../vulnerability-detection.php:11`). safe.
- `disable_xmlrpc` checkbox (`.../hardening-basic.php:141`); `disable_file_editing` (`:28`). safe, but xmlrpc may be vetoed.
- `hsts` checkbox, requires SSL/writable wp-content (`.../security-headers.php:82`). safe with caution.
- `notifications_email_address` email (`.../general.php:76`); `delete_data_on_uninstall` (`:68`). safe.
- Read: `rsssl_get_option($id,$default)`; returns **true for 1**, default when unset (`functions.php:17-43`).
- Write: `rsssl_update_option($id,$value)` validates the ID exists, sanitizes by declared type, saves option/site option, clears notice cache, fires side effects (`settings/settings.php:747-797`). Unknown IDs and unauthorized users are silently ignored.
- Type sanitizers: checkbox/number → `(int)`, hidden → `sanitize_title`, text/select/password/host → `sanitize_text_field`, email → `sanitize_email`, two_fa_roles filtered against `rsssl_get_roles()` (`settings/settings.php:846-891`).

## Actions
- Update setting: `rsssl_update_option($name,$value)` (`settings/settings.php:747`). Capability `manage_security` required (`rlrsssl-really-simple-ssl.php:252-267`).
- Activate SSL: `RSSSL()->admin->activate_ssl($data)` (`class-admin.php:524`). Only when `site_has_ssl` or `rsssl_ssl_detection_overridden` or WP-CLI (`:552`); sets `redirect=wp_redirect`, `mixed_content_fixer=true`, `ssl_enabled=true`, flushes rewrite/caches (`:564-576`) and changes siteurl/home to https (`:992`).
- Deactivate SSL: `RSSSL()->admin->deactivate()` (`class-admin.php:1030`), destructive.
- Rebuild rules: `do_action('rsssl_update_rules')` (advanced headers then .htaccess) (`security/functions.php:363`); targeted `rsssl_update_htaccess` (`:370`), `rsssl_update_advanced_headers` (`:395`).
- Clear test caches: `rsssl_clear_test_caches(['cache_id'=>$id])` (`settings/settings.php:417`).
- Run a fix: `rsssl_fix(['fix_id'=>$id])` (`settings/settings.php:434`).
- Send notification test: `(new rsssl_mailer())->send_test_mail()`; recipient defaults to `notifications_email_address` (`mailer/class-mail.php:34,57`).
- Activate recommended set: `RSSSL()->admin->activate_recommended_features()` (`class-admin.php:3077`); Pro extras only when `rsssl_pro` defined (`:3095-3105`).

## REST (`really-simple-security/v1`)
- `GET fields/get`, `POST fields/set`, `GET tests/{test}`, `POST do_action/{action}` (`settings/settings.php:290,298,306,314`).
- Permission: `rsssl_user_can_manage()` = `manage_security`; `fields/set`/tests/actions also require nonce `rsssl_nonce` (`settings/settings.php:640-658,345-360,502-510`).
- `fields/set` silently drops IDs not present in `rsssl_fields()` (`settings/settings.php:664-676`).

## Objects
- **security_settings** (option): the `rsssl_options` array; read `rsssl_fields(true)`/`rsssl_get_option()`, write one field via `rsssl_update_option()`. Never replace the whole array.
- **two_factor_user** (user_meta): `rsssl_two_fa_status_email`, `_totp`, `_passkey`, `rsssl_two_fa_last_login`; read status via `Rsssl_Two_Factor_Settings::get_user_status($method,$user_id)` (`.../class-rsssl-two-factor-settings.php:377`); list active email users with `get_users(['meta_key'=>'rsssl_two_fa_status_email','meta_value'=>'active'])` (`.../class-rsssl-two-factor.php:270`). Writes are unsafe; use enrollment/reset flows.

## Detection
- `defined('rsssl_file')` and `defined('rsssl_version')` (or `class_exists('REALLY_SIMPLE_SSL')`); version `rsssl_version` (`rlrsssl-really-simple-ssl.php:29,122`).

## Pro notes (documented/flags only, not implemented here)
- Premium-flagged fields: `enable_passkey_login` (`settings/config/fields/two-fa.php:48,52`) and per-role TOTP `two_fa_enabled_roles_totp` (`:133,138`). Free 2FA uses the email provider (`security/wordpress/two-fa/providers/class-rsssl-two-factor-email.php:33`).
- `activate_recommended_features()` enables limit-login, firewall, event log and advanced headers only when `rsssl_pro` is defined (`class-admin.php:3095-3105`). Treat those Pro extras as documented-only.

## Gotchas
- Capability is `manage_security`, not `manage_options`; administrators get it on activation (`rlrsssl-really-simple-ssl.php:242-249,257`). WP-CLI bypasses the check.
- On multisite network activation the option is `get_site_option('rsssl_options')`; reading `get_option()` returns the wrong site (`functions.php:17-23,316`). Detect with `rsssl_is_networkwide_active()`.
- Checkbox values persist as int 0/1; `rsssl_get_option()` maps 1→true (`settings/settings.php:849-851`; `functions.php:39-41`). Re-read after write.
- Some saves are vetoed to 0 when prerequisites fail: firewall, XML-RPC, HSTS, login protection, notification email (`security/sync-settings.php:201-231`). Do not assume the requested value stuck.
- Saving fields fires `rsssl_after_saved_fields`, which schedules .htaccess rebuilds and firewall installs (`security/functions.php:986`; `security/firewall-manager.php:83`). Expect filesystem writes/cache flushes.
- `activate_ssl()` changes `siteurl`/`home` to https and can lock out a site if the certificate is not ready; pre-check `site_has_ssl`/certificate (`class-admin.php:552-577,992`).
- 404 blocking is auto-disabled when the homepage contains 404 resources (`security/functions.php:2245`); HSTS requires `site_has_ssl` and writable wp-content (`settings/config/fields/security-headers.php:82-89`).
- Email notification fields require `rsssl_email_verification_status=completed` (`security/functions.php:2224-2231`).
- REST writes need both `manage_security` and nonce `rsssl_nonce`; direct PHP calls should still enforce the capability (`settings/settings.php:640-658`).
