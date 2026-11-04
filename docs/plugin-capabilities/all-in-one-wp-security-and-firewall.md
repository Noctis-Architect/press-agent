# All-In-One Security / AIOS (5.5.1) — capability notes

Source: `vendor/plugins/all-in-one-wp-security-and-firewall` (free). Full machine-readable spec: `all-in-one-wp-security-and-firewall.json`.

## Storage
- All user settings: one option array `aio_wp_security_configs`.
- Config API: `AIOWPSecurity_Config::get_value()/set_value()/save_config()` (`classes/wp-security-config.php:23`, `:52`, `:76`).
- Checkbox values are stored as `'1'` or `''`.
- Firewall/htaccess rules are rebuilt with `AIOWPSecurity_Utility_Htaccess::write_to_htaccess()` (`classes/wp-security-utility-htaccess.php:157`).
- Detection: `defined('AIO_WP_SECURITY_VERSION')` (`wp-security-core.php:129`).

## Exposed settings (high value)
| Area | Keys |
|---|---|
| Login lockout | `aiowps_enable_login_lockdown`, `aiowps_max_login_attempts`, `aiowps_retry_time_period`, `aiowps_lockout_time_length` |
| IP lists | `aiowps_enable_whitelisting`, `aiowps_allowed_ip_addresses`, `aiowps_enable_blacklisting`, `aiowps_banned_ip_addresses` |
| Firewall/hardening | `aiowps_enable_basic_firewall`, `aiowps_enable_pingback_firewall`, `aiowps_disable_xmlrpc_pingback_methods`, `aiowps_block_debug_log_file_access`, `aiowps_disable_index_views`, `aiowps_disable_trace_and_track`, `aiowps_forbid_proxy_comments`, `aiowps_deny_bad_query_strings`, `aiowps_advanced_char_string_filter`, `aiowps_enable_5g_firewall` |
| 404/login | `aiowps_enable_404_logging`, `aiowps_enable_404_IP_lockout`, `aiowps_404_lockout_time_length`, `aiowps_404_lock_redirect_url`, `aiowps_enable_rename_login_page`, `aiowps_login_page_slug` |
| Brute force/CAPTCHA | `aiowps_enable_brute_force_attack_prevention`, `aiowps_brute_force_secret_word`, `aiowps_enable_login_captcha`, `aiowps_enable_registration_page_captcha`, `aiowps_enable_comment_captcha` |
| Session/hardening | `aiowps_enable_forced_logout`, `aiowps_enable_invalid_username_lockdown`, `aiowps_enable_email_notify`, `aiowps_email_address`, `aiowps_disable_file_editing`, `aiowps_enable_login_honeypot`, `aiowps_disable_application_password` |

## 6G firewall config
- Rules live in a separate JSON firewall config managed by `AIOS_Firewall_Resource::CONFIG` (`classes/wp-security-firewall-resource.php:16`) and `AIOWPS\Firewall\Config::get_contents()/set_contents()` (`classes/firewall/libs/wp-security-firewall-config.php`).
- Exposed keys: `aiowps_6g_block_request`, `_query`, `_referrers`, `_agents`, `_request_methods`, pingback/proxy/query-string filters, blacklist IPs/agents, Googlebot/Bingbot ranges.
- Main switch in the main config: `aiowps_enable_6g_firewall` (`classes/wp-security-configure-settings.php:817`).

## Actions
- `rebuild_htaccess` — rewrite AIOS rules from current config.
- `aios_firewall_config` set — persist the firewall JSON and rebuild managed rules.

## Gotchas
- Values are strings `'1'`/`''`; keep that form for fidelity.
- Changing firewall/login settings can rewrite `.htaccess`; verify the site remains reachable.
- 6G rules are separate from `aio_wp_security_configs`; use the `aios_firewall_config` setting.
- Unknown field IDs are rejected by PressAgent's registry.
