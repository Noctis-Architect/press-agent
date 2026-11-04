<?php
/**
 * WordPress core settings manager smoke tests with stubs.
 * Run: php tests/php/wp-settings-smoke.php
 */

define( 'ABSPATH', sys_get_temp_dir() . '/pressagent-wp-settings-test/' );

$GLOBALS['options'] = array(
    'blogname' => 'Old Site',
    'posts_per_page' => '10',
    'show_on_front' => 'posts',
    'default_role' => 'subscriber',
    'siteurl' => 'https://example.com',
    'home' => 'https://example.com',
    'permalink_structure' => '/%postname%/',
);

class WP_Error {
    public $code;
    public $message;
    public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function current_user_can( $cap ) { return true; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function rest_sanitize_boolean( $value ) {
    if ( is_string( $value ) ) {
        $value = strtolower( $value );
        return ! in_array( $value, array( '', '0', 'false', 'no', 'off' ), true );
    }
    return (bool) $value;
}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9\-]/', '-', (string) $value ) ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_post( $id ) {
    if ( 5 === (int) $id ) { return (object) array( 'ID' => 5, 'post_type' => 'page' ); }
    if ( 7 === (int) $id ) { return (object) array( 'ID' => 7, 'post_type' => 'post' ); }
    if ( 9 === (int) $id ) { return (object) array( 'ID' => 9, 'post_type' => 'attachment' ); }
    return null;
}
function wp_attachment_is_image( $id ) { return 9 === (int) $id; }
function wp_roles() {
    return new class {
        public function is_role( $role ) { return in_array( $role, array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ), true ); }
    };
}
function flush_rewrite_rules( $hard = true ) { $GLOBALS['flushed'] = true; }
function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( str_repeat( 'a1b2c3', 5 ), 0, $length ); }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }

require_once __DIR__ . '/../../plugin/includes/class-wp-settings-manager.php';

function check( $label, $condition ) {
    $GLOBALS['tests'] = ( $GLOBALS['tests'] ?? 0 ) + 1;
    if ( $condition ) { echo "PASS  {$label}\n"; }
    else { echo "FAIL  {$label}\n"; $GLOBALS['failures'][] = $label; }
}

$settings = PressAgent_WP_Settings_Manager::get( array( 'general', 'reading' ) );
check( 'get general/reading', 'Old Site' === $settings['general']['blogname'] && 10 === $settings['reading']['posts_per_page'] );

$res = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'blogname' => 'New Site' ) ) );
check( 'set blogname', ! is_wp_error( $res ) && 'New Site' === $GLOBALS['options']['blogname'] && ! empty( $res['snapshot_ids'][0] ) );

$snapshot = $res['snapshot_ids'][0];
$rb = PressAgent_WP_Settings_Manager::rollback( $snapshot );
check( 'rollback blogname', ! is_wp_error( $rb ) && 'Old Site' === $GLOBALS['options']['blogname'] );

$bad = PressAgent_WP_Settings_Manager::set( array( 'reading' => array( 'posts_per_page' => 0 ) ) );
check( 'invalid posts_per_page rejected', is_wp_error( $bad ) );

$bad = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'default_role' => 'nope' ) ) );
check( 'invalid role rejected', is_wp_error( $bad ) );

$bad = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'admin_email' => 'nope' ) ) );
check( 'invalid email rejected', is_wp_error( $bad ) );

$bad = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'siteurl' => 'https://new.example.com' ) ) );
check( 'siteurl requires confirm', is_wp_error( $bad ) );

$ok = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'siteurl' => 'https://new.example.com' ) ), true );
check( 'siteurl confirmed', ! is_wp_error( $ok ) && 'https://new.example.com' === $GLOBALS['options']['siteurl'] && ! empty( $ok['warnings'] ) );

$ok = PressAgent_WP_Settings_Manager::set( array( 'reading' => array( 'page_on_front' => 5, 'show_on_front' => 'page' ) ) );
check( 'front page accepts page', ! is_wp_error( $ok ) && 5 === $GLOBALS['options']['page_on_front'] && 'page' === $GLOBALS['options']['show_on_front'] );

$bad = PressAgent_WP_Settings_Manager::set( array( 'reading' => array( 'page_on_front' => 7 ) ) );
check( 'front page rejects post', is_wp_error( $bad ) );

$bad = PressAgent_WP_Settings_Manager::set( array( 'reading' => array( 'show_on_front' => 'weird' ) ) );
check( 'invalid show_on_front rejected', is_wp_error( $bad ) );

$ok = PressAgent_WP_Settings_Manager::set( array( 'general' => array( 'timezone_string' => 'Asia/Tehran' ) ) );
check( 'valid timezone', ! is_wp_error( $ok ) && 'Asia/Tehran' === $GLOBALS['options']['timezone_string'] );

$ok = PressAgent_WP_Settings_Manager::set( array( 'permalinks' => array( 'category_base' => 'topics' ) ) );
check( 'permalinks flush', ! is_wp_error( $ok ) && ! empty( $GLOBALS['flushed'] ) );

echo "\n" . ( ( $GLOBALS['tests'] ?? 0 ) - count( $GLOBALS['failures'] ?? array() ) ) . "/" . ( $GLOBALS['tests'] ?? 0 ) . " checks passed.\n";
exit( empty( $GLOBALS['failures'] ) ? 0 : 1 );
