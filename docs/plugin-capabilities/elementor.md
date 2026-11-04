# Elementor 4.3.6 (free) — PressAgent capability map

Plugin file `elementor/elementor.php`; version constant `ELEMENTOR_VERSION` (`elementor.php:31`); class `Elementor\Plugin` (`includes/plugin.php:41`).

## Detection
- `defined('ELEMENTOR_VERSION')` / `class_exists('\Elementor\Plugin')` — `elementor.php:31`, `includes/plugin.php:41`.
- Plugin basename: `ELEMENTOR_PLUGIN_BASE` = `elementor/elementor.php` — `elementor.php:35`.

## Storage
- Global options: individual `wp_options` named `elementor_<field>`; prefix rule `includes/settings/settings-page.php:264`, fields `includes/settings/settings.php:165`.
- Active Kit: option `elementor_active_kit` (`core/kits/manager.php:22`); previous Kit `elementor_previous_kit` (`:24`).
- Kit document: `elementor_library` post with `_elementor_template_type=kit` (`core/kits/manager.php:200,204`, `core/kits/documents/kit.php:38`); settings in `_elementor_page_settings` (`core/base/document.php:43`).
- Page JSON: `_elementor_data` JSON string (`core/base/document.php:44`, save `:1387`).
- Experiments: one option per feature, `elementor_experiment-<name>` (`core/experiments/manager.php:36`).
- Generated cache: `_elementor_css` (`core/files/css/post.php:30`), `_elementor_element_cache` (`core/base/document.php:48`), `_elementor_page_assets` (`core/base/elements-iteration-actions/assets.php:13`).

## Settings (all option storage)
| Key | Shape | Values / notes | Source |
|---|---|---|---|
| `elementor_active_kit` | int | Active Kit post ID | `core/kits/manager.php:22` |
| `elementor_cpt_support` | array | Default `["page","post"]`; restricts editable post types | `includes/plugin.php:760` |
| `elementor_css_print_method` | string | `external`\|`internal`; triggers cache clear | `includes/settings/settings.php:307` |
| `elementor_unfiltered_files_upload` | string | `""`\|`"1"`; enables SVG/JSON uploads (security risk) | `includes/settings/settings.php:247`, `core/files/uploads-manager.php:28` |
| `elementor_experiment-<name>` | string | `active`\|`inactive`\|`default`; names incl. `container`, `e_font_icon_svg`, `additional_custom_breakpoints`, `e_optimized_markup`, `e_optimized_css_files` | `core/experiments/manager.php:336`, registered `:342` |
| `elementor_element_cache_ttl` | string | Hours; empty→24; `disable` turns off; change clears cache | `core/base/document.php:1964`, hook `core/files/manager.php:265` |
| `elementor_disable_color_schemes` | string | `yes`\|empty; clears CSS | `includes/settings/settings.php:174` |
| `elementor_disable_typography_schemes` | string | `yes`\|empty; clears CSS | `includes/settings/settings.php:182` |
| `elementor_google_font` | string | `1`\|`0` | `includes/fonts.php:1872` |
| `elementor_font_display` | string | `auto`\|`block`\|`swap`\|`fallback`\|`optional` | `includes/fonts.php:1881` |
| `elementor_local_google_fonts` | string | `1`\|`0`; clears CSS | `includes/settings/settings.php:365` |
| `elementor_optimized_image_loading` | string | `1`\|`0`; may be absent until saved | `includes/settings/settings.php:324` |
| `elementor_optimized_gutenberg_loading` | string | `1`\|`0`; may be absent until saved | `includes/settings/settings.php:341` |
| `elementor_lazy_load_background_images` | string | `1`\|`0`; may be absent until saved | `includes/settings/settings.php:353` |
| `elementor_google_maps_api_key` | string | Maps Embed API key | `includes/settings/settings.php:212` |
| `elementor_editor_break_lines` | string | `""`\|`"1"` | `includes/settings/settings.php:235` |

Writes via `update_option` are safe for these; CSS-related options auto-clear cache via `includes/settings/settings.php:466`.

## Kit meta (`_elementor_page_settings`, post array)
Read with `get_post_meta($kit_id, '_elementor_page_settings', true)`; save via `wp_slash` meta update or `Kit::save()` (`core/settings/page/manager.php:31,208`).
- `system_colors` (`_id`,`title`,`color`) — `core/kits/documents/tabs/global-colors.php:106`; `custom_colors` — `:128`.
- `system_typography` / `custom_typography` (`_id`,`title`,`typography_*`) — `core/kits/documents/tabs/global-typography.php:165,187`.
- `container_width` (responsive slider object) — `core/kits/documents/tabs/settings-layout.php:53`; `space_between_widgets` — `:104`; `page_title_selector` — `:137`.
- Breakpoints: `viewport_mobile`, `viewport_mobile_extra`, `viewport_tablet`, `viewport_tablet_extra`, `viewport_laptop`, `viewport_widescreen` (`viewport_` prefix) — `core/breakpoints/manager.php:16`, `core/kits/documents/tabs/settings-layout.php:308`.
- Site identity: `site_name`, `site_description`, `site_logo` — `core/kits/documents/tabs/settings-site-identity.php:69,80,91`; saving `site_name` also updates `blogname` (`:144`).
- `custom_css` tab is registered in free — `core/kits/documents/kit.php:212`; control source `core/kits/documents/tabs/settings-custom-css.php:33`.

## Other post meta
| Key | Type | Notes | Source |
|---|---|---|---|
| `_elementor_data` | JSON string | Slashed JSON; update then clear cache | `core/base/document.php:44`, `:1387` |
| `_elementor_page_settings` | array | Page/Kit settings | `core/base/document.php:43` |
| `_elementor_edit_mode` | string | `builder` | `core/base/document.php:46` |
| `_elementor_template_type` | string | `page`,`section`,`container`,`kit` in free | `core/base/document.php:42` |
| `_elementor_version` | string | Written by save; do not fabricate | `core/base/document.php:1446` |
| `_elementor_css` | array | Generated; read-only | `core/files/css/post.php:30` |
| `_elementor_element_cache` | array | Generated; read-only | `core/base/document.php:48` |
| `_elementor_page_assets` | array | Generated; read-only | `core/base/elements-iteration-actions/assets.php:13` |
| `_elementor_conditions` | array | Free floating elements only; Theme Builder is Pro | `modules/floating-buttons/module.php:275` |

## Safe actions
- Clear all cache: `Elementor\Plugin::$instance->files_manager->clear_cache()` — deletes generated CSS + cache meta (`core/files/manager.php:123`), destructive/regenerable.
- Per-document element cache is `_elementor_element_cache` meta; `Document::delete_cache()` is protected (`core/base/document.php:2000`). Clear all via `clear_cache()`.
- Update Kit: `$kit->save(['settings' => array_replace_recursive($kit->get_settings(), $settings)])` — `save()` replaces the settings array but clears cache (`core/kits/documents/kit.php:81,105`); `update_settings()` merges recursively but does not clear cache (`core/base/document.php:919`).
- Set experiment: `update_option('elementor_experiment-'.$name, $state)` + clear cache — `core/experiments/manager.php:796,780`.
- Active Kit ID: `kits_manager->get_active_id()` — `core/kits/manager.php:30`.

## REST (verified)
- `GET /wp-json/elementor/v1/settings/{key}` — `manage_options`, key must start with `elementor` (`modules/wp-rest/classes/elementor-settings.php:12`).
- `POST /wp-json/elementor/v1/settings/{key}` — `manage_options`, raw `update_option($key,$value)` (`:50`); no type validation.
- `GET /wp-json/elementor/v1/post` — `edit_posts`, excludes `elementor_library` (`modules/wp-rest/module.php:30`, `modules/wp-rest/classes/post-query.php:155`).
- `GET /wp-json/elementor/v1/term` — `edit_posts` (`modules/wp-rest/module.php:31`); `GET /user` — `list_users` (`:32`).
- Standard `wp/v2` post meta: `_elementor_data`, `_elementor_page_settings`, `_elementor_edit_mode`, `_elementor_template_type` are registered for post types supporting `elementor`; auth is document-editable (`modules/wp-rest/classes/elementor-post-meta.php:14,68,105`).

## Objects / CRUD
- **Kit**: read `elementor_active_kit` → `_elementor_page_settings`; create with Kits Manager (`core/kits/manager.php:129`); update via `Kit::save()`; no elements.
- **Template**: `elementor_library` CPT (`includes/template-library/sources/local.php:40`) + `elementor_library_type` taxonomy (`:45`); list with `WP_Query`; set `_elementor_template_type` + `_elementor_data`.
- **Document**: any post in `elementor_cpt_support`; read `_elementor_data`; write slashed JSON; clear cache.
- Theme Builder types/conditions are **Pro**; free only promotes them (`modules/promotions/theme-builder-promotion.php:13`).

## Gotchas
- `_elementor_data` is a JSON **string**, saved with `wp_slash(wp_json_encode(...))`; writing an array corrupts it.
- `elementor/v1/settings` bypasses sanitization and cache hooks in REST context; use it only for scalar/global options and clear cache explicitly.
- Elementor's `elementor/v1/post` route excludes `elementor_library`; use WP_Query/REST meta for templates.
- `container` experiment default is new-site active / upgraded-site inactive; read actual state (`core/experiments/manager.php:372`).
- After any layout/Kit/meta write, purge Elementor cache and any page cache (LiteSpeed/etc.).
