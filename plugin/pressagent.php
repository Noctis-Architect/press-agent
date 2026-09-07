<?php
/**
 * Plugin Name: PressAgent
 * Description: The Autonomous AI Ops Bridge for WordPress
 * Version: 1.0.0
 * Author: PressAgent Team
 * Text Domain: pressagent
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PRESSAGENT_VERSION', '1.0.0' );
define( 'PRESSAGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRESSAGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-auth.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-action-guard.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-pages-controller.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-elementor-adapter.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-generic-settings.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-debug-log-parser.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-security-audit.php';
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-code-executor.php';

function pressagent_activate() {
    PressAgent_Action_Guard::create_table();
    if ( ! get_option( 'pressagent_settings_allowlist' ) ) {
        add_option( 'pressagent_settings_allowlist', array(
            'wp_rocket' => array( 'wp_rocket_settings' ),
            'wordfence' => array( 'wordfence*' ),
            'litespeed' => array( 'litespeed.conf.*' )
        ) );
    }
}
register_activation_hook( __FILE__, 'pressagent_activate' );

function pressagent_init() {
    new PressAgent_Auth();
    new PressAgent_REST_API();
}
add_action( 'plugins_loaded', 'pressagent_init' );

function pressagent_admin_menu() {
    add_options_page(
        'PressAgent Settings',
        'PressAgent',
        'manage_options',
        'pressagent-settings',
        'pressagent_settings_page'
    );
}
add_action( 'admin_menu', 'pressagent_admin_menu' );

function pressagent_settings_page() {
    require_once PRESSAGENT_PLUGIN_DIR . 'templates/admin-settings.php';
}
