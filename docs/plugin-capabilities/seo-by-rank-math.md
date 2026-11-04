# Rank Math SEO (1.0.281) — capability notes

Source: `vendor/plugins/seo-by-rank-math` (free). Full machine-readable spec: `seo-by-rank-math.json`.

## Storage
- Settings: option groups `rank-math-options-titles`, `rank-math-options-general`, `rank-math-options-sitemap` (React/CMB2). Save via `RankMath\Admin\Option_Center::save_settings()` so field sanitization and rewrite/htaccess side effects run (`includes/admin/class-option-center.php:374`).
- SEO meta: post/term/user meta with `rank_math_*` prefix. Robots are arrays: `rank_math_robots` (`includes/admin/metabox/class-screen.php:302`), advanced robots in `rank_math_advanced_robots`.
- Detection: `defined('RANK_MATH_VERSION')` (`rank-math.php:248`).

## High-value settings
| Option | Fields |
|---|---|
| `rank-math-options-titles` | `homepage_title`, `homepage_description`, `title_separator`, `open_graph_image`, `twitter_card_type`, `robots_global`, `pt_page_title`, `pt_page_description`, dynamic `pt_{post_type}_*` |
| `rank-math-options-general` | `nofollow_external_links`, `nofollow_image_links`, `new_window_external_links`, `strip_category_base`, webmaster verification codes, RSS content |
| `rank-math-options-sitemap` | `items_per_page`, `include_images`, `include_featured_image`, `exclude_posts`, `exclude_terms`, HTML sitemap |

## Post/term meta
| Key | Meaning |
|---|---|
| `rank_math_title` | SEO title |
| `rank_math_description` | Meta description |
| `rank_math_focus_keyword` | Focus keyword(s) |
| `rank_math_canonical_url` | Canonical URL |
| `rank_math_robots` | Array: index/noindex/follow/nofollow/noarchive/nosnippet/noimageindex |
| `rank_math_advanced_robots` | Advanced robots values |
| `rank_math_facebook_title` / `_description` / `_image` | Open Graph overrides |
| `rank_math_twitter_title` / `_description` / `_image` | X/Twitter overrides |
| `rank_math_primary_{taxonomy}` | Primary term ID |

## Modules and redirections
- Module state: option `rank_math_modules`; toggle through `RankMath\Helper::update_modules([$module => 'on'|'off'])` (`includes/class-helper.php:196`).
- Redirections: custom table `{prefix}rank_math_redirections`; list via `RankMath\Redirections\DB::get_redirections()` (`includes/modules/redirections/class-db.php:90`), create/update via `Redirection::from([...])->save()` (`class-redirection.php:199`), delete via `DB::delete()` (`class-db.php:492`).
- 404 monitor: table `{prefix}rank_math_404_logs`; settings `404_monitor_*` in `rank-math-options-general` (`includes/modules/404-monitor/views/options.php`).
- Redirection settings `redirections_*` live in `rank-math-options-general`.

## Gotchas
- Never write settings with raw `update_option()`; use `Option_Center::save_settings()`.
- `rank_math_robots` is an array, not boolean flags.
- Per-post-type keys exist only after the post type is registered.
- Pro modules (Content AI, Analytics, instant indexing, advanced schema) are not exposed.
- Redirection updates must preserve existing sources; passing only id/status to `DB::update()` can wipe fields.
