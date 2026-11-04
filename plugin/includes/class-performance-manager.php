<?php
/**
 * Server-side performance audit and safe auto-fixes.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Performance_Manager {

    /**
     * Full environment/database/cache audit.
     *
     * @return array
     */
    public static function audit() {
        global $wpdb;

        $memory_limit = self::parse_bytes( ini_get( 'memory_limit' ) );
        $opcache      = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false;
        $object_cache = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();

        // WP 6.6+ uses on/off/auto-on/auto-off; older versions use yes/no.
        $autoload_sql = "autoload IN ('yes','on','auto-on')";
        $options_total = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_name) + LENGTH(option_value)) FROM {$wpdb->options} WHERE {$autoload_sql}" );
        $autoload_rows = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE {$autoload_sql} ORDER BY size DESC LIMIT 8",
            ARRAY_A
        );
        $autoload_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE {$autoload_sql}" );
        $expired_transients = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"
        );
        $revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
        $db_size   = $wpdb->get_var(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = DATABASE()"
        );

        $debug_log = WP_CONTENT_DIR . '/debug.log';
        $debug_size = file_exists( $debug_log ) ? (int) filesize( $debug_log ) : 0;

        $active_plugins = (array) get_option( 'active_plugins', array() );
        $theme          = wp_get_theme();
        $page_cache     = self::detect_page_cache();
        $litespeed      = self::litespeed_snapshot();
        $elementor      = self::elementor_snapshot();

        $issues = array();

        if ( empty( $page_cache['enabled'] ) ) {
            $issues[] = self::issue(
                'no_page_cache',
                'high',
                'Page cache is not active',
                'No active page cache was detected' . ( $page_cache['plugin'] ? ' (' . $page_cache['plugin'] . ')' : '' ) . '. Enable LiteSpeed Cache or another page cache to cut TTFB.',
                $litespeed ? 'enable_litespeed_cache' : null
            );
        }
        if ( ! $object_cache ) {
            $issues[] = self::issue(
                'no_object_cache',
                'medium',
                'Persistent object cache is missing',
                'Add Redis or Memcached with a persistent object-cache drop-in to reduce database queries.',
                null
            );
        }
        if ( empty( $opcache['opcache_enabled'] ) ) {
            $issues[] = self::issue(
                'opcache_disabled',
                'medium',
                'PHP OPcache is disabled',
                'Enable OPcache in php.ini (opcache.enable=1) for a large PHP execution win.',
                null
            );
        }
        if ( $options_total > 1048576 ) {
            $issues[] = self::issue(
                'autoload_large',
                'high',
                'Autoloaded options are heavy',
                sprintf( 'Autoloaded options: %s across %d rows. Review the top offenders and remove orphaned plugin options.', size_format( $options_total, 2 ), $autoload_count ),
                null
            );
        }
        if ( $revisions > 1000 ) {
            $issues[] = self::issue(
                'revisions_bloat',
                'medium',
                'Post revisions are excessive',
                sprintf( '%d revisions found. Delete old revisions or cap revisions in wp-config.php.', $revisions ),
                'delete_old_revisions'
            );
        }
        if ( $expired_transients > 100 ) {
            $issues[] = self::issue(
                'expired_transients',
                'low',
                'Expired transients are piling up',
                sprintf( '%d expired transients found. Clean them to reduce options-table overhead.', $expired_transients ),
                'delete_expired_transients'
            );
        }
        if ( $debug_size > 5 * 1024 * 1024 ) {
            $issues[] = self::issue(
                'debug_log_large',
                'low',
                'debug.log is large',
                sprintf( 'debug.log is %s. Rotate or truncate it and fix the underlying warnings.', size_format( $debug_size, 2 ) ),
                null
            );
        }
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $issues[] = self::issue(
                'wp_debug_on',
                'medium',
                'WP_DEBUG is enabled',
                'Disable WP_DEBUG on production (set it in wp-config.php) and keep only WP_DEBUG_LOG temporarily while diagnosing.',
                null
            );
        }
        if ( ! defined( 'WP_POST_REVISIONS' ) && $revisions > 1000 ) {
            $issues[] = self::issue(
                'revisions_unlimited',
                'medium',
                'Revision limit is not configured',
                'Define WP_POST_REVISIONS in wp-config.php (e.g. 5) or delete old revisions.',
                'delete_old_revisions'
            );
        }
        if ( $memory_limit > 0 && $memory_limit < 268435456 ) {
            $issues[] = self::issue(
                'php_memory_low',
                'medium',
                'PHP memory limit is low',
                sprintf( 'memory_limit is %s. 256M or more is recommended for Elementor/WooCommerce sites.', ini_get( 'memory_limit' ) ),
                null
            );
        }
        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            $issues[] = self::issue(
                'php_old',
                'high',
                'PHP version is old',
                'PHP ' . PHP_VERSION . ' is below 8.1. Upgrade PHP for performance and security.',
                null
            );
        }
        if ( $litespeed ) {
            if ( empty( $litespeed['optm-css_min'] ) && empty( $litespeed['optm-js_min'] ) ) {
                $issues[] = self::issue(
                    'litespeed_minify_off',
                    'low',
                    'LiteSpeed CSS/JS minification is off',
                    'Enable CSS/JS minification and HTML minification to reduce transfer size.',
                    'enable_litespeed_minify'
                );
            }
            if ( empty( $litespeed['media-lazy'] ) ) {
                $issues[] = self::issue(
                    'litespeed_lazy_off',
                    'low',
                    'LiteSpeed lazy loading is off',
                    'Enable native lazy loading for below-the-fold images.',
                    'enable_litespeed_lazy'
                );
            }
            if ( empty( $litespeed['cache-browser'] ) ) {
                $issues[] = self::issue(
                    'litespeed_browser_cache_off',
                    'low',
                    'LiteSpeed browser cache is off',
                    'Enable browser cache headers for static assets.',
                    'enable_litespeed_browser_cache'
                );
            }
        }
        if ( $elementor ) {
            if ( 'internal' === $elementor['css_print_method'] ) {
                $issues[] = self::issue(
                    'elementor_css_internal',
                    'low',
                    'Elementor CSS is printed inline',
                    'Switch Elementor CSS print method to external files for better caching.',
                    'enable_elementor_external_css'
                );
            }
            if ( 'active' !== $elementor['optimized_markup'] ) {
                $issues[] = self::issue(
                    'elementor_optimized_markup_off',
                    'low',
                    'Elementor optimized markup is off',
                    'Enable the e_optimized_markup experiment on supported Elementor versions.',
                    'enable_elementor_optimized_markup'
                );
            }
        }

        return array(
            'site'        => array(
                'name'      => get_bloginfo( 'name' ),
                'url'       => home_url( '/' ),
                'wp_version'=> get_bloginfo( 'version' ),
                'php'       => PHP_VERSION,
                'server'    => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
                'theme'     => $theme->get( 'Name' ),
                'child'     => (bool) $theme->parent(),
            ),
            'environment' => array(
                'memory_limit'      => $memory_limit,
                'memory_limit_text' => ini_get( 'memory_limit' ),
                'max_execution'     => (int) ini_get( 'max_execution_time' ),
                'opcache'           => ! empty( $opcache['opcache_enabled'] ),
                'object_cache'      => (bool) $object_cache,
                'object_cache_dropin' => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
                'wp_debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'wp_cache_constant' => defined( 'WP_CACHE' ) && WP_CACHE,
                'wp_post_revisions' => defined( 'WP_POST_REVISIONS' ) ? WP_POST_REVISIONS : null,
            ),
            'database'    => array(
                'size_mb'           => null === $db_size ? null : (float) $db_size,
                'autoload_bytes'    => $options_total,
                'autoload_count'    => $autoload_count,
                'top_autoload'      => is_array( $autoload_rows ) ? $autoload_rows : array(),
                'expired_transients'=> $expired_transients,
                'revisions'         => $revisions,
            ),
            'cache'       => array(
                'page_cache' => $page_cache,
                'litespeed'  => $litespeed,
                'elementor'  => $elementor,
            ),
            'plugins'     => array(
                'active_count' => count( $active_plugins ),
                'matches'      => self::plugin_matches( $active_plugins ),
            ),
            'issues'      => $issues,
        );
    }

    /**
     * Analyse one rendered page.
     *
     * @param int    $page_id Page ID.
     * @param string $url     Relative path or same-origin URL.
     * @return array|WP_Error
     */
    public static function page( $page_id = 0, $url = '' ) {
        if ( $page_id ) {
            $permalink = get_permalink( (int) $page_id );
            if ( ! $permalink ) {
                return new WP_Error( 'invalid_page', 'Page not found: ' . $page_id, array( 'status' => 404 ) );
            }
            $target = $permalink;
        } elseif ( '' !== $url ) {
            $target = self::same_origin_url( $url );
            if ( is_wp_error( $target ) ) {
                return $target;
            }
        } else {
            $target = home_url( '/' );
        }

        $start  = microtime( true );
        // Same-origin check above is the SSRF guard; wp_safe_remote_get would
        // also block local/staging hosts, which PressAgent explicitly supports.
        $response = wp_remote_get( $target, array(
            'timeout'             => 15,
            'redirection'         => 2,
            'limit_response_size' => 3 * 1024 * 1024,
            'user-agent'          => 'PressAgent Performance Audit; ' . home_url( '/' ),
        ) );
        $elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $html = (string) wp_remote_retrieve_body( $response );
        $headers = wp_remote_retrieve_headers( $response );
        $html = (string) $html;

        $head = $html;
        if ( preg_match( '/<head\b[^>]*>(.*?)<\/head>/is', $html, $m ) ) {
            $head = $m[1];
        }

        $scripts_src   = preg_match_all( '/<script\b[^>]*\bsrc\s*=/i', $html );
        $inline_scripts= preg_match_all( '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script>/is', $html );
        $styles        = preg_match_all( '/<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*>/i', $html );
        $inline_styles = preg_match_all( '/<style\b[^>]*>.*?<\/style>/is', $html );
        $blocking      = preg_match_all( '/<script\b(?![^>]*\b(?:async|defer|type\s*=\s*["\']module["\'])[^>]*)src\s*=/i', $head );

        $images        = preg_match_all( '/<img\b[^>]*>/i', $html, $img_tags );
        $images        = is_array( $img_tags[0] ) ? count( $img_tags[0] ) : 0;
        $missing_alt   = 0;
        $missing_dims  = 0;
        $lazy          = 0;
        foreach ( (array) ( $img_tags[0] ?? array() ) as $tag ) {
            if ( ! preg_match( '/\balt\s*=/i', $tag ) ) {
                $missing_alt++;
            }
            if ( ! preg_match( '/\bwidth\s*=/i', $tag ) || ! preg_match( '/\bheight\s*=/i', $tag ) ) {
                $missing_dims++;
            }
            if ( preg_match( '/\bloading\s*=\s*["\']lazy["\']/i', $tag ) || preg_match( '/\bdata-lazy/i', $tag ) ) {
                $lazy++;
            }
        }

        $elementor_css = preg_match_all( '/\/elementor\/css\//i', $html );
        $elementor_js  = preg_match_all( '/\/elementor\/assets\/js\//i', $html );
        $google_fonts  = preg_match_all( '/fonts\.(?:googleapis|gstatic)\.com/i', $html );

        preg_match_all( '/\b(?:src|href)\s*=\s*["\'](https?:\/\/[^"\']+)/i', $html, $urls );
        $hosts = array();
        foreach ( (array) ( $urls[1] ?? array() ) as $absolute ) {
            $host = wp_parse_url( $absolute, PHP_URL_HOST );
            if ( $host && $host !== wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
                $hosts[ $host ] = true;
            }
        }

        $compression = '';
        if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
            $compression = (string) ( $headers['content-encoding'] ?? '' );
        } elseif ( is_array( $headers ) ) {
            $compression = (string) ( $headers['content-encoding'] ?? '' );
        }

        $cache_header = '';
        foreach ( array( 'x-litespeed-cache', 'cf-cache-status', 'x-cache', 'x-cache-status', 'age' ) as $name ) {
            $value = is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ? ( $headers[ $name ] ?? '' ) : ( is_array( $headers ) ? ( $headers[ $name ] ?? '' ) : '' );
            if ( '' !== $value ) {
                $cache_header = $name . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value );
                break;
            }
        }

        $metrics = array(
            'url'                => $target,
            'status'             => $code,
            'time_ms'            => $elapsed,
            'html_bytes'         => strlen( $html ),
            'scripts'            => (int) $scripts_src,
            'inline_scripts'     => (int) $inline_scripts,
            'stylesheets'        => (int) $styles,
            'inline_styles'      => (int) $inline_styles,
            'head_blocking_scripts' => (int) $blocking,
            'images'             => (int) $images,
            'images_missing_alt' => $missing_alt,
            'images_missing_dimensions' => $missing_dims,
            'images_lazy'        => $lazy,
            'elementor_css'      => (int) $elementor_css,
            'elementor_js'       => (int) $elementor_js,
            'google_fonts'       => (int) $google_fonts,
            'external_hosts'     => array_keys( $hosts ),
            'compression'        => $compression,
            'cache_header'       => $cache_header,
        );

        $issues = array();
        if ( $code >= 400 ) {
            $issues[] = self::issue( 'page_http_error', 'high', 'Page returned HTTP ' . $code, 'Fix the error before optimizing performance.', null );
        }
        if ( $elapsed > 2500 ) {
            $issues[] = self::issue( 'page_slow', 'high', 'Server response is slow', sprintf( 'The page took %d ms to respond. Investigate TTFB, caching and heavy plugins.', $elapsed ), null );
        } elseif ( $elapsed > 1200 ) {
            $issues[] = self::issue( 'page_warmup', 'medium', 'Page response is above 1.2s', sprintf( 'The page took %d ms. Aim for < 1s with page cache and object cache.', $elapsed ), null );
        }
        if ( strlen( $html ) > 512000 ) {
            $issues[] = self::issue( 'html_large', 'medium', 'HTML document is large', sprintf( '%s of HTML. Reduce inline CSS/JS and Elementor markup.', size_format( strlen( $html ), 2 ) ), null );
        }
        if ( $scripts_src > 30 ) {
            $issues[] = self::issue( 'many_scripts', 'medium', 'Many JavaScript files', sprintf( '%d external scripts. Combine/defer non-critical scripts.', $scripts_src ), null );
        }
        if ( $blocking > 5 ) {
            $issues[] = self::issue( 'render_blocking', 'high', 'Render-blocking scripts in head', sprintf( '%d blocking scripts. Defer or move them and enable LiteSpeed JS defer.', $blocking ), null );
        }
        if ( $missing_dims > 3 ) {
            $issues[] = self::issue( 'images_missing_dimensions', 'medium', 'Images without width/height', sprintf( '%d images lack dimensions and can cause layout shift (CLS).', $missing_dims ), null );
        }
        if ( $missing_alt > 0 ) {
            $issues[] = self::issue( 'images_missing_alt', 'low', 'Images without alt text', sprintf( '%d images have no alt attribute.', $missing_alt ), null );
        }
        if ( '' === $compression && strlen( $html ) > 51200 ) {
            $issues[] = self::issue( 'no_compression', 'medium', 'HTML is not compressed', 'Enable gzip/brotli compression at the server or cache layer.', null );
        }
        if ( '' === $cache_header ) {
            $issues[] = self::issue( 'no_cache_headers', 'low', 'No cache header detected', 'Check that the page is served from LiteSpeed/your page cache.', null );
        }
        if ( $elementor_css > 5 ) {
            $issues[] = self::issue( 'elementor_css_many', 'low', 'Many Elementor CSS files', sprintf( '%d Elementor CSS files. Switch to external CSS + optimized CSS files or combine them.', $elementor_css ), 'enable_elementor_external_css' );
        }
        if ( $google_fonts > 0 ) {
            $issues[] = self::issue( 'external_google_fonts', 'low', 'External Google Fonts', 'Host fonts locally or disable Google Fonts in Elementor to remove a third-party dependency.', null );
        }

        return array( 'metrics' => $metrics, 'issues' => $issues );
    }

    /**
     * Apply a safe performance fix.
     *
     * @param string $fix_id   Fix identifier.
     * @param array  $params   Optional params (age_days, limit).
     * @param bool   $confirm  Required for destructive fixes.
     * @return array|WP_Error
     */
    public static function fix( $fix_id, $params = array(), $confirm = false ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to change performance settings.', array( 'status' => 403 ) );
        }

        $litespeed_fixes = array(
            'enable_litespeed_cache'         => array( 'cache' => 1 ),
            'enable_litespeed_minify'        => array( 'optm-css_min' => 1, 'optm-js_min' => 1, 'optm-html_min' => 1 ),
            'enable_litespeed_lazy'          => array( 'media-lazy' => 1 ),
            'enable_litespeed_browser_cache' => array( 'cache-browser' => 1 ),
        );
        if ( isset( $litespeed_fixes[ $fix_id ] ) ) {
            if ( ! defined( 'LSCWP_V' ) ) {
                return new WP_Error( 'litespeed_missing', 'LiteSpeed Cache is not active.', array( 'status' => 409 ) );
            }
            $result = PressAgent_Plugin_Registry::write_settings( 'litespeed-cache', $litespeed_fixes[ $fix_id ] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            do_action( 'litespeed_purge_all' );
            return array( 'fix' => $fix_id, 'changed' => $litespeed_fixes[ $fix_id ], 'snapshot_ids' => $result['snapshot_ids'] ?? array() );
        }

        $elementor_fixes = array(
            'enable_elementor_external_css'       => array( 'elementor_css_print_method' => 'external' ),
            'enable_elementor_element_cache'      => array( 'elementor_element_cache_ttl' => '24' ),
            'enable_elementor_optimized_markup'   => array( 'elementor_experiment-e_optimized_markup' => 'active' ),
            'enable_elementor_optimized_css'      => array( 'elementor_experiment-e_optimized_css_files' => 'active' ),
        );
        if ( isset( $elementor_fixes[ $fix_id ] ) ) {
            if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
                return new WP_Error( 'elementor_missing', 'Elementor is not active.', array( 'status' => 409 ) );
            }
            $result = PressAgent_Plugin_Registry::write_settings( 'elementor', $elementor_fixes[ $fix_id ] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            return array( 'fix' => $fix_id, 'changed' => $elementor_fixes[ $fix_id ], 'snapshot_ids' => $result['snapshot_ids'] ?? array() );
        }

        if ( 'purge_cache' === $fix_id ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
            return array( 'fix' => 'purge_cache', 'message' => 'All caches purged.' );
        }

        if ( 'delete_expired_transients' === $fix_id ) {
            return self::delete_expired_transients( (int) ( $params['limit'] ?? 500 ) );
        }

        if ( 'delete_old_revisions' === $fix_id ) {
            if ( true !== $confirm ) {
                return new WP_Error( 'confirmation_required', 'Revision deletion is destructive. Call again with confirm:true after user approval.', array( 'status' => 409 ) );
            }
            return self::delete_old_revisions( (int) ( $params['age_days'] ?? 30 ), (int) ( $params['limit'] ?? 200 ) );
        }

        if ( 'cleanup_database' === $fix_id ) {
            if ( true !== $confirm ) {
                return new WP_Error( 'confirmation_required', 'Database cleanup is destructive. Call again with confirm:true after user approval.', array( 'status' => 409 ) );
            }
            return self::cleanup_database( (int) ( $params['limit'] ?? 1000 ) );
        }

        return new WP_Error( 'unknown_fix', 'Unknown performance fix: ' . $fix_id, array( 'status' => 400 ) );
    }

    /* ---------------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------------- */

    private static function detect_page_cache() {
        $plugin  = null;
        $enabled = null;
        if ( defined( 'LSCWP_V' ) ) {
            $plugin = 'LiteSpeed Cache ' . LSCWP_V;
            $read   = PressAgent_Plugin_Registry::read_settings( 'litespeed-cache', array( 'cache' ) );
            if ( ! is_wp_error( $read ) ) {
                $enabled = ! empty( $read['settings']['cache'] );
            }
        } elseif ( defined( 'WP_ROCKET_VERSION' ) ) {
            $plugin  = 'WP Rocket ' . WP_ROCKET_VERSION;
            $enabled = true;
        } elseif ( defined( 'W3TC' ) ) {
            $plugin  = 'W3 Total Cache';
            $enabled = true;
        } elseif ( defined( 'WPCACHEHOME' ) || function_exists( 'wp_cache_clear_cache' ) ) {
            $plugin  = 'WP Super Cache';
            $enabled = true;
        }
        return array( 'plugin' => $plugin, 'enabled' => $enabled );
    }

    private static function litespeed_snapshot() {
        if ( ! defined( 'LSCWP_V' ) ) {
            return null;
        }
        $read = PressAgent_Plugin_Registry::read_settings( 'litespeed-cache', array( 'cache', 'cache-browser', 'optm-css_min', 'optm-js_min', 'optm-html_min', 'media-lazy', 'optm-js_defer' ) );
        if ( is_wp_error( $read ) ) {
            return array();
        }
        return $read['settings'];
    }

    private static function elementor_snapshot() {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return null;
        }
        return array(
            'version'          => ELEMENTOR_VERSION,
            'css_print_method' => get_option( 'elementor_css_print_method', 'external' ),
            'element_cache_ttl'=> get_option( 'elementor_element_cache_ttl', '' ),
            'optimized_markup' => get_option( 'elementor_experiment-e_optimized_markup', 'default' ),
            'optimized_css'    => get_option( 'elementor_experiment-e_optimized_css_files', 'default' ),
        );
    }

    private static function plugin_matches( $active_plugins ) {
        $known = array(
            'elementor/elementor.php'          => 'Elementor',
            'woocommerce/woocommerce.php'      => 'WooCommerce',
            'wordpress-seo/wp-seo.php'         => 'Yoast SEO',
            'seo-by-rank-math/rank-math.php'   => 'Rank Math',
            'wordfence/wordfence.php'          => 'Wordfence',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'AIOS',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wpforms-lite/wpforms.php'         => 'WPForms Lite',
            'contact-form-7/wp-contact-form-7.php' => 'Contact Form 7',
            'wp-mail-smtp/wp_mail_smtp.php'    => 'WP Mail SMTP',
            'redirection/redirection.php'      => 'Redirection',
            'really-simple-ssl/rlrsssl-really-simple-ssl.php' => 'Really Simple Security',
        );
        $matches = array();
        foreach ( $known as $file => $name ) {
            if ( in_array( $file, $active_plugins, true ) ) {
                $matches[] = $name;
            }
        }
        return $matches;
    }

    private static function same_origin_url( $url ) {
        $home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        if ( 0 === strpos( $url, '/' ) ) {
            return home_url( $url );
        }
        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
            return home_url( '/' . ltrim( $url, '/' ) );
        }
        if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || $parts['host'] !== $home_host ) {
            return new WP_Error( 'invalid_url', 'Only URLs on the configured WordPress site are allowed.', array( 'status' => 400 ) );
        }
        return esc_url_raw( $url );
    }

    private static function delete_expired_transients( $limit ) {
        global $wpdb;
        $limit  = min( 2000, max( 1, $limit ) );
        $names  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP() LIMIT %d",
                $limit
            )
        );
        $deleted = 0;
        foreach ( (array) $names as $timeout_name ) {
            $key = str_replace( '_transient_timeout_', '', $timeout_name );
            delete_option( $timeout_name );
            delete_option( '_transient_' . $key );
            $deleted++;
        }
        return array( 'fix' => 'delete_expired_transients', 'deleted' => $deleted, 'remaining_batch' => count( (array) $names ) >= $limit );
    }

    private static function cleanup_database( $limit ) {
        global $wpdb;
        $limit = min( 5000, max( 1, $limit ) );

        $orphan_meta = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL LIMIT %d",
                $limit
            )
        );
        $orphan_term_relationships = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL LIMIT %d",
                $limit
            )
        );
        $trashed_comments = (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash' LIMIT %d", $limit )
        );
        $expired = self::delete_expired_transients( $limit );

        return array(
            'fix'                       => 'cleanup_database',
            'orphaned_postmeta'         => $orphan_meta,
            'orphaned_term_relationships'=> $orphan_term_relationships,
            'trashed_comments'          => $trashed_comments,
            'expired_transients'        => $expired['deleted'] ?? 0,
            'message'                   => 'Database cleanup executed. Optimize tables from the host if needed.',
        );
    }

    private static function delete_old_revisions( $age_days, $limit ) {
        global $wpdb;
        $age_days = max( 1, $age_days );
        $limit    = min( 1000, max( 1, $limit ) );
        $ids      = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_modified_gmt < DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d DAY) ORDER BY ID ASC LIMIT %d",
                $age_days,
                $limit
            )
        );
        $deleted = 0;
        foreach ( (array) $ids as $id ) {
            if ( wp_delete_post_revision( (int) $id ) ) {
                $deleted++;
            }
        }
        $remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
        return array( 'fix' => 'delete_old_revisions', 'deleted' => $deleted, 'remaining' => $remaining, 'batch_full' => count( (array) $ids ) >= $limit );
    }

    private static function parse_bytes( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value || '-1' === $value ) {
            return -1;
        }
        $unit = strtolower( substr( $value, -1 ) );
        $num  = (int) $value;
        switch ( $unit ) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
        }
        return $num;
    }

    private static function issue( $id, $severity, $title, $detail, $fix_id ) {
        return array(
            'id'       => $id,
            'severity' => $severity,
            'title'    => $title,
            'detail'   => $detail,
            'fix_id'   => $fix_id,
        );
    }
}
