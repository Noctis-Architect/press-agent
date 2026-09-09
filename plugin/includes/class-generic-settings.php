<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Generic_Settings {
    public static function read_option( $plugin_slug, $key ) {
        if ( ! self::is_allowed( $key ) ) {
            return new WP_Error( 'forbidden_key', 'This settings key is not in the allowlist.', array( 'status' => 403 ) );
        }
        return get_option( $key );
    }

    public static function write_option( $plugin_slug, $key, $value ) {
        if ( ! self::is_allowed( $key ) ) {
            return new WP_Error( 'forbidden_key', 'This settings key is not in the allowlist.', array( 'status' => 403 ) );
        }

        // Protect the allowlist itself: only a logged-in administrator may modify it.
        if ( $key === 'pressagent_settings_allowlist' && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden_key', 'The settings allowlist cannot be modified via API.', array( 'status' => 403 ) );
        }

        PressAgent_Action_Guard::create_snapshot( 'update_option', array( 'option_name' => $key ) );

        // Only proceed with the actual privilege escalation if the current user is
        // a logged-in administrator. Never silently impersonate the first admin.
        if ( ! current_user_can( 'manage_options' ) && in_array( $key, array( 'users_can_register', 'default_role', 'active_plugins', 'siteurl', 'home', 'admin_email' ), true ) ) {
            return new WP_Error( 'forbidden_key', 'This option requires administrator privileges.', array( 'status' => 403 ) );
        }

        update_option( $key, $value );
        
        if ( in_array( $plugin_slug, array( 'wp_rocket', 'litespeed' ), true ) ) {
            self::purge_cache( $plugin_slug );
        }
        
        return rest_ensure_response( array( 'message' => 'Option updated.' ) );
    }

    public static function purge_cache( $plugin_slug = 'all' ) {
        if ( $plugin_slug === 'wp_rocket' || $plugin_slug === 'all' ) {
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            // Remove WP Rocket cache files if directory exists
            $rocket_cache = WP_CONTENT_DIR . '/cache/wp-rocket';
            if ( is_dir( $rocket_cache ) ) {
                self::_recursive_rmdir( $rocket_cache );
            }
        }
        if ( $plugin_slug === 'litespeed' || $plugin_slug === 'all' ) {
            if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
                \LiteSpeed_Cache_API::purge_all();
            }
            do_action( 'litespeed_purge_all' );
        }
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        return rest_ensure_response( array( 'message' => 'Cache purged.' ) );
    }

    private static function _recursive_rmdir( $dir ) {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( $object !== '.' && $object !== '..' ) {
                    if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . '/' . $object ) ) {
                        self::_recursive_rmdir( $dir . DIRECTORY_SEPARATOR . $object );
                    } else {
                        @unlink( $dir . DIRECTORY_SEPARATOR . $object );
                    }
                }
            }
            @rmdir( $dir );
        }
    }

    public static function get_cache_status() {
        return rest_ensure_response( array(
            'wp_rocket' => function_exists( 'rocket_clean_domain' ),
            'litespeed' => class_exists( 'LiteSpeed_Cache_API' )
        ) );
    }

    private static $_builtin_allowlist = array(
        'wordpress' => array(
            'blogname',
            'blogdescription',
            'show_on_front',
            'page_on_front',
            'page_for_posts',
            'posts_per_page',
            'date_format',
            'time_format',
            'start_of_week',
            'WPLANG',
            'blog_public',
            'default_pingback_flag',
            'default_ping_status',
            'default_comment_status',
            'permalink_structure',
            'category_base',
            'tag_base',
            'timezone_string',
            'gmt_offset',
        ),
    );

    public static function is_allowed( $key ) {
        // Check built-in allowlist first
        foreach ( self::$_builtin_allowlist as $builtin_keys ) {
            if ( in_array( $key, $builtin_keys, true ) ) {
                return true;
            }
        }

        // Then check dynamic allowlist (patterns apply to any plugin slug)
        $allowlist = get_option( 'pressagent_settings_allowlist', array() );
        foreach ( $allowlist as $patterns ) {
            if ( ! is_array( $patterns ) ) continue;
            foreach ( $patterns as $allowed_pattern ) {
                if ( $allowed_pattern === $key ) return true;
                if ( is_string( $allowed_pattern ) && str_ends_with( $allowed_pattern, '*' ) ) {
                    $prefix = rtrim( $allowed_pattern, '*' );
                    if ( str_starts_with( $key, $prefix ) ) return true;
                }
            }
        }
        return false;
    }
}
