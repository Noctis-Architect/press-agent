<?php
/**
 * Plugin manager search/resolve/install smoke tests with stubs.
 * Run: php tests/php/plugin-manager-smoke.php
 */

$abspath = sys_get_temp_dir() . '/pressagent-plugin-manager-test/';
@mkdir( $abspath . 'wp-admin/includes', 0777, true );
foreach ( array( 'plugin.php', 'plugin-install.php', 'file.php', 'misc.php', 'class-wp-upgrader.php' ) as $file ) {
    $path = $abspath . 'wp-admin/includes/' . $file;
    if ( ! file_exists( $path ) ) {
        file_put_contents( $path, "<?php\n" );
    }
}
define( 'ABSPATH', $abspath );

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_messages() { return array( $this->message ); }
    public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $cap ) { return true; }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function sanitize_title( $value ) {
    $value = strtolower( trim( (string) $value ) );
    $value = preg_replace( '/[^a-z0-9]+/', '-', $value );
    return trim( $value, '-' );
}
function get_plugins( $folder = '' ) {
    return $GLOBALS['plugins'];
}
function get_option( $key, $default = false ) {
    if ( 'active_plugins' === $key ) {
        return $GLOBALS['active_plugins'];
    }
    return $default;
}
function is_multisite() { return false; }
function get_site_option( $key, $default = false ) { return $default; }
function wp_clean_plugins_cache( $clear = true ) { $GLOBALS['cache_cleaned'] = true; }
function is_plugin_active( $file ) { return in_array( $file, $GLOBALS['active_plugins'], true ); }
function is_plugin_active_for_network( $file ) { return false; }
function activate_plugin( $file ) {
    $GLOBALS['active_plugins'][] = $file;
    $GLOBALS['activated'][]      = $file;
    return null;
}
function get_bloginfo( $show ) { return '6.8.2'; }

function repo_plugin( $slug, $name, $version = '1.0.0' ) {
    return (object) array(
        'slug'              => $slug,
        'name'              => $name,
        'version'           => $version,
        'short_description' => 'Test plugin',
        'author'            => 'Test Author',
        'active_installs'   => 1000,
        'rating'            => 90,
        'num_ratings'       => 10,
        'requires'          => '6.0',
        'tested'            => '6.8',
        'requires_php'      => '7.4',
        'last_updated'      => '2026-01-01',
        'download_link'     => 'https://downloads.wordpress.org/plugin/' . $slug . '.zip',
    );
}

function plugins_api( $action, $args = array() ) {
    if ( 'plugin_information' === $action ) {
        $known = array(
            'elementor'         => array( 'Elementor', '4.2.4' ),
            'seo-by-rank-math'  => array( 'Rank Math SEO – AI SEO Tools', '1.0.281' ),
            'wordpress-seo'     => array( 'Yoast SEO', '28.8' ),
            'woocommerce'       => array( 'WooCommerce', '11.2.1' ),
            'litespeed-cache'   => array( 'کش لایت‌اسپید', '7.9.3' ),
        );
        $slug = $args['slug'] ?? '';
        if ( isset( $known[ $slug ] ) ) {
            return repo_plugin( $slug, $known[ $slug ][0], $known[ $slug ][1] );
        }
        return new WP_Error( 'plugins_api_failed', 'Plugin not found.' );
    }

    if ( 'query_plugins' === $action ) {
        $search = strtolower( $args['search'] ?? '' );
        $found  = array();
        if ( 'rank math' === $search ) {
            $found[] = (array) repo_plugin( 'seo-by-rank-math', 'Rank Math SEO – AI SEO Tools' );
            $found[] = (array) repo_plugin( 'import-xml-csv-settings-to-rank-math-seo', 'WP All Import – Import SEO Settings for Rank Math SEO' );
            $found[] = (array) repo_plugin( 'do-shortcodes-for-rank-math-seo', 'Do Shortcodes for Rank Math SEO' );
        } elseif ( 'seo' === $search ) {
            $found[] = (array) repo_plugin( 'wordpress-seo', 'Yoast SEO' );
            $found[] = (array) repo_plugin( 'seo-by-rank-math', 'Rank Math SEO – AI SEO Tools' );
        } elseif ( 'elementor' === $search ) {
            $found[] = (array) repo_plugin( 'elementor', 'Elementor', '4.2.4' );
        } elseif ( 'litespeed' === $search ) {
            $found[] = (array) repo_plugin( 'litespeed-cache', 'کش لایت‌اسپید', '7.9.3' );
            $found[] = (array) repo_plugin( 'wp-stateless-litespeed-cache-addon', 'WP-Stateless – LiteSpeed Cache Addon' );
            $found[] = (array) repo_plugin( 'urls-most-wanted-for-litespeed-lscache', 'URLs Most Wanted for LiteSpeed LScache' );
        }
        return (object) array(
            'plugins' => $found,
            'info'    => (object) array( 'results' => count( $found ) ),
        );
    }

    return new WP_Error( 'unknown_action', 'Unknown API action' );
}

$GLOBALS['plugins'] = array(
    'elementor/elementor.php'          => array( 'Name' => 'Elementor', 'Version' => '4.2.4' ),
    'pressagent/pressagent.php'        => array( 'Name' => 'PressAgent', 'Version' => '1.2.0' ),
    'woocommerce/woocommerce.php'      => array( 'Name' => 'WooCommerce', 'Version' => '11.2.1' ),
);
$GLOBALS['active_plugins'] = array( 'woocommerce/woocommerce.php' );
$GLOBALS['activated']      = array();

require_once __DIR__ . '/../../plugin/includes/class-plugin-manager.php';

function check( $label, $condition ) {
    $GLOBALS['tests'] = ( $GLOBALS['tests'] ?? 0 ) + 1;
    if ( $condition ) { echo "PASS  {$label}\n"; }
    else { echo "FAIL  {$label}\n"; $GLOBALS['failures'][] = $label; }
}

$search = PressAgent_Plugin_Manager::search_plugins( 'elementor' );
check( 'repo search returns plugin', ! is_wp_error( $search ) && 1 === count( $search['plugins'] ) && 'elementor' === $search['plugins'][0]['slug'] );
check( 'repo search marks installed inactive plugin', ! empty( $search['plugins'][0]['installed'] ) && false === $search['plugins'][0]['active'] );

$empty = PressAgent_Plugin_Manager::search_plugins( '' );
check( 'empty search rejected', is_wp_error( $empty ) && 'invalid_query' === $empty->get_error_code() );

$resolved = PressAgent_Plugin_Manager::resolve_repo_plugin( 'rank math' );
check( 'name resolves to canonical slug', ! is_wp_error( $resolved ) && 'seo-by-rank-math' === $resolved['slug'] && 'search' === $resolved['match'] );

$resolved = PressAgent_Plugin_Manager::resolve_repo_plugin( 'https://wordpress.org/plugins/elementor/' );
check( 'wordpress.org URL resolves', ! is_wp_error( $resolved ) && 'elementor' === $resolved['slug'] );

$resolved = PressAgent_Plugin_Manager::resolve_repo_plugin( 'elementor/elementor.php' );
check( 'plugin file path resolves', ! is_wp_error( $resolved ) && 'elementor' === $resolved['slug'] );

$resolved = PressAgent_Plugin_Manager::resolve_repo_plugin( 'litespeed' );
check( 'localized name resolves by slug prefix', ! is_wp_error( $resolved ) && 'litespeed-cache' === $resolved['slug'] && 'search' === $resolved['match'] );

$ambiguous = PressAgent_Plugin_Manager::resolve_repo_plugin( 'seo' );
check( 'ambiguous search returns candidates', is_wp_error( $ambiguous ) && 'ambiguous_plugin' === $ambiguous->get_error_code() && 2 === count( $ambiguous->get_error_data()['candidates'] ) );

$installed = PressAgent_Plugin_Manager::install_plugin( 'elementor', false );
check( 'installed plugin reports already_installed', ! is_wp_error( $installed ) && ! empty( $installed['already_installed'] ) && false === $installed['activated'] );

$installed = PressAgent_Plugin_Manager::install_plugin( 'elementor', true );
check( 'installed plugin activates on request', ! is_wp_error( $installed ) && true === $installed['activated'] && in_array( 'elementor/elementor.php', $GLOBALS['activated'], true ) );

$installed = PressAgent_Plugin_Manager::install_plugin( 'WooCommerce', false );
check( 'installed plugin found by exact name', ! is_wp_error( $installed ) && ! empty( $installed['already_installed'] ) && 'woocommerce' === $installed['slug'] );

$installed = PressAgent_Plugin_Manager::install_plugin( 'https://wordpress.org/plugins/woocommerce/', true );
check( 'URL for active plugin stays active', ! is_wp_error( $installed ) && ! empty( $installed['already_installed'] ) && true === $installed['activated'] );

$protected = PressAgent_Plugin_Manager::install_plugin( 'pressagent', true );
check( 'protected plugin rejected', is_wp_error( $protected ) && 'protected_plugin' === $protected->get_error_code() );

$missing = PressAgent_Plugin_Manager::resolve_repo_plugin( 'definitely-not-a-real-plugin-xyz' );
check( 'unknown plugin returns not_found', is_wp_error( $missing ) && 'not_found' === $missing->get_error_code() );

echo "\n" . ( ( $GLOBALS['tests'] ?? 0 ) - count( $GLOBALS['failures'] ?? array() ) ) . "/" . ( $GLOBALS['tests'] ?? 0 ) . " checks passed.\n";
exit( empty( $GLOBALS['failures'] ) ? 0 : 1 );
