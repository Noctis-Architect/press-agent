<?php
/**
 * Minimal WordPress stub harness for the plugin registry.
 * Run: php tests/php/registry-smoke.php
 */

define( 'ABSPATH', sys_get_temp_dir() . '/pressagent-test-wp/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );

$GLOBALS['pa_options'] = array();
$GLOBALS['pa_tests']   = 0;
$GLOBALS['pa_failures'] = array();

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function current_user_can( $cap ) { return true; }
function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['pa_options'] ) ? $GLOBALS['pa_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['pa_options'][ $key ] = $value;
    return true;
}
function delete_option( $key ) {
    unset( $GLOBALS['pa_options'][ $key ] );
    return true;
}
function get_site_option( $key, $default = false ) { return get_option( $key, $default ); }
function update_site_option( $key, $value ) { return update_option( $key, $value ); }
function is_multisite() { return false; }
function is_plugin_active( $file ) { return false; }
function is_plugin_active_for_network( $file ) { return false; }
function get_plugin_data( $file, $markup = true, $translate = true ) { return array( 'Version' => '0.0.0' ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_textarea_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function wp_kses_post( $value ) { return (string) $value; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function rest_sanitize_boolean( $value ) {
    if ( is_string( $value ) ) {
        $value = strtolower( $value );
        return ! in_array( $value, array( '', '0', 'false', 'no', 'off' ), true );
    }
    return (bool) $value;
}
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( str_repeat( 'a1b2c3d4e5', 5 ), 0, $length ); }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
function map_deep( $value, $callback ) {
    if ( is_array( $value ) ) {
        return array_map( function ( $item ) use ( $callback ) { return map_deep( $item, $callback ); }, $value );
    }
    return call_user_func( $callback, $value );
}

require_once __DIR__ . '/../../plugin/includes/class-plugin-registry.php';
require_once __DIR__ . '/../../plugin/includes/class-supported-plugin-actions.php';

function check( $label, $condition ) {
    $GLOBALS['pa_tests']++;
    if ( $condition ) {
        echo "PASS  {$label}\n";
    } else {
        echo "FAIL  {$label}\n";
        $GLOBALS['pa_failures'][] = $label;
    }
}

$all = PressAgent_Plugin_Registry::all();
check( 'registry loads 12 plugins', 12 === count( $all ) );
check( 'all entries have a plugin file', ! array_filter( $all, function ( $p ) { return empty( $p['file'] ); } ) );

foreach ( array( 'elementor', 'litespeed-cache', 'woocommerce', 'wordpress-seo', 'seo-by-rank-math', 'wordfence', 'all-in-one-wp-security-and-firewall', 'redirection', 'wp-mail-smtp', 'really-simple-ssl', 'wpforms-lite', 'contact-form-7' ) as $slug ) {
    $catalog = PressAgent_Plugin_Registry::get_catalog( $slug );
    check( "catalog {$slug}", ! is_wp_error( $catalog ) && $catalog['slug'] === $slug );
}

$GLOBALS['pa_options']['elementor_css_print_method'] = 'external';
$read = PressAgent_Plugin_Registry::read_settings( 'elementor', array( 'elementor_css_print_method' ) );
check( 'elementor read', ! is_wp_error( $read ) && 'external' === $read['settings']['elementor_css_print_method'] );

$write = PressAgent_Plugin_Registry::write_settings( 'elementor', array( 'elementor_css_print_method' => 'internal' ) );
check( 'elementor write', ! is_wp_error( $write ) && 'internal' === $GLOBALS['pa_options']['elementor_css_print_method'] );
check( 'elementor write returns snapshot', ! is_wp_error( $write ) && ! empty( $write['snapshot_ids'][0] ) );

$snapshot_id = ! is_wp_error( $write ) ? $write['snapshot_ids'][0] : '';
$restore = $snapshot_id ? PressAgent_Plugin_Registry::restore_setting( $snapshot_id ) : new WP_Error();
check( 'elementor restore', ! is_wp_error( $restore ) && 'external' === $GLOBALS['pa_options']['elementor_css_print_method'] );

$GLOBALS['pa_options']['elementor_cpt_support'] = array( 'page', 'post' );
$merge = PressAgent_Plugin_Registry::write_settings( 'elementor', array( 'elementor_cpt_support' => array( 'page', 'post', 'product' ) ) );
check( 'array replace', ! is_wp_error( $merge ) && array( 'page', 'post', 'product' ) === $GLOBALS['pa_options']['elementor_cpt_support'] );

$unknown = PressAgent_Plugin_Registry::write_settings( 'elementor', array( 'not_a_setting' => 1 ) );
check( 'unknown setting rejected', is_wp_error( $unknown ) );

$bad_type = PressAgent_Plugin_Registry::write_settings( 'elementor', array( 'elementor_css_print_method' => array( 'x' ) ) );
check( 'array for scalar rejected', is_wp_error( $bad_type ) );

$GLOBALS['pa_options']['aio_wp_security_configs'] = array( 'aiowps_enable_login_lockdown' => '', 'other' => 'keep' );
$aios = PressAgent_Plugin_Registry::write_settings( 'all-in-one-wp-security-and-firewall', array( 'aio_wp_security_configs' => array( 'aiowps_max_login_attempts' => '9' ) ) );
check( 'aios config merge', ! is_wp_error( $aios ) && 9 === $GLOBALS['pa_options']['aio_wp_security_configs']['aiowps_max_login_attempts'] && 'keep' === $GLOBALS['pa_options']['aio_wp_security_configs']['other'] );

check( 'aios unknown field rejected', is_wp_error( PressAgent_Plugin_Registry::write_settings( 'all-in-one-wp-security-and-firewall', array( 'aio_wp_security_configs' => array( 'nope' => 'x' ) ) ) ) );

$GLOBALS['pa_options']['aio_wp_security_configs'] = array( 'aiowps_captcha_secret_key' => 'super-secret' );
$secret_read = PressAgent_Plugin_Registry::read_settings( 'all-in-one-wp-security-and-firewall', array( 'aio_wp_security_configs' ), array( 'aiowps_captcha_secret_key' ) );
check( 'undeclared secret not readable', ! is_wp_error( $secret_read ) && ! array_key_exists( 'aiowps_captcha_secret_key', $secret_read['settings']['aio_wp_security_configs'] ) );

check( 'redirection actions present', isset( $all['redirection']['actions']['create_redirect'] ) );

$redirection_catalog = PressAgent_Plugin_Registry::get_catalog( 'redirection' );
$catalog_json = wp_json_encode( $redirection_catalog );
check( 'catalog is JSON-safe (no closures/handlers)', false !== $catalog_json && false === strpos( $catalog_json, 'handler' ) );
check( 'catalog keeps action params', isset( $redirection_catalog['actions'][1]['params']['target'] ) );
check( 'wordfence bool setting typed', 'bool' === $all['wordfence']['settings']['firewallEnabled']['type'] );
check( 'yoast seo provider', 'yoast' === $all['wordpress-seo']['seo']['provider'] );
check( 'rankmath seo provider', 'rankmath' === $all['seo-by-rank-math']['seo']['provider'] );

echo "\n" . ( $GLOBALS['pa_tests'] - count( $GLOBALS['pa_failures'] ) ) . "/{$GLOBALS['pa_tests']} checks passed.\n";
exit( empty( $GLOBALS['pa_failures'] ) ? 0 : 1 );
