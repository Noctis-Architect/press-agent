<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Generic_Settings {
    public static function read_option( $plugin_slug, $key ) {
        if ( ! self::_is_allowed( $plugin_slug, $key ) ) {
            return new WP_Error( 'forbidden_key', 'This settings key is not in the allowlist.', array( 'status' => 403 ) );
        }
        return get_option( $key );
    }

    public static function write_option( $plugin_slug, $key, $value ) {
        if ( ! self::_is_allowed( $plugin_slug, $key ) ) {
            return new WP_Error( 'forbidden_key', 'This settings key is not in the allowlist.', array( 'status' => 403 ) );
        }
        
        PressAgent_Action_Guard::create_snapshot( 'update_option', array( 'option_name' => $key ) );
        
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
        }
        if ( $plugin_slug === 'litespeed' || $plugin_slug === 'all' ) {
            if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
                \LiteSpeed_Cache_API::purge_all();
            }
        }
        return rest_ensure_response( array( 'message' => 'Cache purged.' ) );
    }

    public static function get_cache_status() {
        return rest_ensure_response( array(
            'wp_rocket' => function_exists( 'rocket_clean_domain' ),
            'litespeed' => class_exists( 'LiteSpeed_Cache_API' )
        ) );
    }

    private static function _is_allowed( $plugin_slug, $key ) {
        $allowlist = get_option( 'pressagent_settings_allowlist', array() );
        if ( ! isset( $allowlist[$plugin_slug] ) ) return false;
        
        foreach ( $allowlist[$plugin_slug] as $allowed_pattern ) {
            if ( $allowed_pattern === $key ) return true;
            if ( str_ends_with( $allowed_pattern, '*' ) ) {
                $prefix = rtrim( $allowed_pattern, '*' );
                if ( str_starts_with( $key, $prefix ) ) return true;
            }
        }
        return false;
    }
}
