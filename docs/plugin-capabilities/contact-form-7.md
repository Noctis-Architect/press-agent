# Contact Form 7 6.2.4.1 (free) — PressAgent capability map

Plugin file `contact-form-7/wp-contact-form-7.php`; version `WPCF7_VERSION` (`wp-contact-form-7.php:17`); class `WPCF7_ContactForm` (`includes/contact-form.php:7`). Requires WP 7.1 / PHP 8.3 (`readme.txt`).

## Detection
- `defined('WPCF7_VERSION')` / `class_exists('WPCF7_ContactForm')` — `wp-contact-form-7.php:17`, `includes/contact-form.php:7`.
- Basename `WPCF7_PLUGIN_BASENAME` = `contact-form-7/wp-contact-form-7.php` — `wp-contact-form-7.php:21`.

## Storage
- CPT name: `wpcf7_contact_form` — `includes/contact-form.php:10`; registered non-public with page-based capabilities — `:50`.
- Properties are post meta: `_form` (string), `_mail` (array), `_mail_2` (array), `_messages` (array), `_additional_settings` (string) — `includes/contact-form.php:279-283`; retrieved via the `_<name>` convention — `:335`; saved in `save()` — `:1285`.
- `post_content` is a flattened copy of all properties, **not** the authoritative source — `includes/contact-form.php:1265`.
- `_locale` and `_hash` — `includes/contact-form.php:217,218`; hash generated once on save — `:1294`.
- Global option `wpcf7` is a single array; `WPCF7::get_option()/update_option()` merge individual keys — `load.php:99,120`.
- Config errors cached in `_config_validation` — `includes/config-validator/validator.php:356`.

## Global option
| Key | Shape | Notes | Source |
|---|---|---|---|
| `wpcf7` | array | Version, `bulk_validate`, and integration keys (`recaptcha`, `turnstile`, `stripe`, `sendinblue`, `constant-contact`); integration keys exist only after configuration | `load.php:99`, `:206`, `:209` |

Write only through `WPCF7::update_option($name,$value)` (`load.php:120`) to preserve other keys; whole-option writes are unsafe.

## Form properties (post meta)
| Meta | Type | Contents / notes | Source |
|---|---|---|---|
| `_form` | string | Raw form template with form tags (`[text* your-name]`) | `includes/contact-form.php:279` |
| `_mail` | array | `active`, `subject`, `sender`, `recipient`, `body`, `additional_headers`, `attachments`, `use_html`, `exclude_blank` | `includes/contact-form.php:280`; keys `includes/contact-form-functions.php:388` |
| `_mail_2` | array | Same keys; `active` defaults false | `includes/contact-form.php:281` |
| `_messages` | array | Core keys `mail_sent_ok`, `mail_sent_ng`, `validation_error`, `spam`, `accept_terms`, `invalid_required`, `invalid_too_long`, `invalid_too_short` | `includes/contact-form.php:282`; keys `includes/contact-form-template.php:164` |
| `_additional_settings` | string | Newline `name: value`; verified keys `demo_mode`, `skip_mail`, `subscribers_only`, `do_not_store`, `acceptance_as_validation`, `doi`, `flamingo_*` | `includes/contact-form.php:283` |
| `_locale` | string | Validated locale | `includes/contact-form.php:217` |
| `_hash` | string | Read-only unique hash | `includes/contact-form.php:218`, `:1294` |
| `_config_validation` | array | Generated validator cache; read-only | `includes/config-validator/validator.php:356` |
| `_old_cf7_unit_id` | int | Legacy migration ID; read-only | `includes/contact-form.php:1358` |

## Create/update accurately
```json
POST /wp-json/contact-form-7/v1/contact-forms?context=save
{
  "title": "Contact",
  "locale": "en_US",
  "form": "<label>Name [text* your-name]</label>\n[email* your-email]\n[textarea your-message]\n[submit \"Send\"]",
  "mail": {
    "subject": "[your-subject]",
    "sender": "Site <wordpress@example.com>",
    "recipient": "admin@example.com",
    "body": "[your-name] <[your-email]>\n[your-message]",
    "additional_headers": "Reply-To: [your-email]",
    "attachments": "",
    "use_html": false,
    "exclude_blank": false
  },
  "mail_2": {"active": false},
  "messages": {"mail_sent_ok": "Thank you."},
  "additional_settings": ""
}
```
This mirrors `wpcf7_save_contact_form()` (`includes/contact-form-functions.php:297`) and the admin save fields (`admin/admin.php:316`). `mail.active` is forced true; `mail_2` stays inactive until `active:true`. REST GET returns `form` as `{content,fields}` (`includes/rest-api.php:463`)—send back only `form.content`.

## Safe actions
| Action | Implementation | Safety | Source |
|---|---|---|---|
| List forms | `WPCF7_ContactForm::find($args)` | read | `includes/contact-form.php:79` |
| Get form | `wpcf7_contact_form($id)` | read | `includes/contact-form-functions.php:14` |
| Save form | `wpcf7_save_contact_form($data,$context)`; context `save` persists | write | `includes/contact-form-functions.php:297` |
| Delete form | `$form->delete()` (permanent, no trash) | destructive | `includes/contact-form.php:1333` |
| Validate config | `WPCF7_ConfigValidator::validate()` + `save()` | write | `includes/config-validator/validator.php:348` |

## REST (namespace `contact-form-7/v1`)
| Route | Methods | Permission | Source |
|---|---|---|---|
| `/contact-forms` | GET | `wpcf7_read_contact_forms` → `edit_posts` | `includes/rest-api.php:22` |
| `/contact-forms` | POST | `wpcf7_edit_contact_forms` → `publish_pages` | `includes/rest-api.php:39` |
| `/contact-forms/{id}` | GET | `wpcf7_edit_contact_form` → `publish_pages` | `includes/rest-api.php:56` |
| `/contact-forms/{id}` | POST/PUT/PATCH | `wpcf7_edit_contact_form` → `publish_pages` | `includes/rest-api.php:75` |
| `/contact-forms/{id}` | DELETE | `wpcf7_delete_contact_form` → `publish_pages` | `includes/rest-api.php:91` |
| `/contact-forms/{id}/feedback` | POST | public, multipart only | `includes/rest-api.php:110` |
| `/contact-forms/{id}/feedback/schema` | GET | public | `includes/rest-api.php:121` |
| `/contact-forms/{id}/refill` | GET | public | `includes/rest-api.php:133` |

Capability mapping: `includes/capabilities.php:9`; constants: `WPCF7_ADMIN_READ_CAPABILITY=edit_posts` (`wp-contact-form-7.php:50`), `WPCF7_ADMIN_READ_WRITE_CAPABILITY=publish_pages` (`:54`).

## Object CRUD
- **`cf7_form`** — CPT `wpcf7_contact_form`; list with `find()`, read properties with `get_properties()` (`includes/contact-form.php:369`), create with id `-1`, update with an existing id, delete permanently. Let `save()` write `_form`/`_mail`/`_mail_2`/`_messages`/`_additional_settings`, `_locale`, and `_hash` (`includes/contact-form.php:1285`).

## Gotchas
- **REST writes need `context=save`.** The create/update callbacks pass the request context through; if it is null, `wpcf7_save_contact_form()` does not call `save()` (`includes/rest-api.php:207`, `includes/contact-form-functions.php:356`). Use `?context=save` or body `context=save`.
- REST **read** shape differs from write shape: `form` and `additional_settings` are returned as objects with `content` and parsed fields (`includes/rest-api.php:463`).
- `post_content` is flattened duplicate data (`includes/contact-form.php:1265`); editing it directly has no effect on the live form.
- `_hash` must never be regenerated or overwritten; it is lookup data.
- Form read/edit/delete capabilities map to `edit_posts` (read) and `publish_pages` (write/delete), not `manage_options` (`includes/capabilities.php:9`).
- Deletion is permanent (`wp_delete_post($id,true)`, `includes/contact-form.php:1338`).
- `wpcf7_save_contact_form()` runs config validation separately; invalid config is reported but does not block save (`admin/admin.php:326`).
- Core CF7 stores **no submissions**; use Flamingo or another integration if entries are required.
