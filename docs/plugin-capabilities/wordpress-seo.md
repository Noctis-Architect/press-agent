# Yoast SEO (free) — 28.8

- Plugin file: `vendor/plugins/wordpress-seo/wp-seo.php` (header version 28.8)
- readme stable tag 28.8, Requires PHP 7.4, readme "Requires at least: 7.0"; main file header says WP 7.0.
- Detection: `defined('WPSEO_VERSION')`, `class_exists('WPSEO_Options')`, `function_exists('YoastSEO')` — `wp-seo-main.php:18`.

## Storage map

| What | Where | Notes |
|---|---|---|
| Main settings | option `wpseo` | `inc/options/class-wpseo-option-wpseo.php:18` |
| Title/meta templates | option `wpseo_titles` | `inc/options/class-wpseo-option-titles.php:20` |
| Social profiles/cards | option `wpseo_social` | `inc/options/class-wpseo-option-social.php:18` |
| Multisite defaults | option `wpseo_ms` | `inc/options/class-wpseo-option-ms.php:21` |
| Term SEO | option `wpseo_taxonomy_meta` | **not term_meta**; `[taxonomy][term_id][wpseo_*]`, `inc/options/class-wpseo-taxonomy-meta.php:18` |
| llms.txt | options `wpseo` + `wpseo_llmstxt` | `inc/options/class-wpseo-option-llmstxt.php:20` |
| Post SEO | post_meta `_yoast_wpseo_*` | prefix `inc/class-wpseo-meta.php:52` |
| Runtime SEO | custom indexable tables | rebuilt by `wp yoast index`; not options |

Write path: `WPSEO_Options::set($key,$value,$group)` `inc/options/class-wpseo-options.php:324`; it merges one key and calls `update_option()` via `save_option()` `:516`. Validation runs through `sanitize_option_{option}` `inc/options/class-wpseo-option.php:186`.

## High-value settings

| Option | Field(s) | Default | Source |
|---|---|---|---|
| `wpseo_titles` | `separator` | `sc-dash` | `class-wpseo-option-titles.php:34` |
| `wpseo_titles` | `title-home-wpseo`, `metadesc-home-wpseo` | `%%sitename%% %%page%% %%sep%% %%sitedesc%%`, empty | `:35`, `:50` |
| `wpseo_titles` | `title-{post_type}`, `metadesc-{post_type}`, `noindex-{post_type}`, `display-metabox-pt-{post_type}` | `%%title%% %%page%% %%sep%% %%sitename%%`, empty, false, true | `:312`–`:315` |
| `wpseo_titles` | `schema-page-type-{post_type}`, `schema-article-type-{post_type}` | `WebPage`, `Article`/`None` | `:317`, `:318` |
| `wpseo_titles` | `title-tax-{taxonomy}`, `metadesc-tax-{taxonomy}`, `noindex-tax-{taxonomy}` | `%%term_title%% Archives ...`, empty, false | `:348`, `:349`, `:352` |
| `wpseo_titles` | `noindex-author-wpseo`, `noindex-author-noposts-wpseo`, `noindex-archive-wpseo` | false, true, true | `:56`–`:58` |
| `wpseo_titles` | `disable-author`, `disable-date`, `disable-attachment` | false, false, true | `:60`, `:61`, `:63` |
| `wpseo_titles` | `breadcrumbs-enable`, `breadcrumbs-sep`, `breadcrumbs-home` | true, `»`, empty | `:69`, `:73`, `:70` |
| `wpseo_titles` | `company_or_person`, `company_name`, `company_logo`, `company_logo_id`, `website_name`, `person_name` | `company`, empty, empty, 0, empty, empty | `:86`, `:84`, `:80`, `:81`, `:75`, `:76` |
| `wpseo` | `enable_xml_sitemap`, `enable_index_now`, `enable_text_link_counter`, `enable_cornerstone_content` | all true | `class-wpseo-option-wpseo.php:59`, `:61`, `:60`, `:58` |
| `wpseo` | `disableadvanced_meta`, `enable_headless_rest_endpoints`, `remove_generator`, `remove_emoji_scripts` | true, true, false, false | `:43`, `:44`, `:113`, `:114` |
| `wpseo` | `content_analysis_active`, `keyword_analysis_active`, `inclusive_language_analysis_active`, `enable_admin_bar_menu` | true, true, false, true | `:54`–`:57` |
| `wpseo` | `enable_llms_txt` | false | `:152` |
| `wpseo_social` | `facebook_site`, `twitter_site`, `instagram_url`, `linkedin_url`, `youtube_url`, `other_social_urls` | empty / `[]` | `class-wpseo-option-social.php:29`, `:43`, `:30`, `:31`, `:45`, `:47` |
| `wpseo_social` | `og_default_image`, `og_default_image_id`, `twitter_card_type`, `opengraph`, `twitter` | empty, empty, `summary_large_image`, true, true | `:33`, `:34`, `:44`, `:39`, `:42` |
| `wpseo_llmstxt` | `llms_txt_selection_mode`, `about_us_page`, `contact_page`, `terms_page`, `privacy_policy_page`, `shop_page`, `other_included_pages` | `auto`, 0…0, `[]` | `class-wpseo-option-llmstxt.php:30`–`:36` |
| `wpseo_taxonomy_meta` | `[tax][term_id]` fields: `wpseo_title`, `wpseo_desc`, `wpseo_noindex`, `wpseo_focuskw`, `wpseo_opengraph-title`, `wpseo_twitter-title` | empty / `default` | `class-wpseo-taxonomy-meta.php:54`–`:72` |

## Post / term / user meta

| Key | Object | Type | Notes / source |
|---|---|---|---|
| `_yoast_wpseo_title` | post | string | title template, `inc/class-wpseo-meta.php:107` |
| `_yoast_wpseo_metadesc` | post | string | meta description, `:113` |
| `_yoast_wpseo_focuskw` | post | string | single free keyphrase, `:101` |
| `_yoast_wpseo_canonical` | post | string | URL, `:177` |
| `_yoast_wpseo_meta-robots-noindex` | post | string | `0` default, `1` noindex, `2` index, `:147` |
| `_yoast_wpseo_meta-robots-nofollow` | post | string | `0`/`1`, `:156` |
| `_yoast_wpseo_meta-robots-adv` | post | string | comma list `noimageindex,noarchive,nosnippet`, `:164`, validator `:549` |
| `_yoast_wpseo_opengraph-title` / `-description` / `-image` | post | string | registered only if `wpseo_social.opengraph`, `:265`–`:267` |
| `_yoast_wpseo_twitter-title` / `-description` / `-image` | post | string | registered only if `wpseo_social.twitter`, `:265`–`:267` |
| `_yoast_wpseo_primary_category` | post | int/string | generalized `_yoast_wpseo_primary_{taxonomy}`, `inc/class-wpseo-primary-term.php:44`, `:68` |
| `_yoast_wpseo_schema_page_type` | post | string | Schema_Types::PAGE_TYPES, `:188` |
| `_yoast_wpseo_schema_article_type` | post | string | Schema_Types::ARTICLE_TYPES, `:192` |
| `_yoast_wpseo_is_cornerstone` | post | string | `1`/`0`, `:141` |
| `wpseo_noindex_author` | user | string | `on` = noindex, `src/user-meta/framework/custom-meta/noindex-author.php:45` |
| `wpseo_title`, `wpseo_metadesc` | user | string | author archive templates, `author-title.php:45`, `author-metadesc.php:45` |
| `wpseo_taxonomy_meta` fields | term | string | **option, not term_meta**; use `WPSEO_Taxonomy_Meta::set_value()`, `:466` |

## Actions

| Action | Implementation | Safety | Source |
|---|---|---|---|
| `update_option_setting` | `WPSEO_Options::set($key,$value,$group)` | write | `inc/options/class-wpseo-options.php:324` |
| `save_post_seo_meta` | `WPSEO_Meta::set_value($key,$value,$post_id)` | write | `inc/class-wpseo-meta.php:721` |
| `save_term_seo_meta` | `WPSEO_Taxonomy_Meta::set_value($term_id,$tax,$key,$value)` | write | `inc/options/class-wpseo-taxonomy-meta.php:466` |
| `clear_sitemap_cache` | `WPSEO_Sitemaps_Cache::clear($types); ::clear_queued()` | write | `inc/sitemaps/class-sitemaps-cache.php:272`, `:302` |
| `invalidate_sitemap_cache_storage` | `WPSEO_Sitemaps_Cache_Validator::invalidate_storage($type)` | write | `inc/sitemaps/class-sitemaps-cache-validator.php:138` |
| `index_indexables_wpcli` | `wp yoast index` | write | `src/commands/index-command.php:147` |
| `reindex_indexables_wpcli` | `wp yoast index --reindex --skip-confirmation` | **destructive** (truncates indexables) | `src/commands/index-command.php:216` |
| `update_post_seo_data_ability` | `wp_get_ability('yoast-seo/update-post-seo-data')->execute($input)` | write | `src/abilities/user-interface/abilities/update-post-seo-data-ability.php:88` |
| `update_site_representation_ability` | `wp_get_ability('yoast-seo/update-site-representation')->execute($input)` | write | `src/abilities/user-interface/abilities/update-site-representation-ability.php:61` |

## REST routes (plugin-owned)

| Route | Methods | Permission | Source |
|---|---|---|---|
| `yoast/v1/configuration/site_representation` | POST | `wpseo_manage_options` | `src/routes/first-time-configuration-route.php:125` |
| `yoast/v1/configuration/social_profiles` | POST | `wpseo_manage_options` | `:143` |
| `yoast/v1/indexing/prepare` | POST | `edit_posts` | `src/routes/indexing-route.php:286` |
| `yoast/v1/indexing/posts` | POST | `edit_posts` | `:288` |
| `yoast/v1/indexing/terms` | POST | `edit_posts` | `:291` |

Abilities are registered only on WP 6.9+ (`src/conditionals/abilities-api-conditional.php:13`); they are exposed through the WordPress Abilities API, not a `yoast/v1` route.

## Pro / not verified

- Premium-only (documented-only, not present in this free source): multiple focus keyphrases (`_yoast_wpseo_focuskeywords`), redirects, internal-link suggestions, bot blocker, schema aggregation/NLWeb, AI Generate.
- Free schema types are limited by `Yoast\WP\SEO\Config\Schema_Types`; extra Premium schema types are not verified.

## Gotchas

- Direct `update_post_meta()` bypasses indexable rebuilds; front-end title/schema may stay stale until `wp yoast index` or the ability runs.
- Social post meta is only registered when `wpseo_social.opengraph`/`twitter` is true (`inc/class-wpseo-meta.php:265`); writing anyway leaves orphan meta.
- Never replace whole option arrays. `WPSEO_Options::set()` merges one key and triggers validation; raw `update_option()` with a partial array can drop unrelated fields.
- Dynamic keys exist only after post types/taxonomies are registered (`:312`, `:348`). Read current option before assuming a key is present.
- `wpseo_titles`/`wpseo_social` updates enqueue indexable watchers and cache invalidation; one edit can schedule site-wide work.
- Term SEO lives in `wpseo_taxonomy_meta`, not `wp_termmeta`.
- `clear()` only queues cache invalidation; `clear_queued()` (or shutdown) executes it.
- Do not write internal keys such as `wpseo.version`, `myyoast-oauth`, `semrush_tokens`, `wincher_tokens`.
- Multisite: `wpseo_ms` can override/allowlist keys per site; check it before bulk writes.
