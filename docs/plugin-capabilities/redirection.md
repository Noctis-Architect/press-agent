# Redirection (free) - capability map

Slug `redirection`; main file `redirection/redirection.php`; analyzed **5.10.1** (header `redirection.php:5`; readme stable 5.10.1, WP 6.7+, PHP 7.4+ - `readme.txt:4-7`; plugin constant `REDIRECTION_VERSION` `redirection.php:18`). Paths relative to `vendor/plugins/redirection/`.

## Storage
- Settings: one array option `redirection_options` (`models/options.php:49`); defaults merged on read (`:260-295`).
- Objects: tables `{prefix}redirection_items`, `redirection_groups`, `redirection_logs`, `redirection_404` (`includes/database/schema/class-latest.php:150-153`; schemas `:223,255,274,301`).
- DB schema version target `REDIRECTION_DB_VERSION` = 4.2 (`redirection.php:16`), stored in `redirection_options['database']` (written after install `schema/class-latest.php:48`).
- Modules (WordPress/404/logging) are nested in `redirection_options['modules']` (`models/options.php:274`).

## Settings (`redirection_options[...]`)
- `auto_target` string, default `''`; sanitized `sanitize_text_field` (`models/options.php:268,408-409`). safe.
- `expire_redirect` int, default 7; clamp -1..60 (`models/options.php:269,436-437`). safe.
- `expire_404` int, default 7; clamp -1..60 (`models/options.php:270,440`). safe.
- `redirect_cache` int, default 1; allowed -1,0,1,24,168, fallback 1 (`models/options.php:275,448`). safe.
- `monitor_post` group ID, default 0; validated group else first group/0 (`models/options.php:265,391-403`). safe.
- `monitor_types` array of public post types; filtered against `red_get_post_types()` (`models/options.php:266,370-379`). safe.
- `associated_redirect` string URL (`models/options.php:267,382-389`). safe.
- `https` bool, default false; set false when `REDIRECTION_DISABLE` active (`models/options.php:281,210,430`). safe.
- `headers` array; sanitized by `Red_Http_Headers` (`models/options.php:282,488`). safe.
- `aliases` array (max 20 domains) and `preferred_domain` `''|www|nowww` (`models/options.php:285-286,493,510`). safe.
- `rest_api` int 0/1/3 (`models/options.php:280,366-367`); `track_hits` bool (`:273,430`). safe.
- Write path: `red_set_options([...])` / `Red_Options::save()` performs merge+sanitize+`update_option()` and resets the in-memory cache (`redirection-settings.php:48`; `models/options.php:306-314`). Direct `update_option()` bypasses this. Internal `database`, `modules`, `token` are **not safe** to write.

## Actions
- List: `Red_Item::get_all()` (`models/redirect/redirect.php:516`); read: `get_by_id($id)` (`:570`); URL match: `get_for_url($url)` (`:406`).
- Create: `Red_Item::create($details)` (`:613`); details: `url`, `action_type` (`url|error|nothing|random|pass`, `models/action.php:83,111-117`), `action_code`, `action_data` (`['url'=>target]` for url, `matches/url.php:31-42`), `group_id`, `match_type` (default `url`, `redirect-sanitizer.php:116`), `title`, `regex` (`:74`).
- Update: `$item->update($details)` (`:662`); delete: `$item->delete()` (`:598`); enable/disable: `$item->enable()`/`disable()` (`:841,854`); bulk delete: `Red_Item::delete_all($params)` (`:1031`).
- Groups: `Red_Group::create($name,$module_id,$enabled)` (`models/group.php:235`), `$group->update([...])` (`:266`), `$group->delete()` cascades to redirects (`:292`).
- 404 lookup: `Red_404_Log::get_filtered(['filterBy'=>['url-exact'=>$url], 'per_page'=>N,'page'=>P])` (`models/log/log.php:310`; URL filters `:466-469`; 5..200 per page `:409-411`).
- Delete 404s/logs: `Red_404_Log::delete_all($params)` / `Red_Redirect_Log::delete_all($params)` (`models/log/log.php:269`); single `delete($id)` (`:257`).
- Cache: `Red_Module::flush($group_id)` after direct DB edits (`models/module.php:96`).

## REST (`redirection/v1`)
- `/redirect` GET/POST; `/redirect/{id}` POST; `/bulk/redirect/{delete|enable|disable|reset}` POST (`includes/api/route/class-redirect.php:30,50,83`).
- `/404` GET; `/bulk/404/delete` POST (`includes/api/route/class-error.php:28,42`).
- `/group` GET/POST; `/group/{id}` POST; `/group/dropdown` GET; `/bulk/group/{delete|enable|disable}` POST (`includes/api/route/class-group.php:30,53,66,80`).
- `/setting` GET/POST (`includes/api/route/class-settings.php:40`); `/log` GET, `/bulk/log/delete` POST (`includes/api/route/class-log.php:28,42`).
- Permissions: capability constants `redirection_cap_redirect_manage/add/delete`, `_group_*`, `_404_*`, `_log_*`, `_option_manage`; default `manage_options` (`redirection-capabilities.php:44-80`).

## Objects
- **redirect** (table): list/read/create/update/delete as above. Validation in `Red_Item_Sanitize::get()` checks group (`redirect-sanitizer.php:256`), match type (`:158`), action type (`:164`), code (`:232`).
- **redirect_group** (table): `Red_Group::get_all()` (`models/group.php:158`), `get($id)` (`:134`); delete cascades.
- **404_log** (table): read/filter only for PressAgent; delete via `Red_404_Log::delete/delete_all`. Columns: url, domain, agent, referrer, http_code, request_method, request_data, ip (`schema/class-latest.php:301-317`).
- **redirect_log** (table): read/filter via `Red_Redirect_Log::get_filtered()`; delete via inherited `Red_Log` methods. Columns include sent_to, redirection_id, redirect_by (`schema/class-latest.php:274-279`).

## Detection
- `defined('REDIRECTION_VERSION')`, `defined('REDIRECTION_FILE')`, `class_exists('Red_Item')`; version `REDIRECTION_VERSION` (`redirection.php:17-18`).

## Pro notes
- None. The readme states there is no premium version and no paid feature gate (`readme.txt:111`).

## Gotchas
- Check `Redirection\Database\Status::needs_updating()` before use; it compares `redirection_options['database']` to 4.2 and triggers staged table upgrades (`includes/database/class-status.php:137,156`).
- `Red_Item::create()`/`update()` return `WP_Error` on invalid URL/group/match/action; always check (`models/redirect/redirect.php:613-660,662-690`).
- `action_data` is serialized for non-URL actions and for URL matches; pass arrays, not pre-serialized strings (`redirect-sanitizer.php:171-175`).
- Direct SQL writes bypass sanitizer + `Red_Module::flush()`, leaving stale redirect cache (`models/module.php:96`; `models/redirect/redirect-cache.php:124`).
- Bulk/group deletes are irreversible and group delete removes child redirects; confirm before calling (`models/group.php:292`; `models/redirect/redirect.php:1031`).
- 404/redirect logs are pruned by `expire_404`/`expire_redirect` (default 7 days); absence may be retention.
- Default capability is `manage_options`; delegated users may only hold fine-grained `redirection_cap_*` caps (`redirection-capabilities.php:44,94`).
