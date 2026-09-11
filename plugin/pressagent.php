<?php
/**
 * Plugin Name: PressAgent
 * Description: The Autonomous AI Ops Bridge for WordPress
 * Version: 1.0.1
 * Author: PressAgent Team
 * Text Domain: pressagent
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PRESSAGENT_VERSION', '1.0.1' );
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
require_once PRESSAGENT_PLUGIN_DIR . 'includes/class-updater.php';

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
    PressAgent_Updater::init();
}
add_action( 'plugins_loaded', 'pressagent_init' );

function pressagent_admin_menu() {
    $update_badge = '';
    if ( class_exists( 'PressAgent_Updater' ) ) {
        $info = PressAgent_Updater::check_update( false );
        if ( ! empty( $info['has_update'] ) ) {
            $update_badge = ' <span class="update-plugins count-1" style="background-color:#dc2626;color:#ffffff;border-radius:10px;padding:2px 6px;font-size:10px;font-weight:700;display:inline-block;line-height:1;"><span class="update-count">1</span></span>';
        }
    }

    // High-tech AI Ops Hexagon SVG Icon
    $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="#a7aaad" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' .
        '<polygon points="10 2 18 6.5 18 13.5 10 18 2 13.5 2 6.5 10 2"/>' .
        '<circle cx="10" cy="10" r="2.5" fill="#a7aaad"/>' .
        '<line x1="10" y1="2" x2="10" y2="7.5"/>' .
        '<line x1="10" y1="12.5" x2="10" y2="18"/>' .
        '<line x1="2" y1="6.5" x2="6.8" y2="9"/>' .
        '<line x1="13.2" y1="11" x2="18" y2="13.5"/>' .
        '</svg>'
    );

    // Dedicated Top-Level Menu
    add_menu_page(
        'PressAgent Ops Console',
        'PressAgent' . $update_badge,
        'manage_options',
        'pressagent',
        'pressagent_settings_page',
        $icon_svg,
        58.6
    );

    // Submenu Items
    add_submenu_page(
        'pressagent',
        'PressAgent Console',
        'Console & Tools',
        'manage_options',
        'pressagent',
        'pressagent_settings_page'
    );

    add_submenu_page(
        'pressagent',
        'PressAgent Updates',
        'Updates & Sync' . $update_badge,
        'manage_options',
        'pressagent#updates',
        'pressagent_settings_page'
    );

    // Backward compatibility for existing bookmarks/links to options-general.php?page=pressagent-settings
    add_submenu_page(
        null,
        'PressAgent Settings',
        'PressAgent Settings',
        'manage_options',
        'pressagent-settings',
        'pressagent_settings_page'
    );
}
add_action( 'admin_menu', 'pressagent_admin_menu' );

function pressagent_settings_page() {
    require_once PRESSAGENT_PLUGIN_DIR . 'templates/admin-settings.php';
}

