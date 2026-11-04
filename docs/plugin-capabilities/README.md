# Plugin capability map

Every JSON file in this directory is the verified source of truth for `plugin/includes/plugin-registry-data.php`.
Versions and download hashes are recorded in `plugin-sources.json` at the repository root.

| Plugin | Analyzed version | Free source | Registry slug |
|---|---|---|---|
| Yoast SEO | 28.8 | wordpress.org | `wordpress-seo` |
| Rank Math SEO | 1.0.281 | wordpress.org | `seo-by-rank-math` |
| Wordfence Security | 9.0.2 | wordpress.org | `wordfence` |
| All-In-One Security (AIOS) | 5.5.1 | wordpress.org | `all-in-one-wp-security-and-firewall` |
| Elementor | 4.3.6 | wordpress.org | `elementor` |
| LiteSpeed Cache | 7.9.3 | wordpress.org | `litespeed-cache` |
| Contact Form 7 | 6.2.4.1 | wordpress.org | `contact-form-7` |
| WPForms Lite | 2.0.3.1 | wordpress.org | `wpforms-lite` |
| WooCommerce | 11.2.1 | wordpress.org | `woocommerce` |
| Redirection | 5.10.1 | wordpress.org | `redirection` |
| WP Mail SMTP | 4.10.3 | wordpress.org | `wp-mail-smtp` |
| Really Simple Security | 9.8.4 | wordpress.org | `really-simple-ssl` |

## Rules

- Free versions are downloaded from the official WordPress.org API (`scripts/fetch_plugin_sources.py`).
- Premium features are **not** sideloaded, nulled, or redistributed. They are supported only when a licensed copy is installed on the target site and are marked as Pro-only in the notes.
- Each registry entry exposes only verified settings/meta/actions; unknown keys are rejected with the allowed list.
- Re-run the analysis after upgrading a plugin and update both the JSON and the registry together.
