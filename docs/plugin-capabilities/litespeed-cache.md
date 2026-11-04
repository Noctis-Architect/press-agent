# LiteSpeed Cache 7.9.3 (free) — PressAgent capability map

Plugin file `litespeed-cache/litespeed-cache.php`; version `LSCWP_V` (`litespeed-cache.php:38`); class `LiteSpeed\Core` (`src/core.cls.php:23`).

## Detection
- `defined('LSCWP_V')` / `class_exists('LiteSpeed\Core')` — `litespeed-cache.php:38`, `src/core.cls.php:23`.
- Basename `LSCWP_BASENAME` = `litespeed-cache/litespeed-cache.php` — `litespeed-cache.php:42`.

## Storage & write path
- Every setting is its own option: `litespeed.conf.<id>`; name built by `Root::name()` — `src/root.cls.php:563`.
- Arrays are JSON-encoded on save by `Conf::_maybe_encode()` — `src/root.cls.php:668`; `Conf::_maybe_decode()` reads them — `src/root.cls.php:653`.
- `litespeed.conf.hash` is a **random 32-char secret**, not a checksum: default empty (`src/base.cls.php:360`), generated (`:873`), explicitly not settable (`:32`) and skipped by `Conf::update()` (`src/conf.cls.php:721`). Do not write it.
- `litespeed.conf._version` is the internal version marker — `src/base.cls.php:31`.
- Defaults live in `data/const.default.json` + class defaults; many option rows do not exist until saved.
- Static cache root: `wp-content/litespeed` — `litespeed-cache.php:67`.

**Canonical write:** `LiteSpeed\API::cls()->save_conf(['cache'=>1,...])` (`src/api.cls.php:317`) → `Conf::update_confs()` (`src/conf.cls.php:637`) → per-id `Conf::update()` (`:708`, type casting `src/base.cls.php:714`), then purge rules (`:647`), cron and `Activation::update_files()` (`:680`). Raw `update_option` skips all of this.

## Settings (option `litespeed.conf.<id>`)
| id | Shape | Notes / values | Source |
|---|---|---|---|
| `hash` | string | Random secret; read-only | `src/base.cls.php:32`, `:873` |
| `_version` | string | Internal version; read-only | `src/base.cls.php:31` |
| `cache` | int | 0 off, 1 on, 2 network inherit (multisite); change purges all | `src/base.cls.php:45`, `:863` |
| `cache-priv` | bool | Cache private pages | `src/base.cls.php:46` |
| `cache-mobile` | bool | Separate mobile cache | `src/base.cls.php:51` |
| `cache-browser` | bool | Browser cache headers | `src/base.cls.php:53` |
| `cache-rest` | bool | Cache REST responses | `src/base.cls.php:48` |
| `cache-ttl_pub` | int | Public TTL **seconds**, default 604800 | `src/base.cls.php:65`, `data/const.default.json:26` |
| `cache-ttl_frontpage` | int | Front page TTL seconds, default 604800 | `src/base.cls.php:67`, `data/const.default.json:28` |
| `cache-exc` | array | Excluded URL paths; normalized `relative` | `src/base.cls.php:62` |
| `cache-exc_cookies` | array | Cookie names/patterns | `src/base.cls.php:55` |
| `cache-exc_useragents` | array | User-agent strings | `src/base.cls.php:54` |
| `cache-exc_qs` | array | Ignored query params | `src/base.cls.php:56` |
| `cache-force_uri` | array | Force-cache URL paths | `src/base.cls.php:59` |
| `purge-post_all` | bool | Purge whole cache on post update | `src/base.cls.php:82` |
| `purge-upgrade` | bool | Purge after plugin upgrade | `src/base.cls.php:80` |
| `optm-css_min` | bool | Minify CSS | `src/base.cls.php:135` |
| `optm-css_comb` | bool | Combine CSS | `src/base.cls.php:136` |
| `optm-ucss` | bool | Unique CSS (QUIC.cloud quota) | `src/base.cls.php:138` |
| `optm-js_min` | bool | Minify JS | `src/base.cls.php:144` |
| `optm-js_comb` | bool | Combine JS | `src/base.cls.php:145` |
| `optm-js_defer` | int | 0 off, 1 deferred, 2 delayed | `src/base.cls.php:161`, `tpl/page_optm/settings_js.tpl.php:77` |
| `optm-html_min` | bool | Minify HTML | `src/base.cls.php:149` |
| `optm-css_font_display` | bool | Add font-display | `src/base.cls.php:160` |
| `media-lazy` | bool | Native lazy loading | `src/base.cls.php:203` |
| `img_optm-webp` | int | 0 off, 1 WebP, 2 AVIF (AVIF is paid QUIC.cloud) | `src/base.cls.php:234`, `tpl/img_optm/settings.media_webp.tpl.php:22,31` |
| `object` | bool | Object cache on/off; credentials in sibling `object-*` keys | `src/base.cls.php:180` |
| `crawler` | bool | Crawler on/off; `crawler-sitemap`, `crawler-load_limit` | `src/base.cls.php:244` |
| `debug` | int | 0 off, 1 on, 2 admin IP only | `src/base.cls.php:115`, `tpl/toolbox/settings-debug.tpl.php:80` |
| `debug-level` | bool | Advanced detail (when debug on) | `src/base.cls.php:117` |

Multi-switch ids are `debug`, `optm-js_defer`, `img_optm-webp` (`src/base.cls.php:635`); `cache` is int. Read effective values with `Conf::conf($id)` (`src/root.cls.php:482`), never `get_option()` alone.

## Actions
| Action | Implementation | Safety | Source |
|---|---|---|---|
| Save settings | `LiteSpeed\API::cls()->save_conf($matrix)` | write | `src/api.cls.php:317` |
| Update conf | `Conf::cls()->update_confs($matrix)` | write | `src/conf.cls.php:637` |
| Read setting | `Conf::cls()->conf($id)` | read | `src/root.cls.php:482` |
| Purge all | `do_action('litespeed_purge_all')` / `Purge::purge_all($reason)` | destructive | `src/purge.cls.php:231` |
| Purge URL | `do_action('litespeed_purge_url', $url)` | destructive | `src/api.cls.php:113` |
| Purge post | `do_action('litespeed_purge_post', $post_id)` | destructive | `src/api.cls.php:111` |
| Purge post type | `do_action('litespeed_purge_posttype', $post_type)` | destructive | `src/api.cls.php:112` |
| Purge page cache only | `Purge::purge_all_lscache($reason)` | destructive | `src/purge.cls.php:283` |

## REST (management routes are limited)
- `POST /wp-json/litespeed/v1/toggle_crawler_state` — `manage_options`/`manage_network_options` — `src/rest.cls.php:50`.
- `GET /wp-json/litespeed/v1/tool/check_ip` — same caps — `src/rest.cls.php:58`.
- `GET /wp-json/litespeed/v1/guest/sync` — same caps — `src/rest.cls.php:66`.
- No REST route exists for saving settings or purging; use the PHP API/actions above (or WP-CLI).

## Objects
- **`litespeed_settings`** — options `litespeed.conf.*`; CRUD only through `save_conf`/`update_confs` so validation, purge and config-file sync run (`src/conf.cls.php:637`).
- **`litespeed_cache_store`** — files under `wp-content/litespeed` + web-server cache; no supported file CRUD, only purge APIs (`litespeed-cache.php:67`, `src/purge.cls.php:231`).

## Gotchas
- Writing `litespeed.conf.*` via `update_option()` bypasses type casting, validation, automatic purge and `.litespeed_conf` file sync; use `API::save_conf()`/`Conf::update_confs()`.
- The hash is a random ESI/vary secret, **not** a settings checksum; changing other settings requires no hash update, and overwriting the hash breaks ESI integrity until regenerated.
- Array options are JSON strings on disk; `Conf::conf()` decodes them. Do not store PHP serialized arrays directly.
- Defaults are code/data defaults, not option rows; a missing `litespeed.conf.cache` does not mean cache is off.
- TTL values are seconds; `cache-ttl_pub` default 604800 and UI warns below 30.
- `cache`, `debug`, `optm-js_defer`, `img_optm-webp` are 0/1/2 multi-switch values, not booleans.
- Multisite `cache=2` means inherit network; shared object-cache purges need network authority.
- Changing cache/optimization settings triggers purges; cache warm-up is expected.
- WP-CLI: `wp litespeed-purge all|url|post_id|...` — `cli/purge.cls.php:68`.
