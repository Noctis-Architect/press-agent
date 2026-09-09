<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Security_Audit {
    public static function get_summary() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        $plugin_info = array();
        foreach ( $plugins as $path => $data ) {
            if ( in_array( $path, $active_plugins, true ) ) {
                $plugin_info[] = array(
                    'name' => $data['Name'],
                    'version' => $data['Version'],
                    'path' => $path
                );
            }
        }

        return array(
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'active_plugins' => $plugin_info,
            'wp_config_writable' => is_writable( ABSPATH . 'wp-config.php' ),
            'htaccess_exists' => file_exists( ABSPATH . '.htaccess' )
        );
    }
}
