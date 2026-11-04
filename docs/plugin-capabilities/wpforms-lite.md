# WPForms Lite 2.0.3.1 — PressAgent capability map

Plugin file `wpforms-lite/wpforms.php`; version `WPFORMS_VERSION` (`wpforms.php:62`); handler `WPForms_Form_Handler` (`includes/class-form.php:15`); accessor `wpforms()->obj('form')` (`src/WPForms.php:512`).

## Detection
- `defined('WPFORMS_VERSION')` / `function_exists('wpforms')` — `wpforms.php:62`, `src/WPForms.php:610`.
- Plugin basename `WPFORMS_PLUGIN_FILE` = `wpforms-lite/wpforms.php` — `wpforms.php:89`.

## Storage
- Form CPT `wpforms`; template CPT `wpforms-template` (`includes/class-form.php:29`); registration at `:110`, non-public, `show_ui=false`, no `show_in_rest` (`:93-99`).
- Form data is **one JSON document in `post_content`**, encoded with `wp_slash(wp_json_encode($data))` (`includes/functions/forms.php:108`) and decoded with `json_decode` + `wp_unslash` (`:77`).
- `post_title` mirrors `settings.form_title`; `post_excerpt` mirrors `settings.form_desc` (`includes/class-form.php:699`).
- Plugin settings: option `wpforms_settings` array (`includes/admin/class-settings.php:191`); version options `wpforms_version` / `wpforms_version_lite` (`includes/class-install.php:179`).
- No local entries in Lite; counters in post meta `wpforms_entries_count` (`lite/wpforms-lite.php:938`) and option `wpforms_entries_count_daily` (`src/Lite/Reports/EntriesWindow.php:21`).
- Embed locations cache: post meta `wpforms_form_locations` (`src/Forms/Locator.php:31`).

## Settings
| Key | Shape | Notes | Source |
|---|---|---|---|
| `wpforms_settings` | array | One option; merge before writing | `includes/admin/class-settings.php:191` |
| `wpforms_settings[disable-css]` | int | 1 base+theme, 2 base only, 3 none; default 1 | `includes/admin/class-settings.php:413` |
| `wpforms_settings[global-assets]` | bool | Load assets site-wide | `:439` |
| `wpforms_settings[gdpr]` | bool | GDPR enhancements | `:453` |
| `wpforms_settings[delete-spam-entries]` | int | 7/15/30/90 days; default 90 | `:628` |
| `wpforms_settings[hide-announcements]` | bool | Hide announcements | `:642` |
| `wpforms_settings[hide-admin-bar]` | bool | Hide admin bar menu | `:649` |
| `wpforms_settings[uninstall-data]` | bool | Delete all data on uninstall | `:656` |
| `wpforms_settings[validation-required]` | string | Required-field message | `:489` |
| `wpforms_settings[currency]` | string | ISO code, default USD | `src/Admin/Settings/Payments.php:86` |
| `wpforms_version` | string | Installed version; read-only | `includes/class-install.php:179` |
| `wpforms_version_lite` | string | Lite version; read-only | `includes/class-install.php:181` |
| `wpforms_entries_count_daily` | array | Rolling counters; read-only | `src/Lite/Reports/EntriesWindow.php:21` |
| `wpforms_license` | array | License type/key; Pro-managed, read-only | `includes/functions/access.php:75` |

Save settings with `wpforms_update_settings(array_merge(get_option('wpforms_settings', []), $changes))` (`includes/functions/forms.php:181`); the admin handler merges per view (`includes/admin/class-settings.php:191`). It fires `wpforms_settings_updated`.

## Form JSON shape (`post_content` after `wpforms_decode()`)
- Top level: `field_id` (next numeric id, string/int), `fields` (map numeric id => field), `settings`, `meta`.
- Fields: `id`, `type` (`name`, `email`, `textarea`, `text`, `select`, `radio`, `checkbox`, `number`, `html`...), `label`, `description`, `required`, `size`, `choices`, `css`, plus type-specific keys — examples `includes/templates/class-simple-contact-form.php:31-52`, defaults `includes/fields/class-base.php:36`.
- Settings: `form_title`, `form_desc`, `submit_text`, `submit_text_processing`, `notification_enable`, `notifications` (`1`=>`email`,`subject`,`sender_name`,`sender_address`,`replyto`,`message`), `confirmations` (`type`,`message`,`page`,`redirect`), `antispam_v3`, `ajax_submit` — `includes/templates/class-simple-contact-form.php:57-84`; consumed at `includes/class-process.php:1565,1935`.
- `meta`: arbitrary plugin metadata (`category`, `template`); use `get_meta`/`update_meta` (`includes/class-form.php:1294,1333`) instead of editing directly.

## Safe actions
| Action | Implementation | Safety | Source |
|---|---|---|---|
| List forms | `wpforms()->obj('form')->get('', ['post_status'=>'any'])` | read | `includes/class-form.php:216` |
| Get form | `get($id, ['content_only'=>true])` | read | `includes/class-form.php:307` |
| Create form | `add($title, $args, $data)` then `update($id, $form_data)` | write | `includes/class-form.php:535` |
| Update form | `update($id, $form_data)` | write | `includes/class-form.php:671` |
| Delete form | `delete($ids)` (permanent) | destructive | `includes/class-form.php:480` |
| Duplicate form | `duplicate($ids)` | write | `includes/class-form.php:921` |
| Update form meta | `update_meta($id,$key,$value)` | write | `includes/class-form.php:1333` |
| Save settings | `wpforms_update_settings($merged)` | write | `includes/functions/forms.php:181` |
| Next field id | `next_field_id($id)` | write | `includes/class-form.php:1233` |

## REST
- **No form CRUD REST routes.** The `wpforms` CPT is `public=false`, `show_ui=false`, and does not set `show_in_rest` (`includes/class-form.php:93-110`). There is no `wp/v2/wpforms` endpoint.
- Builder saves go through admin-ajax `wp_ajax_wpforms_save_form`, nonce `wpforms-builder`, plus `wpforms_current_user_can('edit_forms')` (`includes/admin/ajax-actions.php:17,24,124`). Not suitable for remote MCP use.
- REST routes that exist are for Setup Wizard/integrations, not form management (`src/SetupWizard/RestApi.php:118`).

## Objects
- **`wpforms_form`** — CPT `wpforms`; data in slashed JSON `post_content`; list/read/create/update/delete through `wpforms()->obj('form')`; title duplicated in post_title and `settings.form_title`.
- **`wpforms_template`** — CPT `wpforms-template` (`src/Admin/Forms/UserTemplates.php:100`); same JSON format; list with `post_type=wpforms-template`.
- **Entries** — Pro-only; no local entry object exists in Lite (see `pro_notes`).

## Gotchas
- Never write form `post_content` as plain JSON: `wpforms_encode()` adds `wp_slash()` because `wp_update_post()` unslashes (`includes/functions/forms.php:96-115`). Use `update()`/`add()`.
- `get()` returns only published forms unless `post_status` is overridden (`includes/class-form.php:238`); trashed/draft forms need `post_status=any`.
- `update()` replaces the whole form data; send `fields`, `settings`, `meta`, `field_id`. It preserves selected meta/field meta but not omitted settings (`includes/class-form.php:671`).
- `wpforms_update_settings()` replaces the whole option (`includes/functions/forms.php:181`); always merge first.
- Lite stores **no entry content** and ignores all but the first notification (`includes/class-process.php:1948`); its Entries page is sample/upsell (`lite/wpforms-lite.php:832`).
- Capability checks in Lite collapse to `manage_options` (`src/Access/Capabilities.php:44`), so editor-level users cannot manage forms even if they can edit pages.
- `field_id` must stay ahead of the largest field key; use `next_field_id()` (`includes/class-form.php:1233`).
- `settings.notifications[1]` is the only notification used in Lite; keep `notification_enable=1` and a valid `email` or mail is skipped (`includes/class-process.php:1900,1935`).
- `wpforms_setting()` returns its default when the stored value is empty/0 (`includes/functions/forms.php:153`), so read `wpforms_settings` directly when a false/zero value matters.
- `wpforms_form_locations` is a plugin-managed cache; do not use it as authoritative embed state.
