<?php
/**
 * Performance manager smoke tests with stubs.
 * Run: php tests/php/performance-smoke.php
 */

define( 'ABSPATH', sys_get_temp_dir() . '/pressagent-performance-test/' );

class WP_Error {
    public $code;
    public $message;
    public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function current_user_can( $cap ) { return true; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function esc_url_raw( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_URL ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function wp_unslash( $value ) { return $value; }

require_once __DIR__ . '/../../plugin/includes/class-performance-manager.php';

function check( $label, $condition ) {
    $GLOBALS['tests'] = ( $GLOBALS['tests'] ?? 0 ) + 1;
    if ( $condition ) { echo "PASS  {$label}\n"; }
    else { echo "FAIL  {$label}\n"; $GLOBALS['failures'][] = $label; }
}

$ref = new ReflectionMethod( 'PressAgent_Performance_Manager', 'parse_bytes' );
$ref->setAccessible( true );
check( 'parse_bytes 256M', 268435456 === $ref->invoke( null, '256M' ) );
check( 'parse_bytes -1', -1 === $ref->invoke( null, '-1' ) );
check( 'parse_bytes 1G', 1073741824 === $ref->invoke( null, '1G' ) );

$same = new ReflectionMethod( 'PressAgent_Performance_Manager', 'same_origin_url' );
$same->setAccessible( true );
check( 'relative URL', 'https://example.com/shop/' === $same->invoke( null, '/shop/' ) );
check( 'same origin', 'https://example.com/shop/' === $same->invoke( null, 'https://example.com/shop/' ) );
check( 'external URL rejected', is_wp_error( $same->invoke( null, 'https://evil.example.net/' ) ) );
check( 'userinfo URL rejected', is_wp_error( $same->invoke( null, 'https://example.com@evil.example.net/' ) ) );

$unknown = PressAgent_Performance_Manager::fix( 'not_a_fix' );
check( 'unknown fix rejected', is_wp_error( $unknown ) );

$destructive = PressAgent_Performance_Manager::fix( 'delete_old_revisions', array(), false );
check( 'revision deletion needs confirm', is_wp_error( $destructive ) && 'confirmation_required' === $destructive->code );

$cleanup = PressAgent_Performance_Manager::fix( 'cleanup_database', array(), false );
check( 'database cleanup needs confirm', is_wp_error( $cleanup ) && 'confirmation_required' === $cleanup->code );

echo "\n" . ( ( $GLOBALS['tests'] ?? 0 ) - count( $GLOBALS['failures'] ?? array() ) ) . "/" . ( $GLOBALS['tests'] ?? 0 ) . " checks passed.\n";
exit( empty( $GLOBALS['failures'] ) ? 0 : 1 );
