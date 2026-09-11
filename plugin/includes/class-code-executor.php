<?php
/**
 * Class PressAgent_Code_Executor
 *
 * Developer & God Mode Engine for PressAgent.
 * Allows AI agents and developers to scaffold, install, toggle, and delete
 * custom WordPress plugins and execute diagnostic snippets on staging environments.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Code_Executor {

    /**
     * Check if God Mode / Code Execution is enabled.
     *
     * Enabled by default when the constant PRESSAGENT_GOD_MODE is true,
     * or when the database option 'pressagent_god_mode_enabled' is active (defaults to 'yes').
     *
     * @return bool
     */
    public static function is_enabled() {
        if ( defined( 'PRESSAGENT_GOD_MODE' ) ) {
            return (bool) PRESSAGENT_GOD_MODE;
        }

        $opt = get_option( 'pressagent_god_mode_enabled', 'yes' );
        return ( 'yes' === $opt || true === $opt || '1' === $opt || 1 === $opt );
    }

    /**
     * Validate PHP code syntax without executing it.
     *
     * @param string $code Raw PHP code.
     * @return true|WP_Error
     */
    public static function validate_php_syntax( $code ) {
        // Prepare temporary file for syntax check
        $temp_file = tempnam( sys_get_temp_dir(), 'pa_syntax_' );
        if ( false === $temp_file ) {
            return true; // Fallback if tempnam is restricted
        }

        file_put_contents( $temp_file, $code );

        $output = array();
        $ret_code = 0;
        exec( 'php -l ' . escapeshellarg( $temp_file ) . ' 2>&1', $output, $ret_code );
        @unlink( $temp_file );

        if ( 0 !== $ret_code ) {
            $err_str = implode( "\n", $output );
            return new WP_Error( 'syntax_error', 'PHP Syntax Validation Failed: ' . $err_str, array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Scaffold and create a new custom WordPress plugin.
     *
     * @param string $name Plugin display name or slug.
     * @param string $code Full PHP source code of the plugin.
     * @param string $description Optional description.
     * @param bool   $activate Whether to automatically activate after creation.
     * @return array|WP_Error
     */
    public static function create_plugin( $name, $code, $description = '', $activate = true ) {
        if ( ! self::is_enabled() ) {
            return new WP_Error(
                'god_mode_disabled',
                'God Mode is currently disabled in PressAgent settings.',
                array( 'status' => 403 )
            );
        }

        if ( empty( $name ) || empty( $code ) ) {
            return new WP_Error( 'missing_params', 'Plugin name and code are required.', array( 'status' => 400 ) );
        }

        $slug = sanitize_title_with_dashes( $name );
        if ( empty( $slug ) ) {
            $slug = 'custom-plugin-' . time();
        }

        // Format code and ensure required WordPress plugin header exists
        $trimmed = trim( $code );
        if ( false === stripos( $trimmed, 'Plugin Name:' ) ) {
            $header  = "<?php\n";
            $header .= "/**\n";
            $header .= " * Plugin Name: " . esc_html( $name ) . "\n";
            $header .= " * Description: " . esc_html( ! empty( $description ) ? $description : 'Autonomous plugin created by PressAgent AI Ops' ) . "\n";
            $header .= " * Version: 1.0.0\n";
            $header .= " * Author: PressAgent AI\n";
            $header .= " */\n\n";
            $header .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";

            if ( 0 === strpos( $trimmed, '<?php' ) ) {
                $trimmed = substr( $trimmed, 5 );
            }
            $code = $header . ltrim( $trimmed );
        }

        // Pre-validate PHP syntax before writing to disk
        $validation = self::validate_php_syntax( $code );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Action Guard Snapshot before creating
        if ( class_exists( 'PressAgent_Action_Guard' ) ) {
            PressAgent_Action_Guard::create_snapshot(
                'create_plugin',
                array(
                    'plugin_slug' => $slug,
                    'plugin_name' => $name,
                    'size'        => strlen( $code ),
                )
            );
        }

        $plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug;
        if ( ! file_exists( $plugin_dir ) ) {
            wp_mkdir_p( $plugin_dir );
        }

        $main_file = trailingslashit( $plugin_dir ) . $slug . '.php';
        $written   = file_put_contents( $main_file, $code );

        if ( false === $written ) {
            return new WP_Error( 'file_write_error', 'Failed to write plugin file to disk. Check file permissions.', array( 'status' => 500 ) );
        }

        $rel_path = $slug . '/' . $slug . '.php';
        $is_activated = false;
        $activation_error = null;

        if ( $activate ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $act_res = activate_plugin( $rel_path );
            if ( is_wp_error( $act_res ) ) {
                $activation_error = $act_res->get_error_message();
            } else {
                $is_activated = true;
            }
        }

        // Reset OPcache if available
        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        return array(
            'success'          => true,
            'plugin_slug'      => $slug,
            'plugin_name'      => $name,
            'plugin_file'      => $rel_path,
            'bytes_written'    => $written,
            'activated'        => $is_activated,
            'activation_error' => $activation_error,
            'message'          => 'Plugin ' . $name . ' created successfully' . ( $is_activated ? ' and activated.' : '.' ),
        );
    }

    /**
     * Delete an existing custom plugin.
     *
     * @param string $slug Plugin directory slug.
     * @return array|WP_Error
     */
    public static function delete_plugin( $slug ) {
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'god_mode_disabled', 'God Mode is disabled.', array( 'status' => 403 ) );
        }

        $slug = sanitize_title_with_dashes( $slug );
        $protected = array( 'pressagent', 'press-agent', 'elementor', 'elementor-pro', 'woocommerce' );
        if ( in_array( $slug, $protected, true ) ) {
            return new WP_Error( 'protected_plugin', 'Cannot delete core or system protected plugin: ' . $slug, array( 'status' => 403 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $rel_path = $slug . '/' . $slug . '.php';
        if ( is_plugin_active( $rel_path ) ) {
            deactivate_plugins( $rel_path );
        }

        $plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $slug;
        if ( ! is_dir( $plugin_dir ) ) {
            return new WP_Error( 'not_found', 'Plugin directory not found: ' . $slug, array( 'status' => 404 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            $wp_filesystem->delete( $plugin_dir, true );
        } else {
            self::recursive_rmdir( $plugin_dir );
        }

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        return array(
            'success'     => true,
            'plugin_slug' => $slug,
            'message'     => 'Plugin ' . $slug . ' deleted successfully.',
        );
    }

    /**
     * Toggle plugin state (activate or deactivate).
     *
     * @param string $slug Plugin slug.
     * @param string $action 'activate' or 'deactivate'.
     * @return array|WP_Error
     */
    public static function toggle_plugin( $slug, $action = 'activate' ) {
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'god_mode_disabled', 'God Mode is disabled.', array( 'status' => 403 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $slug = sanitize_title_with_dashes( $slug );
        $rel_path = $slug . '/' . $slug . '.php';

        if ( 'activate' === $action ) {
            $res = activate_plugin( $rel_path );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            return array( 'success' => true, 'status' => 'activated', 'plugin' => $slug );
        } else {
            deactivate_plugins( $rel_path );
            return array( 'success' => true, 'status' => 'deactivated', 'plugin' => $slug );
        }
    }

    /**
     * Execute arbitrary PHP code snippet safely and capture output.
     *
     * @param string $code PHP code to execute.
     * @return array|WP_Error
     */
    public static function execute_snippet( $code ) {
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'god_mode_disabled', 'God Mode is disabled.', array( 'status' => 403 ) );
        }

        $validation = self::validate_php_syntax( '<?php ' . $code );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        try {
            ob_start();
            $return_val = eval( '?>' . $code );
            $output = ob_get_clean();

            return array(
                'success' => true,
                'output'  => $output,
                'return'  => $return_val,
            );
        } catch ( Throwable $t ) {
            if ( ob_get_level() > 0 ) {
                ob_end_clean();
            }
            return new WP_Error(
                'fatal_execution_error',
                $t->getMessage() . ' in ' . $t->getFile() . ':' . $t->getLine(),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Fallback recursive directory removal.
     */
    private static function recursive_rmdir( $dir ) {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( '.' !== $object && '..' !== $object ) {
                    $item = $dir . DIRECTORY_SEPARATOR . $object;
                    if ( is_dir( $item ) && ! is_link( $item ) ) {
                        self::recursive_rmdir( $item );
                    } else {
                        @unlink( $item );
                    }
                }
            }
            @rmdir( $dir );
        }
    }
}
