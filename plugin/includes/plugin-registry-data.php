<?php
/**
 * Supported plugin capabilities.
 *
 * Every entry was verified against the free plugin source listed in
 * plugin-sources.json. Premium-only features are intentionally not exposed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -------------------------------------------------------------------------
 * LiteSpeed Cache: one option per setting; write through API::save_conf().
 * ---------------------------------------------------------------------- */
$litespeed_settings = array();
$litespeed_defs     = array(
    'cache'                => array( 'Master page cache (0 off, 1 on, 2 network)', 'int' ),
    'cache-ttl_pub'        => array( 'Public page TTL in seconds', 'int' ),
    'cache-mobile'         => array( 'Separate mobile cache', 'bool' ),
    'cache-browser'        => array( 'Browser cache headers', 'bool' ),
    'cache-exc'            => array( 'URLs excluded from cache', 'array' ),
    'cache-exc_cookies'    => array( 'Cookies that bypass cache', 'array' ),
    'cache-exc_useragents' => array( 'User agents that bypass cache', 'array' ),
    'cache-exc_qs'         => array( 'Query-string parameters ignored for cache', 'array' ),
    'purge-post_all'       => array( 'Purge everything on post update', 'bool' ),
    'optm-css_min'         => array( 'Minify CSS', 'bool' ),
    'optm-css_comb'        => array( 'Combine CSS', 'bool' ),
    'optm-ucss'            => array( 'Unique CSS (QUIC.cloud)', 'bool' ),
    'optm-js_min'          => array( 'Minify JavaScript', 'bool' ),
    'optm-js_comb'         => array( 'Combine JavaScript', 'bool' ),
    'optm-js_defer'        => array( 'JS defer mode (0 off, 1 deferred, 2 delayed)', 'int' ),
    'optm-html_min'        => array( 'Minify HTML', 'bool' ),
    'optm-css_font_display'=> array( 'Add font-display support', 'bool' ),
    'media-lazy'           => array( 'Native lazy loading', 'bool' ),
    'img_optm-webp'        => array( 'Image format (0 off, 1 WebP, 2 AVIF)', 'int' ),
    'object'               => array( 'Object cache', 'bool' ),
    'crawler'              => array( 'Built-in crawler', 'bool' ),
    'debug'                => array( 'Debug logging (0 off, 1 on, 2 admin IP)', 'int' ),
);
foreach ( $litespeed_defs as $id => $def ) {
    $litespeed_settings[ $id ] = array(
        'label'        => $def[0],
        'type'         => $def[1],
        'merge'        => 'replace',
        'get_callback' => function () use ( $id ) {
            return PressAgent_Supported_Plugin_Actions::litespeed_get_setting( $id );
        },
        'set_callback' => function ( $value ) use ( $id ) {
            return PressAgent_Supported_Plugin_Actions::litespeed_save_setting( $id, $value );
        },
    );
}

/* -------------------------------------------------------------------------
 * Wordfence: scalar keys in the wfConfig table.
 * ---------------------------------------------------------------------- */
$wordfence_settings = array();
$wordfence_defs     = array(
    'firewallEnabled'                   => array( 'Firewall enabled', 'bool' ),
    'loginSecurityEnabled'              => array( 'Login security enabled', 'bool' ),
    'alertOn_block'                     => array( 'Alert on block', 'bool' ),
    'alertOn_loginLockout'              => array( 'Alert on login lockout', 'bool' ),
    'alertOn_scanIssues'                => array( 'Alert on scan issues', 'bool' ),
    'alertOn_update'                    => array( 'Alert on update', 'bool' ),
    'autoUpdate'                        => array( 'Wordfence auto-update', 'bool' ),
    'liveTrafficEnabled'                => array( 'Live traffic enabled', 'bool' ),
    'lowResourceScansEnabled'           => array( 'Low-resource scans', 'bool' ),
    'scan_force_ipv4_start'             => array( 'Force IPv4 for scans', 'bool' ),
    'loginSec_blockAdminReg'            => array( 'Block admin registration', 'bool' ),
    'loginSec_disableAuthorScan'        => array( 'Disable author scanning', 'bool' ),
    'loginSec_disableApplicationPasswords' => array( 'Disable application passwords', 'bool' ),
    'loginSec_disableOEmbedAuthor'      => array( 'Disable oEmbed author', 'bool' ),
    'loginSec_lockInvalidUsers'         => array( 'Lock out invalid usernames', 'bool' ),
    'loginSec_strongPasswds_enabled'    => array( 'Enforce strong passwords', 'bool' ),
    'loginSec_breachPasswds_enabled'    => array( 'Check breached passwords', 'bool' ),
    'loginSec_maskLoginErrors'          => array( 'Mask login errors', 'bool' ),
    'scan_include_extra'                => array( 'Extra scan paths', 'string' ),
    'alertEmails'                       => array( 'Alert email addresses (comma separated)', 'string' ),
    'whitelisted'                       => array( 'Whitelisted IPs (comma separated)', 'string' ),
    'howGetIPs'                         => array( 'How Wordfence gets visitor IPs', 'string' ),
    'loginSec_maxFailures'              => array( 'Max login failures', 'int' ),
    'loginSec_maxForgotPasswd'          => array( 'Max forgot-password attempts', 'int' ),
    'loginSec_countFailMins'            => array( 'Failure counting window (minutes)', 'int' ),
    'loginSec_lockoutMins'              => array( 'Lockout duration (minutes)', 'int' ),
    'maxMem'                            => array( 'Max scan memory (MB)', 'int' ),
    'scan_maxIssues'                    => array( 'Max scan issues', 'int' ),
    'alert_maxHourly'                   => array( 'Max alerts per hour', 'int' ),
    'blockedTime'                       => array( 'Block duration (seconds)', 'int' ),
    'liveTraf_maxRows'                  => array( 'Live traffic max rows', 'int' ),
    'liveTraf_maxAge'                   => array( 'Live traffic max age (days)', 'int' ),
    'scanType'                          => array( 'Scan type', 'string' ),
    'schedMode'                         => array( 'Scan scheduling mode', 'string' ),
    'schedStartHour'                    => array( 'Scheduled scan start hour (-1 auto)', 'int' ),
    'manualScanType'                    => array( 'Manual scan schedule', 'string' ),
    'scan_maxDuration'                  => array( 'Max scan duration', 'string' ),
    'scan_exclude'                      => array( 'Scan exclude paths', 'string' ),
    'alertOn_severityLevel'             => array( 'Alert severity threshold', 'int' ),
    'email_summary_interval'            => array( 'Email summary interval', 'string' ),
    'liveTraf_ignoreUsers'              => array( 'Live traffic ignored users', 'string' ),
    'liveTraf_ignoreIPs'                => array( 'Live traffic ignored IPs', 'string' ),
    'liveTraf_ignoreUA'                 => array( 'Live traffic ignored user agents', 'string' ),
    'loginSec_strongPasswds'            => array( 'Strong password policy (pubs|admins|all)', 'string' ),
    'loginSec_breachPasswds'            => array( 'Breached password policy (pubs|admins|all)', 'string' ),
    'loginSec_userBlacklist'            => array( 'Blocked usernames/emails', 'string' ),
    'autoBlockScanners'                 => array( 'Auto-block scanners', 'bool' ),
    'allowHTTPSCaching'                 => array( 'Allow HTTPS caching', 'bool' ),
    'loginSec_enableSeparateTwoFactor'  => array( 'Separate two-factor settings', 'bool' ),
);
foreach ( $wordfence_defs as $key => $def ) {
    $wordfence_settings[ $key ] = array(
        'label'        => $def[0],
        'type'         => $def[1],
        'get_callback' => function () use ( $key, $def ) {
            if ( ! class_exists( 'wfConfig' ) ) {
                return null;
            }
            if ( 'bool' === $def[1] ) {
                return (bool) wfConfig::getBool( $key );
            }
            if ( 'int' === $def[1] ) {
                return (int) wfConfig::get( $key );
            }
            return (string) wfConfig::get( $key );
        },
        'set_callback' => function ( $value ) use ( $key, $def ) {
            if ( ! class_exists( 'wfConfig' ) ) {
                return false;
            }
            if ( 'bool' === $def[1] ) {
                wfConfig::set( $key, rest_sanitize_boolean( $value ) ? 1 : 0 );
            } elseif ( 'int' === $def[1] ) {
                wfConfig::set( $key, (int) $value );
            } else {
                wfConfig::set( $key, sanitize_text_field( (string) $value ) );
            }
            return true;
        },
    );
}

/* -------------------------------------------------------------------------
 * AIOS: one config array; values are typically '1' / ''.
 * ---------------------------------------------------------------------- */
$aios_fields = array(
    'aiowps_enable_login_lockdown'          => array( 'Login lockout enabled', 'string', '1 = on, 0/empty = off' ),
    'aiowps_max_login_attempts'             => array( 'Max login attempts', 'int' ),
    'aiowps_retry_time_period'              => array( 'Retry window (minutes)', 'int' ),
    'aiowps_lockout_time_length'            => array( 'Lockout length (minutes)', 'int' ),
    'aiowps_enable_whitelisting'            => array( 'IP whitelisting enabled', 'string', '1 = on, 0/empty = off' ),
    'aiowps_allowed_ip_addresses'           => array( 'Allowed IP addresses', 'textarea' ),
    'aiowps_enable_blacklisting'            => array( 'IP blacklisting enabled', 'string', '1 = on, 0/empty = off' ),
    'aiowps_banned_ip_addresses'            => array( 'Banned IP addresses', 'textarea' ),
    'aiowps_enable_basic_firewall'          => array( 'Basic firewall rules', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_pingback_firewall'       => array( 'Pingback firewall', 'string', '1 = on, 0/empty = off' ),
    'aiowps_disable_xmlrpc_pingback_methods' => array( 'Disable XML-RPC pingback', 'string', '1 = on, 0/empty = off' ),
    'aiowps_block_debug_log_file_access'    => array( 'Block debug.log access', 'string', '1 = on, 0/empty = off' ),
    'aiowps_disable_index_views'            => array( 'Disable directory listing', 'string', '1 = on, 0/empty = off' ),
    'aiowps_disable_trace_and_track'        => array( 'Disable TRACE/TRACK', 'string', '1 = on, 0/empty = off' ),
    'aiowps_forbid_proxy_comments'          => array( 'Forbid proxy comments', 'string', '1 = on, 0/empty = off' ),
    'aiowps_deny_bad_query_strings'         => array( 'Deny bad query strings', 'string', '1 = on, 0/empty = off' ),
    'aiowps_advanced_char_string_filter'    => array( 'Advanced character filter', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_5g_firewall'             => array( '5G firewall', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_6g_firewall'             => array( '6G firewall master switch', 'string', '1 = on, 0/empty = off; rules live in aios_firewall_config' ),
    'aiowps_enable_404_logging'             => array( '404 event logging', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_404_IP_lockout'          => array( '404 IP lockout', 'string', '1 = on, 0/empty = off' ),
    'aiowps_404_lockout_time_length'        => array( '404 lockout minutes', 'int' ),
    'aiowps_404_lock_redirect_url'          => array( '404 lockout redirect URL', 'url' ),
    'aiowps_enable_rename_login_page'       => array( 'Rename login page', 'string', '1 = on, 0/empty = off' ),
    'aiowps_login_page_slug'                => array( 'Custom login slug', 'string' ),
    'aiowps_enable_brute_force_attack_prevention' => array( 'Cookie-based brute-force prevention', 'string', '1 = on, 0/empty = off' ),
    'aiowps_brute_force_secret_word'        => array( 'Brute-force secret word', 'string' ),
    'aiowps_enable_login_captcha'           => array( 'Login CAPTCHA', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_registration_page_captcha' => array( 'Registration CAPTCHA', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_comment_captcha'         => array( 'Comment CAPTCHA', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_autoblock_spam_ip'       => array( 'Auto-block spam IPs', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_forced_logout'           => array( 'Forced logout enabled', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_invalid_username_lockdown' => array( 'Lock out invalid usernames', 'string', '1 = on, 0/empty = off' ),
    'aiowps_instantly_lockout_specific_usernames' => array( 'Usernames to lock out instantly', 'array' ),
    'aiowps_enable_email_notify'            => array( 'Email notifications', 'string', '1 = on, 0/empty = off' ),
    'aiowps_email_address'                  => array( 'Notification email addresses', 'array' ),
    'aiowps_enable_debug'                   => array( 'AIOS debug logging', 'string', '1 = on, 0/empty = off' ),
    'aiowps_disable_file_editing'           => array( 'Disable built-in file editing', 'string', '1 = on, 0/empty = off' ),
    'aiowps_prevent_default_wp_file_access' => array( 'Block default WP file access', 'string', '1 = on, 0/empty = off' ),
    'aiowps_auto_delete_default_wp_files'   => array( 'Delete default WP files', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_random_prefix'           => array( 'Random database prefix', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_salt_postfix'            => array( 'Salt postfix', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_manual_registration_approval' => array( 'Manual registration approval', 'string', '1 = on, 0/empty = off' ),
    'aiowps_enable_login_honeypot'          => array( 'Login honeypot', 'string', '1 = on, 0/empty = off' ),
    'aiowps_disable_application_password'   => array( 'Disable application passwords', 'string', '1 = on, 0/empty = off' ),
);
$aios_field_specs = array();
foreach ( $aios_fields as $key => $def ) {
    $aios_field_specs[ $key ] = array(
        'type'  => $def[1],
        'label' => $def[0],
    );
    if ( ! empty( $def[2] ) ) {
        $aios_field_specs[ $key ]['notes'] = $def[2];
    }
}

/* -------------------------------------------------------------------------
 * Really Simple Security
 * ---------------------------------------------------------------------- */
$rsssl_fields = array(
    'redirect'                                => array( 'HTTPS redirect method (none|wp_redirect|htaccess)', 'string' ),
    'mixed_content_fixer'                     => array( 'Mixed content fixer', 'bool' ),
    'login_protection_enabled'                => array( 'Two-factor login protection', 'bool' ),
    'two_fa_forced_roles'                     => array( 'Roles forced to use 2FA', 'array' ),
    'enable_limited_login_attempts'           => array( 'Limit login attempts', 'bool' ),
    'limit_login_attempts_amount'             => array( 'Allowed failed attempts (3|5|10|15)', 'string' ),
    'limit_login_attempts_locked_out_duration'=> array( 'Lockout minutes', 'string' ),
    'enable_firewall'                         => array( 'Firewall rules', 'bool' ),
    '404_blocking_threshold'                  => array( '404 lockout threshold (disabled|lax|normal|strict)', 'string' ),
    'enable_vulnerability_scanner'            => array( 'Vulnerability scanner', 'bool' ),
    'disable_xmlrpc'                          => array( 'Disable XML-RPC', 'bool' ),
    'disable_file_editing'                    => array( 'Disable file editing', 'bool' ),
    'hsts'                                    => array( 'HSTS header', 'bool' ),
    'notifications_email_address'             => array( 'Notification email', 'email' ),
    'delete_data_on_uninstall'                => array( 'Delete data on uninstall', 'bool' ),
);
$rsssl_field_specs = array();
foreach ( $rsssl_fields as $key => $def ) {
    $rsssl_field_specs[ $key ] = array( 'type' => $def[1], 'label' => $def[0] );
}

return array(

    'elementor' => array(
        'name'             => 'Elementor Website Builder',
        'file'             => 'elementor/elementor.php',
        'version_constant' => 'ELEMENTOR_VERSION',
        'detect'           => array( 'defined:ELEMENTOR_VERSION', 'class:\\Elementor\\Plugin' ),
        'notes'            => array( 'Use pressagent_get_elementor_layout / add_elementor_section / update_widget for page layouts. These settings are global only.' ),
        'settings'         => array(
            'elementor_cpt_support' => array(
                'label' => 'Post types editable with Elementor',
                'option'=> 'elementor_cpt_support',
                'type'  => 'array',
                'merge' => 'replace',
                'strict_fields' => false,
                'fields'=> array(),
            ),
            'elementor_css_print_method' => array(
                'label' => 'CSS print method (external|internal)',
                'option'=> 'elementor_css_print_method',
                'type'  => 'string',
            ),
            'elementor_disable_color_schemes' => array(
                'label' => 'Disable default color schemes (yes|empty)',
                'option'=> 'elementor_disable_color_schemes',
                'type'  => 'string',
            ),
            'elementor_disable_typography_schemes' => array(
                'label' => 'Disable default typography schemes (yes|empty)',
                'option'=> 'elementor_disable_typography_schemes',
                'type'  => 'string',
            ),
            'elementor_google_font' => array(
                'label' => 'Load Google Fonts (1|0)',
                'option'=> 'elementor_google_font',
                'type'  => 'string',
            ),
            'elementor_font_display' => array(
                'label' => 'font-display value',
                'option'=> 'elementor_font_display',
                'type'  => 'string',
            ),
            'elementor_local_google_fonts' => array(
                'label' => 'Serve Google Fonts locally (1|0)',
                'option'=> 'elementor_local_google_fonts',
                'type'  => 'string',
            ),
            'elementor_unfiltered_files_upload' => array(
                'label' => 'Unfiltered uploads (1|empty; security risk)',
                'option'=> 'elementor_unfiltered_files_upload',
                'type'  => 'string',
            ),
            'elementor_element_cache_ttl' => array(
                'label' => 'Element cache TTL (hours|disable)',
                'option'=> 'elementor_element_cache_ttl',
                'type'  => 'string',
            ),
            'elementor_lazy_load_background_images' => array(
                'label' => 'Lazy-load background images (1|0)',
                'option'=> 'elementor_lazy_load_background_images',
                'type'  => 'string',
            ),
            'elementor_experiment-container' => array(
                'label' => 'Container experiment (active|inactive|default)',
                'option'=> 'elementor_experiment-container',
                'type'  => 'string',
            ),
            'elementor_experiment-e_optimized_markup' => array(
                'label' => 'Optimized markup experiment (active|inactive|default)',
                'option'=> 'elementor_experiment-e_optimized_markup',
                'type'  => 'string',
            ),
            'elementor_experiment-e_optimized_css_files' => array(
                'label' => 'Optimized CSS files experiment (active|inactive|default)',
                'option'=> 'elementor_experiment-e_optimized_css_files',
                'type'  => 'string',
            ),
        ),
        'actions' => array(
            'clear_cache' => array(
                'label'   => 'Clear all Elementor CSS/element caches',
                'safety'  => 'write',
                'params'  => array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'elementor_clear_cache' ),
            ),
            'clear_document_cache' => array(
                'label'   => 'Clear one document cache',
                'safety'  => 'write',
                'params'  => array( 'post_id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'elementor_clear_document_cache' ),
            ),
        ),
    ),

    'litespeed-cache' => array(
        'name'             => 'LiteSpeed Cache',
        'file'             => 'litespeed-cache/litespeed-cache.php',
        'version_constant' => 'LSCWP_V',
        'detect'           => array( 'defined:LSCWP_V' ),
        'settings'         => $litespeed_settings,
        'actions'          => array(
            'purge_all' => array(
                'label'   => 'Purge all LiteSpeed caches',
                'safety'  => 'write',
                'params'  => array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'litespeed_purge_all' ),
            ),
            'purge_url' => array(
                'label'   => 'Purge one URL',
                'safety'  => 'write',
                'params'  => array( 'url' => array( 'type' => 'url', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'litespeed_purge_url' ),
            ),
            'purge_post' => array(
                'label'   => 'Purge one post',
                'safety'  => 'write',
                'params'  => array( 'post_id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'litespeed_purge_post' ),
            ),
            'purge_posttype' => array(
                'label'   => 'Purge one post type',
                'safety'  => 'write',
                'params'  => array( 'post_type' => array( 'type' => 'string', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'litespeed_purge_posttype' ),
            ),
        ),
    ),

    'woocommerce' => array(
        'name'             => 'WooCommerce',
        'file'             => 'woocommerce/woocommerce.php',
        'version_constant' => 'WC_VERSION',
        'detect'           => array( 'defined:WC_VERSION', 'class:WooCommerce' ),
        'notes'            => array( 'Products and orders are managed with pressagent_commerce. Variable-product pricing belongs to variations.' ),
        'settings'         => array(
            'woocommerce_currency' => array( 'label' => 'Store currency code', 'option' => 'woocommerce_currency', 'type' => 'string' ),
            'woocommerce_currency_pos' => array( 'label' => 'Currency position (left|right|left_space|right_space)', 'option' => 'woocommerce_currency_pos', 'type' => 'string' ),
            'woocommerce_price_num_decimals' => array( 'label' => 'Price decimals', 'option' => 'woocommerce_price_num_decimals', 'type' => 'int' ),
            'woocommerce_default_country' => array( 'label' => 'Base country/state (US:CA)', 'option' => 'woocommerce_default_country', 'type' => 'string' ),
            'woocommerce_store_address' => array( 'label' => 'Store address', 'option' => 'woocommerce_store_address', 'type' => 'string' ),
            'woocommerce_store_city' => array( 'label' => 'Store city', 'option' => 'woocommerce_store_city', 'type' => 'string' ),
            'woocommerce_store_postcode' => array( 'label' => 'Store postcode', 'option' => 'woocommerce_store_postcode', 'type' => 'string' ),
            'woocommerce_calc_taxes' => array( 'label' => 'Calculate taxes (yes|no)', 'option' => 'woocommerce_calc_taxes', 'type' => 'string' ),
            'woocommerce_prices_include_tax' => array( 'label' => 'Prices include tax (yes|no)', 'option' => 'woocommerce_prices_include_tax', 'type' => 'string' ),
            'woocommerce_enable_coupons' => array( 'label' => 'Coupons enabled (yes|no)', 'option' => 'woocommerce_enable_coupons', 'type' => 'string' ),
            'woocommerce_manage_stock' => array( 'label' => 'Global stock management (yes|no)', 'option' => 'woocommerce_manage_stock', 'type' => 'string' ),
            'woocommerce_weight_unit' => array( 'label' => 'Weight unit (kg|g|lbs|oz)', 'option' => 'woocommerce_weight_unit', 'type' => 'string' ),
            'woocommerce_dimension_unit' => array( 'label' => 'Dimension unit (m|cm|mm|in|yd)', 'option' => 'woocommerce_dimension_unit', 'type' => 'string' ),
            'woocommerce_enable_guest_checkout' => array( 'label' => 'Guest checkout (yes|no)', 'option' => 'woocommerce_enable_guest_checkout', 'type' => 'string' ),
            'woocommerce_cart_page_id' => array(
                'label' => 'Cart page ID',
                'option'=> 'woocommerce_cart_page_id',
                'type'  => 'int',
                'set_callback' => function ( $value ) {
                    $id = absint( $value );
                    if ( $id && ! get_post( $id ) ) {
                        return false;
                    }
                    update_option( 'woocommerce_cart_page_id', $id );
                    return true;
                },
            ),
            'woocommerce_checkout_page_id' => array(
                'label' => 'Checkout page ID',
                'option'=> 'woocommerce_checkout_page_id',
                'type'  => 'int',
                'set_callback' => function ( $value ) {
                    $id = absint( $value );
                    if ( $id && ! get_post( $id ) ) {
                        return false;
                    }
                    update_option( 'woocommerce_checkout_page_id', $id );
                    return true;
                },
            ),
            'woocommerce_myaccount_page_id' => array(
                'label' => 'My Account page ID',
                'option'=> 'woocommerce_myaccount_page_id',
                'type'  => 'int',
                'set_callback' => function ( $value ) {
                    $id = absint( $value );
                    if ( $id && ! get_post( $id ) ) {
                        return false;
                    }
                    update_option( 'woocommerce_myaccount_page_id', $id );
                    return true;
                },
            ),
            'woocommerce_email_from_name' => array( 'label' => 'Transactional email from name', 'option' => 'woocommerce_email_from_name', 'type' => 'string' ),
            'woocommerce_email_from_address' => array( 'label' => 'Transactional email from address', 'option' => 'woocommerce_email_from_address', 'type' => 'email' ),
            'woocommerce_allow_tracking' => array( 'label' => 'Usage tracking (yes|no)', 'option' => 'woocommerce_allow_tracking', 'type' => 'string' ),
        ),
    ),

    'contact-form-7' => array(
        'name'             => 'Contact Form 7',
        'file'             => 'contact-form-7/wp-contact-form-7.php',
        'version_constant' => 'WPCF7_VERSION',
        'detect'           => array( 'defined:WPCF7_VERSION', 'class:WPCF7_ContactForm' ),
        'notes'            => array( 'Forms are managed with pressagent_forms (list/get/create_cf7/update_cf7/delete_cf7). CF7 does not store entries without Flamingo.' ),
        'settings'         => array(),
        'actions'          => array(),
    ),

    'wpforms-lite' => array(
        'name'             => 'WPForms Lite',
        'file'             => 'wpforms-lite/wpforms.php',
        'version_constant' => 'WPFORMS_VERSION',
        'detect'           => array( 'defined:WPFORMS_VERSION', 'function:wpforms' ),
        'notes'            => array( 'Forms are read with pressagent_forms. Lite stores counters only, not entries; multiple notifications are Pro.' ),
        'settings'         => array(
            'wpforms_settings' => array(
                'label' => 'WPForms global settings',
                'type'  => 'array',
                'strict_fields' => true,
                'fields' => array(
                    'disable-css' => array( 'type' => 'int', 'label' => 'CSS mode (1 base+theme, 2 base, 3 none)' ),
                    'global-assets' => array( 'type' => 'bool', 'label' => 'Load assets site-wide' ),
                    'gdpr' => array( 'type' => 'bool', 'label' => 'GDPR enhancements' ),
                    'delete-spam-entries' => array( 'type' => 'int', 'label' => 'Spam retention days' ),
                    'hide-announcements' => array( 'type' => 'bool', 'label' => 'Hide announcements' ),
                    'hide-admin-bar' => array( 'type' => 'bool', 'label' => 'Hide admin bar' ),
                    'uninstall-data' => array( 'type' => 'bool', 'label' => 'Delete data on uninstall' ),
                    'validation-required' => array( 'type' => 'string', 'label' => 'Required-field message' ),
                    'validation-email' => array( 'type' => 'string', 'label' => 'Invalid-email message' ),
                    'currency' => array( 'type' => 'string', 'label' => 'Currency code' ),
                ),
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'wpforms_get_settings' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'wpforms_save_settings' ),
            ),
        ),
        'actions' => array(),
    ),

    'redirection' => array(
        'name'             => 'Redirection',
        'file'             => 'redirection/redirection.php',
        'version_constant' => 'REDIRECTION_VERSION',
        'detect'           => array( 'defined:REDIRECTION_VERSION', 'class:Red_Item' ),
        'notes'            => array( 'There is no Pro version. Redirect mutations run through Red_Item and flush the module cache.' ),
        'settings'         => array(
            'redirection_options' => array(
                'label' => 'Redirection settings',
                'type'  => 'array',
                'strict_fields' => true,
                'fields' => array(
                    'expire_redirect' => array( 'type' => 'int', 'label' => 'Redirect log retention days (-1 never)' ),
                    'expire_404' => array( 'type' => 'int', 'label' => '404 log retention days (-1 never)' ),
                    'redirect_cache' => array( 'type' => 'int', 'label' => 'Redirect cache duration flag' ),
                    'monitor_post' => array( 'type' => 'int', 'label' => 'Post-monitor group ID (0 off)' ),
                    'monitor_types' => array( 'type' => 'array', 'label' => 'Monitored post types' ),
                    'associated_redirect' => array( 'type' => 'string', 'label' => 'Fallback target' ),
                    'https' => array( 'type' => 'bool', 'label' => 'Force HTTPS' ),
                    'aliases' => array( 'type' => 'array', 'label' => 'Additional hostnames' ),
                    'preferred_domain' => array( 'type' => 'string', 'label' => 'Preferred domain (|www|nowww)' ),
                    'track_hits' => array( 'type' => 'bool', 'label' => 'Track hits' ),
                ),
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_get_options' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_save_options' ),
            ),
        ),
        'actions' => array(
            'list_redirects' => array(
                'label' => 'List redirects',
                'safety'=> 'read',
                'params'=> array( 'limit' => array( 'type' => 'int', 'required' => false ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_list' ),
            ),
            'create_redirect' => array(
                'label' => 'Create a redirect',
                'safety'=> 'write',
                'params'=> array(
                    'url' => array( 'type' => 'string', 'required' => true ),
                    'target' => array( 'type' => 'url', 'required' => true ),
                    'action_type' => array( 'type' => 'string', 'required' => false ),
                    'action_code' => array( 'type' => 'int', 'required' => false ),
                    'group_id' => array( 'type' => 'int', 'required' => false ),
                    'title' => array( 'type' => 'string', 'required' => false ),
                    'match_type' => array( 'type' => 'string', 'required' => false ),
                    'regex' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_create' ),
            ),
            'update_redirect' => array(
                'label' => 'Update a redirect',
                'safety'=> 'write',
                'params'=> array(
                    'id' => array( 'type' => 'int', 'required' => true ),
                    'url' => array( 'type' => 'string', 'required' => false ),
                    'target' => array( 'type' => 'url', 'required' => false ),
                    'action_type' => array( 'type' => 'string', 'required' => false ),
                    'action_code' => array( 'type' => 'int', 'required' => false ),
                    'group_id' => array( 'type' => 'int', 'required' => false ),
                    'title' => array( 'type' => 'string', 'required' => false ),
                    'match_type' => array( 'type' => 'string', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_update' ),
            ),
            'delete_redirect' => array(
                'label' => 'Delete a redirect',
                'safety'=> 'destructive',
                'params'=> array( 'id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_delete' ),
            ),
            'set_redirect_enabled' => array(
                'label' => 'Enable or disable a redirect',
                'safety'=> 'write',
                'params'=> array(
                    'id' => array( 'type' => 'int', 'required' => true ),
                    'enabled' => array( 'type' => 'bool', 'required' => true ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_set_enabled' ),
            ),
            'lookup_404s' => array(
                'label' => 'Look up 404 log rows',
                'safety'=> 'read',
                'params'=> array(
                    'url' => array( 'type' => 'string', 'required' => false ),
                    'per_page' => array( 'type' => 'int', 'required' => false ),
                    'page' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_lookup_404s' ),
            ),
            'list_groups' => array(
                'label' => 'List redirect groups',
                'safety'=> 'read',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_list_groups' ),
            ),
            'create_group' => array(
                'label' => 'Create a redirect group',
                'safety'=> 'write',
                'params'=> array(
                    'name'      => array( 'type' => 'string', 'required' => true ),
                    'module_id' => array( 'type' => 'int', 'required' => false ),
                    'enabled'   => array( 'type' => 'bool', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_create_group' ),
            ),
            'update_group' => array(
                'label' => 'Rename/move a redirect group',
                'safety'=> 'write',
                'params'=> array(
                    'id'        => array( 'type' => 'int', 'required' => true ),
                    'name'      => array( 'type' => 'string', 'required' => false ),
                    'module_id' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_update_group' ),
            ),
            'delete_group' => array(
                'label' => 'Delete a group and all its redirects',
                'safety'=> 'destructive',
                'params'=> array( 'id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_delete_group' ),
            ),
            'lookup_logs' => array(
                'label' => 'Look up redirect hit logs',
                'safety'=> 'read',
                'params'=> array(
                    'url'      => array( 'type' => 'string', 'required' => false ),
                    'per_page' => array( 'type' => 'int', 'required' => false ),
                    'page'     => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_lookup_logs' ),
            ),
            'flush_cache' => array(
                'label' => 'Flush redirect module cache',
                'safety'=> 'write',
                'params'=> array( 'group_id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'redirection_flush_cache' ),
            ),
        ),
    ),

    'wp-mail-smtp' => array(
        'name'             => 'WP Mail SMTP',
        'file'             => 'wp-mail-smtp/wp_mail_smtp.php',
        'version_constant' => 'WPMS_PLUGIN_VER',
        'detect'           => array( 'defined:WPMS_PLUGIN_VER', 'function:wp_mail_smtp' ),
        'notes'            => array( 'The SMTP password is write-only: reads return pass_set instead of the secret. WPMS_* constants override stored values. Email log/stats are Pro.' ),
        'settings'         => array(
            'wp_mail_smtp' => array(
                'label' => 'Mailer, SMTP and From settings',
                'type'  => 'array',
                'strict_fields' => true,
                'fields' => array(
                    'mail' => array( 'type' => 'array', 'label' => 'From email/name, mailer, return path' ),
                    'smtp' => array( 'type' => 'array', 'label' => 'SMTP host/port/encryption/auth/user/pass' ),
                    'general' => array( 'type' => 'array', 'label' => 'General flags (do_not_send)' ),
                ),
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'wpmailsmtp_get_options' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'wpmailsmtp_save_options' ),
            ),
        ),
        'actions' => array(
            'send_test_email' => array(
                'label' => 'Send a test email through the configured connection',
                'safety'=> 'write',
                'params'=> array( 'recipient' => array( 'type' => 'email', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'wpmailsmtp_send_test' ),
            ),
            'list_debug_events' => array(
                'label' => 'List WP Mail SMTP debug events',
                'safety'=> 'read',
                'params'=> array(
                    'per_page' => array( 'type' => 'int', 'required' => false ),
                    'offset' => array( 'type' => 'int', 'required' => false ),
                    'type' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'wpmailsmtp_debug_events' ),
            ),
        ),
    ),

    'really-simple-ssl' => array(
        'name'             => 'Really Simple Security',
        'file'             => 'really-simple-ssl/rlrsssl-really-simple-ssl.php',
        'version_constant' => 'rsssl_version',
        'detect'           => array( 'defined:rsssl_version', 'class:REALLY_SIMPLE_SSL' ),
        'notes'            => array( 'SSL activation itself is intentionally not exposed; enable it in the plugin. Pro passkey/TOTP fields are not exposed.' ),
        'settings'         => array(
            'rsssl_options' => array(
                'label' => 'Really Simple Security settings',
                'type'  => 'array',
                'strict_fields' => true,
                'fields' => $rsssl_field_specs,
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'rsssl_get_options' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'rsssl_save_options' ),
            ),
        ),
        'actions' => array(
            'rebuild_rules' => array(
                'label' => 'Rebuild firewall/htaccess rules',
                'safety'=> 'write',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rsssl_rebuild_rules' ),
            ),
            'send_test_mail' => array(
                'label' => 'Send the security notification test email',
                'safety'=> 'write',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rsssl_send_test_mail' ),
            ),
        ),
    ),

    'all-in-one-wp-security-and-firewall' => array(
        'name'             => 'All-In-One Security (AIOS)',
        'file'             => 'all-in-one-wp-security-and-firewall/wp-security.php',
        'version_constant' => 'AIO_WP_SECURITY_VERSION',
        'detect'           => array( 'defined:AIO_WP_SECURITY_VERSION' ),
        'notes'            => array( 'AIOS stores all options in one array. The 6G firewall rules live in a separate firewall config and are intentionally not exposed here.' ),
        'settings'         => array(
            'aio_wp_security_configs' => array(
                'label' => 'AIOS configuration',
                'type'  => 'array',
                'strict_fields' => true,
                'fields' => $aios_field_specs,
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_get_config' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_save_config' ),
            ),
            'aios_firewall_config' => array(
                'label' => 'AIOS 6G firewall rules (separate firewall config)',
                'type'  => 'array',
                'merge' => 'deep',
                'strict_fields' => true,
                'fields' => array(
                    'aiowps_6g_block_request' => array( 'type' => 'bool', 'label' => 'Block malicious requests (6G)' ),
                    'aiowps_6g_block_query' => array( 'type' => 'bool', 'label' => 'Block malicious query strings' ),
                    'aiowps_6g_block_referrers' => array( 'type' => 'bool', 'label' => 'Block malicious referrers' ),
                    'aiowps_6g_block_agents' => array( 'type' => 'bool', 'label' => 'Block malicious user agents' ),
                    'aiowps_6g_block_request_methods' => array( 'type' => 'array', 'label' => 'Blocked HTTP methods' ),
                    'aiowps_enable_pingback_firewall' => array( 'type' => 'bool', 'label' => 'Pingback firewall' ),
                    'aiowps_forbid_proxy_comments' => array( 'type' => 'bool', 'label' => 'Forbid proxy comments' ),
                    'aiowps_deny_bad_query_strings' => array( 'type' => 'bool', 'label' => 'Deny bad query strings' ),
                    'aiowps_advanced_char_string_filter' => array( 'type' => 'bool', 'label' => 'Advanced character filter' ),
                    'aiowps_ban_post_blank_headers' => array( 'type' => 'bool', 'label' => 'Ban POST requests with blank headers' ),
                    'aiowps_block_fake_googlebots' => array( 'type' => 'bool', 'label' => 'Block fake Googlebots' ),
                    'aiowps_enable_blacklisting' => array( 'type' => 'bool', 'label' => 'Firewall IP blacklist enabled' ),
                    'aiowps_blacklist_ips' => array( 'type' => 'array', 'label' => 'Firewall blacklisted IPs' ),
                    'aiowps_blacklist_user_agents' => array( 'type' => 'array', 'label' => 'Firewall blacklisted user agents' ),
                    'aiowps_googlebot_ip_ranges' => array( 'type' => 'array', 'label' => 'Googlebot IP ranges' ),
                    'aiowps_bingbot_ip_ranges' => array( 'type' => 'array', 'label' => 'Bingbot IP ranges' ),
                ),
                'get_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_firewall_get' ),
                'set_callback' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_firewall_save' ),
            ),
        ),
        'actions' => array(
            'rebuild_htaccess' => array(
                'label' => 'Rewrite AIOS .htaccess rules',
                'safety'=> 'write',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_rebuild_rules' ),
            ),
            'list_audit_events' => array(
                'label' => 'List AIOS audit log events',
                'safety'=> 'read',
                'params'=> array(
                    'limit'  => array( 'type' => 'int', 'required' => false ),
                    'offset' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'aios_list_audit_events' ),
            ),
        ),
    ),

    'wordfence' => array(
        'name'             => 'Wordfence Security',
        'file'             => 'wordfence/wordfence.php',
        'version_constant' => 'WORDFENCE_VERSION',
        'detect'           => array( 'defined:WORDFENCE_VERSION', 'class:wordfence' ),
        'notes'            => array( 'Wordfence stores config in its own table; reads/writes go through wfConfig. Premium country blocking and real-time IP blocklist are not exposed.' ),
        'settings'         => $wordfence_settings,
        'actions'          => array(
            'scan_status' => array(
                'label' => 'Get scan running state and issue count',
                'safety'=> 'read',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'wordfence_scan_status' ),
            ),
            'start_scan' => array(
                'label' => 'Start a Wordfence scan',
                'safety'=> 'write',
                'params'=> array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'wordfence_start_scan' ),
            ),
            'get_issues' => array(
                'label' => 'List latest scan issues',
                'safety'=> 'read',
                'params'=> array( 'limit' => array( 'type' => 'int', 'required' => false ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'wordfence_issues' ),
            ),
        ),
    ),

    'wordpress-seo' => array(
        'name'             => 'Yoast SEO',
        'file'             => 'wordpress-seo/wp-seo.php',
        'version_constant' => 'WPSEO_VERSION',
        'detect'           => array( 'defined:WPSEO_VERSION', 'class:WPSEO_Options' ),
        'seo'              => array( 'provider' => 'yoast' ),
        'notes'            => array( 'Page/term SEO metadata is managed with pressagent_seo. Term SEO is stored in wpseo_taxonomy_meta, not term meta.' ),
        'settings'         => array(
            'wpseo_titles' => array(
                'label' => 'Titles, meta templates, schema and breadcrumbs',
                'option'=> 'wpseo_titles',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'separator' => array( 'type' => 'string', 'label' => 'Title separator token' ),
                    'title-home-wpseo' => array( 'type' => 'string', 'label' => 'Homepage title template' ),
                    'metadesc-home-wpseo' => array( 'type' => 'string', 'label' => 'Homepage meta description' ),
                    'title-post' => array( 'type' => 'string', 'label' => 'Post title template' ),
                    'metadesc-post' => array( 'type' => 'string', 'label' => 'Post meta description' ),
                    'noindex-post' => array( 'type' => 'bool', 'label' => 'Noindex posts' ),
                    'title-tax-category' => array( 'type' => 'string', 'label' => 'Category title template' ),
                    'metadesc-tax-category' => array( 'type' => 'string', 'label' => 'Category meta description' ),
                    'noindex-tax-category' => array( 'type' => 'bool', 'label' => 'Noindex categories' ),
                    'noindex-author-wpseo' => array( 'type' => 'bool', 'label' => 'Noindex author archives' ),
                    'noindex-archive-wpseo' => array( 'type' => 'bool', 'label' => 'Noindex date archives' ),
                    'breadcrumbs-enable' => array( 'type' => 'bool', 'label' => 'Enable breadcrumbs' ),
                    'website_name' => array( 'type' => 'string', 'label' => 'Website name for schema' ),
                    'company_or_person' => array( 'type' => 'string', 'label' => 'Company or person (company|person)' ),
                    'company_name' => array( 'type' => 'string', 'label' => 'Company name' ),
                    'company_logo' => array( 'type' => 'url', 'label' => 'Company logo URL' ),
                    'company_logo_id' => array( 'type' => 'int', 'label' => 'Company logo attachment ID' ),
                ),
            ),
            'wpseo' => array(
                'label' => 'Yoast feature switches',
                'option'=> 'wpseo',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'enable_xml_sitemap' => array( 'type' => 'bool', 'label' => 'XML sitemaps' ),
                    'enable_index_now' => array( 'type' => 'bool', 'label' => 'IndexNow' ),
                    'enable_text_link_counter' => array( 'type' => 'bool', 'label' => 'Text link counter' ),
                    'enable_cornerstone_content' => array( 'type' => 'bool', 'label' => 'Cornerstone content' ),
                    'remove_generator' => array( 'type' => 'bool', 'label' => 'Remove generator tag' ),
                    'remove_emoji_scripts' => array( 'type' => 'bool', 'label' => 'Remove emoji scripts' ),
                    'content_analysis_active' => array( 'type' => 'bool', 'label' => 'SEO analysis' ),
                    'keyword_analysis_active' => array( 'type' => 'bool', 'label' => 'Focus keyword analysis' ),
                    'enable_llms_txt' => array( 'type' => 'bool', 'label' => 'llms.txt generation' ),
                ),
            ),
            'wpseo_social' => array(
                'label' => 'Social profiles and Open Graph defaults',
                'option'=> 'wpseo_social',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'facebook_site' => array( 'type' => 'url', 'label' => 'Facebook page URL' ),
                    'twitter_site' => array( 'type' => 'string', 'label' => 'X/Twitter handle' ),
                    'instagram_url' => array( 'type' => 'url', 'label' => 'Instagram URL' ),
                    'linkedin_url' => array( 'type' => 'url', 'label' => 'LinkedIn URL' ),
                    'youtube_url' => array( 'type' => 'url', 'label' => 'YouTube URL' ),
                    'og_default_image' => array( 'type' => 'url', 'label' => 'Default OG image URL' ),
                    'og_default_image_id' => array( 'type' => 'string', 'label' => 'Default OG image attachment ID' ),
                    'twitter_card_type' => array( 'type' => 'string', 'label' => 'X card type' ),
                    'opengraph' => array( 'type' => 'bool', 'label' => 'Open Graph metadata' ),
                    'twitter' => array( 'type' => 'bool', 'label' => 'X/Twitter metadata' ),
                ),
            ),
            'wpseo_llmstxt' => array(
                'label' => 'llms.txt page selection',
                'option'=> 'wpseo_llmstxt',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'llms_txt_selection_mode' => array( 'type' => 'string', 'label' => 'Selection mode' ),
                    'about_us_page' => array( 'type' => 'int', 'label' => 'About page ID' ),
                    'contact_page' => array( 'type' => 'int', 'label' => 'Contact page ID' ),
                    'terms_page' => array( 'type' => 'int', 'label' => 'Terms page ID' ),
                    'privacy_policy_page' => array( 'type' => 'int', 'label' => 'Privacy page ID' ),
                    'shop_page' => array( 'type' => 'int', 'label' => 'Shop page ID' ),
                    'other_included_pages' => array( 'type' => 'array', 'label' => 'Other page IDs' ),
                ),
            ),
        ),
    ),

    'seo-by-rank-math' => array(
        'name'             => 'Rank Math SEO',
        'file'             => 'seo-by-rank-math/rank-math.php',
        'version_constant' => 'RANK_MATH_VERSION',
        'detect'           => array( 'defined:RANK_MATH_VERSION', 'class:RankMath' ),
        'seo'              => array( 'provider' => 'rankmath' ),
        'notes'            => array( 'Page/term SEO metadata is managed with pressagent_seo. Pro modules (Content AI, Analytics, instant indexing) are not exposed.' ),
        'settings'         => array(
            'rank-math-options-titles' => array(
                'label' => 'Rank Math titles and meta',
                'option'=> 'rank-math-options-titles',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'homepage_title' => array( 'type' => 'string', 'label' => 'Homepage title template' ),
                    'homepage_description' => array( 'type' => 'textarea', 'label' => 'Homepage meta description' ),
                    'title_separator' => array( 'type' => 'string', 'label' => 'Title separator' ),
                    'open_graph_image' => array( 'type' => 'url', 'label' => 'Default Open Graph image' ),
                    'twitter_card_type' => array( 'type' => 'string', 'label' => 'X card type' ),
                    'robots_global' => array( 'type' => 'array', 'label' => 'Global robots directives' ),
                    'pt_page_title' => array( 'type' => 'string', 'label' => 'Page title template' ),
                    'pt_page_description' => array( 'type' => 'textarea', 'label' => 'Page meta description' ),
                    'pt_post_title' => array( 'type' => 'string', 'label' => 'Post title template' ),
                    'pt_post_description' => array( 'type' => 'textarea', 'label' => 'Post meta description' ),
                ),
                'set_callback' => function ( $value ) {
                    return PressAgent_Supported_Plugin_Actions::rankmath_save_settings( 'titles', $value );
                },
            ),
            'rank-math-options-general' => array(
                'label' => 'Rank Math general settings',
                'option'=> 'rank-math-options-general',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'nofollow_external_links' => array( 'type' => 'bool', 'label' => 'Nofollow external links' ),
                    'nofollow_image_links' => array( 'type' => 'bool', 'label' => 'Nofollow image links' ),
                    'new_window_external_links' => array( 'type' => 'bool', 'label' => 'Open external links in new window' ),
                    'strip_category_base' => array( 'type' => 'bool', 'label' => 'Strip category base' ),
                    'frontend_seo_score' => array( 'type' => 'bool', 'label' => 'Frontend SEO score' ),
                    'google_verify' => array( 'type' => 'string', 'label' => 'Google verification code' ),
                    'bing_verify' => array( 'type' => 'string', 'label' => 'Bing verification code' ),
                    'baidu_verify' => array( 'type' => 'string', 'label' => 'Baidu verification code' ),
                    'yandex_verify' => array( 'type' => 'string', 'label' => 'Yandex verification code' ),
                    'pinterest_verify' => array( 'type' => 'string', 'label' => 'Pinterest verification code' ),
                    'rss_before_content' => array( 'type' => 'textarea', 'label' => 'RSS before content' ),
                    'rss_after_content' => array( 'type' => 'textarea', 'label' => 'RSS after content' ),
                    'headless_support' => array( 'type' => 'bool', 'label' => 'Headless support' ),
                    '404_monitor_mode' => array( 'type' => 'string', 'label' => '404 monitor mode (simple|advanced)' ),
                    '404_monitor_limit' => array( 'type' => 'int', 'label' => '404 log limit' ),
                    '404_monitor_exclude' => array( 'type' => 'array', 'label' => '404 exclusion rules' ),
                    '404_monitor_ignore_query_parameters' => array( 'type' => 'bool', 'label' => 'Ignore query parameters in 404 logs' ),
                    'redirections_debug' => array( 'type' => 'bool', 'label' => 'Redirection debug logging' ),
                    'redirections_fallback' => array( 'type' => 'string', 'label' => 'Redirection fallback (default|home|404|custom)' ),
                    'redirections_custom_url' => array( 'type' => 'url', 'label' => 'Custom fallback URL' ),
                    'redirections_header_code' => array( 'type' => 'int', 'label' => 'Default redirection code' ),
                    'redirections_post_redirect' => array( 'type' => 'bool', 'label' => 'Auto-redirect changed post slugs' ),
                ),
                'set_callback' => function ( $value ) {
                    return PressAgent_Supported_Plugin_Actions::rankmath_save_settings( 'general', $value );
                },
            ),
            'rank-math-options-sitemap' => array(
                'label' => 'Rank Math sitemap settings',
                'option'=> 'rank-math-options-sitemap',
                'type'  => 'array',
                'strict_fields' => false,
                'fields' => array(
                    'items_per_page' => array( 'type' => 'int', 'label' => 'Items per sitemap page' ),
                    'include_images' => array( 'type' => 'bool', 'label' => 'Include images' ),
                    'include_featured_image' => array( 'type' => 'bool', 'label' => 'Include featured image' ),
                    'exclude_posts' => array( 'type' => 'string', 'label' => 'Excluded post IDs' ),
                    'exclude_terms' => array( 'type' => 'string', 'label' => 'Excluded term IDs' ),
                    'html_sitemap' => array( 'type' => 'bool', 'label' => 'HTML sitemap' ),
                    'html_sitemap_display' => array( 'type' => 'string', 'label' => 'HTML sitemap display mode' ),
                    'html_sitemap_shortcode' => array( 'type' => 'string', 'label' => 'HTML sitemap shortcode' ),
                ),
                'set_callback' => function ( $value ) {
                    return PressAgent_Supported_Plugin_Actions::rankmath_save_settings( 'sitemap', $value );
                },
            ),
        ),
        'actions' => array(
            'list_modules' => array(
                'label'   => 'List Rank Math modules and active state',
                'safety'  => 'read',
                'params'  => array(),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_list_modules' ),
            ),
            'set_module' => array(
                'label'   => 'Enable or disable a Rank Math module',
                'safety'  => 'write',
                'params'  => array(
                    'module'  => array( 'type' => 'string', 'required' => true ),
                    'enabled' => array( 'type' => 'bool', 'required' => true ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_set_module' ),
            ),
            'list_redirections' => array(
                'label'   => 'List Rank Math redirections',
                'safety'  => 'read',
                'params'  => array(
                    'limit'  => array( 'type' => 'int', 'required' => false ),
                    'paged'  => array( 'type' => 'int', 'required' => false ),
                    'search' => array( 'type' => 'string', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_list_redirections' ),
            ),
            'create_redirection' => array(
                'label'   => 'Create a Rank Math redirection',
                'safety'  => 'write',
                'params'  => array(
                    'source'      => array( 'type' => 'string', 'required' => true ),
                    'target'      => array( 'type' => 'url', 'required' => true ),
                    'header_code' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_create_redirection' ),
            ),
            'update_redirection' => array(
                'label'   => 'Update a Rank Math redirection target/status',
                'safety'  => 'write',
                'params'  => array(
                    'id'          => array( 'type' => 'int', 'required' => true ),
                    'target'      => array( 'type' => 'url', 'required' => false ),
                    'header_code' => array( 'type' => 'int', 'required' => false ),
                    'status'      => array( 'type' => 'string', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_update_redirection' ),
            ),
            'delete_redirection' => array(
                'label'   => 'Delete a Rank Math redirection',
                'safety'  => 'destructive',
                'params'  => array( 'id' => array( 'type' => 'int', 'required' => true ) ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_delete_redirection' ),
            ),
            'list_404_errors' => array(
                'label'   => 'List Rank Math 404 log rows',
                'safety'  => 'read',
                'params'  => array(
                    'limit'  => array( 'type' => 'int', 'required' => false ),
                    'offset' => array( 'type' => 'int', 'required' => false ),
                ),
                'handler' => array( 'PressAgent_Supported_Plugin_Actions', 'rankmath_list_404_errors' ),
            ),
        ),
    ),
);
