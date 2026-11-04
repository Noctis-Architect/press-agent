# WooCommerce (free) - capability map

Slug `woocommerce`; main file `woocommerce/woocommerce.php`; analyzed **11.2.1** (header `woocommerce.php:5`; readme stable tag 11.2.0, WP 7.0+, PHP 7.4+ - `readme.txt:4,6,7`). All paths below are relative to `vendor/plugins/woocommerce/`.

## Storage
- Settings are options `woocommerce_*`; gateways use one array option `woocommerce_{gateway_id}_settings` (`includes/abstracts/abstract-wc-settings-api.php:106`).
- Products, variations, coupons, legacy orders: CPTs `product`, `product_variation`, `shop_coupon`, `shop_order`; product data in post meta + `wc_product_meta_lookup` (`includes/class-wc-post-types.php:367,423,439,502`).
- HPOS orders: tables `wc_orders`, `wc_order_addresses`, `wc_order_operational_data`, `wc_orders_meta` (`src/Internal/DataStores/Orders/OrdersTableDataStore.php:192,203,214,225`).
- Shipping: `woocommerce_shipping_zones`, `_zone_locations`, `_zone_methods` (`includes/data-stores/class-wc-shipping-zone-data-store.php:28,163,249`).

## Settings (option / shape / write)
- `woocommerce_currency` string - store currency code (`includes/admin/settings/class-wc-settings-general.php:331`); read via `get_woocommerce_currency()` (`includes/wc-core-functions.php:495`). safe.
- `woocommerce_currency_pos` string - left/right/left_space/right_space (`.../class-wc-settings-general.php:342`). safe.
- `woocommerce_price_num_decimals` int - display decimals, default 2 (`.../class-wc-settings-general.php:378`). safe.
- `woocommerce_default_country` string - `US:CA` style (`.../class-wc-settings-general.php:164`). safe.
- `woocommerce_store_address`, `_address_2`, `_city`, `_postcode` strings - store address block (`.../class-wc-settings-general.php:137,146,155,173`). safe.
- `woocommerce_calc_taxes` yes/no (`.../class-wc-settings-general.php:287`); `woocommerce_prices_include_tax` yes/no (`includes/admin/settings/views/settings-tax.php:24`). safe.
- `woocommerce_enable_coupons` yes/no (`.../class-wc-settings-general.php:296`). safe.
- `woocommerce_manage_stock` yes/no (`.../class-wc-settings-products.php:256`); `woocommerce_weight_unit` (`:134`), `woocommerce_dimension_unit` (`:151`). safe.
- `woocommerce_enable_guest_checkout` yes/no (`.../class-wc-settings-accounts.php:66`). safe.
- `woocommerce_cart_page_id`, `woocommerce_checkout_page_id`, `woocommerce_myaccount_page_id`, `woocommerce_terms_page_id` ints (`.../class-wc-settings-advanced.php:93,113,133,152`). safe, but UI enforces distinct cart/checkout/my-account pages (`:575-583`).
- `woocommerce_email_from_name` / `_from_address` (`.../class-wc-settings-emails.php:131,143`); `woocommerce_allow_tracking` yes/no (`.../class-wc-settings-advanced.php:371`). safe.
- `woocommerce_{gateway}_settings` array: `enabled`=yes/no + gateway keys, e.g. `woocommerce_bacs_settings`, `woocommerce_paypal_settings`; may contain secrets (`includes/abstracts/abstract-wc-settings-api.php:106`, reader `:280`). safe with care.
- `woocommerce_custom_orders_table_enabled` yes/no - HPOS authoritative switch (`src/Internal/DataStores/Orders/CustomOrdersTableController.php:37`). **not safe** to flip directly; requires sync/table setup; read check at `:230`.
- Settings save path: `WC_Admin_Settings::save_fields()` sanitizes, resolves page conflicts and `update_option()` (`includes/admin/class-wc-admin-settings.php:936,1069`). Direct `update_option()` skips validation.

## Product meta (post; write through setters)
- `_sku` string, unique; lookup `wc_get_product_id_by_sku()` (`includes/data-stores/class-wc-product-data-store-cpt.php:37`; `includes/wc-product-functions.php:1164`).
- `_regular_price`, `_sale_price`, `_sale_price_dates_from/_to` (`...class-wc-product-data-store-cpt.php:40-43`); `_price` is derived - **do not write** (`:39`).
- `_manage_stock`, `_stock`, `_stock_status`, `_backorders`, `_low_stock_amount` (`...:47-51`); `_stock_status`=instock|outofstock|onbackorder.
- `_weight`, `_length`, `_width`, `_height` (`...:53-56`); `_tax_status`, `_tax_class` (`:45-46`); `_virtual`, `_downloadable` (`:62-63`).
- `_thumbnail_id` int, `_product_image_gallery` comma IDs (`...:72,74`); `_product_attributes` serialized (write only via `set_attributes()`, `:61`); `total_sales` derived (`:44`).
- Setters/getters: `set_regular_price()` `includes/abstracts/abstract-wc-product.php:936`, `set_sale_price():947`, `set_manage_stock():1048`, `set_stock_quantity():1059`, `set_stock_status():1069`, `set_backorders():1086`, `save():1546`, `delete():1616`.

## Actions
- Create simple product: `new WC_Product_Simple(); set_name/set_regular_price/set_sku; save()` (`includes/class-wc-product-simple.php:17`; save `abstract-wc-product.php:1546`).
- Update price/stock: `wc_get_product($id)` + setters + `save()` (`abstract-wc-product.php:936,1059,1546`). Use `WC_Product_Variable::sync($p,true)` after variation changes (`includes/class-wc-product-variable.php:710`).
- Create order: `wc_create_order()`, `add_product()`, `calculate_totals()`, `save()` (`includes/wc-core-functions.php:89`; `abstract-wc-order.php:2093,2494,274`).
- Change status: `$order->update_status('completed','note')` - no `wc-` prefix required (`includes/class-wc-order.php:402`); may send emails.
- Add note: `$order->add_order_note($note,$customer_visible)` (`includes/class-wc-order.php:2080`); mark paid: `payment_complete()` (`:138`).
- Clear caches: `wc_delete_product_transients($id)` (`includes/wc-product-functions.php:130`), `wc_delete_shop_order_transients($order)` (`includes/wc-order-functions.php:502`).
- Rebuild product lookup: `wc_update_product_lookup_tables()` (`includes/wc-product-functions.php:2171`); usually automatic on save (`class-wc-product-data-store-cpt.php:964-969`).

## REST (WooCommerce's own; PressAgent should prefer PHP CRUD)
- `/wc/v3/products` GET/POST; `/wc/v3/products/{id}` GET/PUT/PATCH/DELETE; `/wc/v3/products/batch` POST (`includes/rest-api/Controllers/Version2/class-wc-rest-products-v2-controller.php:137,163,217`).
- `/wc/v3/orders` GET/POST; `/wc/v3/orders/{id}` GET/PUT/PATCH/DELETE; `/wc/v3/orders/batch` POST (`.../class-wc-rest-orders-v2-controller.php:71,91,131`).
- `/wc/v3/settings/{group_id}` GET/POST/PUT (`.../class-wc-rest-setting-options-v2-controller.php:41`). Permissions use `wc_rest_check_post_permissions()` (`includes/wc-rest-functions.php:229`); settings need `manage_woocommerce`.

## Objects
- **product** (CPT): list `wc_get_products(['status'=>..,'limit'=>..,'paginate'=>true])` (`includes/wc-product-functions.php:39`); read `wc_get_product()` (`:71`); create/update via `WC_Product_Simple`/setters/save; delete `delete($force)` (`abstract-wc-product.php:1616`).
- **order** (CPT or HPOS): list `wc_get_orders([...])` (`includes/wc-order-functions.php:37`); read `wc_get_order()` (`:89`); create `wc_create_order()`; status via `update_status()`; HPOS-safe only through these APIs.
- **coupon** (CPT `shop_coupon`): `new WC_Coupon($code_or_id)` (`includes/class-wc-coupon.php:111`); setters `set_discount_type():588`, `set_amount():617`, `set_date_expires():651`; `save()` inherited from `WC_Data`.
- **shipping_zone** (tables): `WC_Shipping_Zones::get_zones()` (`includes/class-wc-shipping-zones.php:283`), `new WC_Shipping_Zone($id)` (`includes/class-wc-shipping-zone.php:17`), `save():258`, `add_shipping_method():416`.

## Detection
- `class_exists('WooCommerce')` + `defined('WC_VERSION')`; version at `WC()->version` / `WC_VERSION` (`includes/class-woocommerce.php:63,546`).

## Pro notes (documented only, not in source)
- Core WooCommerce is free; Subscriptions, Bookings, Memberships, WooPayments and marketplace gateways are separate paid plugins (`readme.txt:25,33`). Their settings/objects must be verified separately.

## Gotchas
- `wc_get_product()` must run after `woocommerce_init`, `woocommerce_after_register_taxonomy`, `woocommerce_after_register_post_type`; `wc_get_order()` after post types (`includes/wc-product-functions.php:71`; `includes/wc-order-functions.php:89`).
- Never write `_price`/`total_sales`; product save updates `wc_product_meta_lookup` and invalidates `product_{id}` cache (`class-wc-product-data-store-cpt.php:964-969,1206-1209`).
- Out-of-band writes need the transient clears above; direct `update_option()` skips sanitizers and page-ID conflict handling used by `save_fields()` (`class-wc-admin-settings.php:936,1069`).
- HPOS: use `wc_get_orders()`; do not query `shop_order` posts when HPOS is authoritative. The switch option is only a flag, not a migration (`CustomOrdersTableController.php:230`).
- Order status changes can trigger customer emails and stock reduction; guard on `manage_woocommerce`/order caps.
