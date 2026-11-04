<?php
/**
 * Plugin inventory and lifecycle management (install, activate, update).
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Plugin_Manager {

    const PROTECTED_SLUGS = array( 'pressagent', 'press-agent' );

    /**
     * List installed plugins with versions, state and available updates.
     *
     * @param bool $include_inactive Include inactive plugins (default true).
     * @return array
     */
    public static function list_plugins( $include_inactive = true ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $all     = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );
        $network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
        $updates = get_site_transient( 'update_plugins' );
        $items   = array();

        foreach ( $all as $file => $data ) {
            $is_active = in_array( $file, $active, true ) || isset( $network[ $file ] );
            if ( ! $include_inactive && ! $is_active ) {
                continue;
            }

            $new_version = null;
            if ( $updates && isset( $updates->response[ $file ]->new_version ) ) {
                $new_version = $updates->response[ $file ]->new_version;
            }

            $items[] = array(
                'file'        => $file,
                'slug'        => self::slug_from_file( $file ),
                'name'        => $data['Name'],
                'version'     => $data['Version'],
                'active'      => $is_active,
                'new_version' => $new_version,
                'requires_php'=> $data['RequiresPHP'] ?? null,
                'requires_wp' => $data['RequiresWP'] ?? null,
            );
        }

        usort(
            $items,
            static function ( $a, $b ) {
                if ( $a['active'] !== $b['active'] ) {
                    return $a['active'] ? -1 : 1;
                }
                return strcasecmp( $a['name'], $b['name'] );
            }
        );

        return array(
            'count'   => count( $items ),
            'plugins' => $items,
        );
    }

    /**
     * Install a free plugin from the official WordPress.org directory.
     *
     * @param string $slug     Plugin slug.
     * @param bool   $activate Activate after install.
     * @return array|WP_Error
     */
    public static function install_plugin( $slug, $activate = false ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to install plugins.', array( 'status' => 403 ) );
        }

        $slug = sanitize_title( $slug );
        if ( empty( $slug ) || ! preg_match( '/^[a-z0-9][a-z0-9\-]*$/', $slug ) ) {
            return new WP_Error( 'invalid_slug', 'Invalid plugin slug.', array( 'status' => 400 ) );
        }
        if ( in_array( $slug, self::PROTECTED_SLUGS, true ) ) {
            return new WP_Error( 'protected_plugin', 'This plugin is managed by PressAgent and cannot be installed or toggled here.', array( 'status' => 403 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $installed = get_plugins();
        foreach ( $installed as $file => $data ) {
            if ( self::slug_from_file( $file ) === $slug ) {
                return new WP_Error( 'already_installed', 'Plugin is already installed: ' . $file, array( 'status' => 409 ) );
            }
        }

        $api = plugins_api(
            'plugin_information',
            array(
                'slug'   => $slug,
                'fields' => array(
                    'sections'        => false,
                    'short_description' => true,
                    'banners'         => false,
                    'icons'           => false,
                    'reviews'         => false,
                    'ratings'         => false,
                    'downloaded'      => false,
                    'last_updated'    => true,
                ),
            )
        );
        if ( is_wp_error( $api ) ) {
            return new WP_Error( 'not_found', 'Plugin not found on WordPress.org: ' . $slug, array( 'status' => 404 ) );
        }

        $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
        $result   = $upgrader->install( $api->download_link );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( ! $result ) {
            return new WP_Error( 'install_failed', 'Plugin installation failed.', array( 'status' => 500 ) );
        }

        $plugin_file = $upgrader->plugin_info();
        $activated   = false;
        $activation_error = null;

        if ( $activate && $plugin_file ) {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                $activation_error = 'Installed but not activated: missing activate_plugins capability.';
            } else {
                $activation = activate_plugin( $plugin_file );
                if ( is_wp_error( $activation ) ) {
                    $activation_error = $activation->get_error_message();
                } else {
                    $activated = true;
                }
            }
        }

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache();
        }

        $installed_after = get_plugins();
        $data = $plugin_file && isset( $installed_after[ $plugin_file ] ) ? $installed_after[ $plugin_file ] : null;

        return array(
            'success'          => true,
            'slug'             => $slug,
            'plugin_file'      => $plugin_file,
            'version'          => $data['Version'] ?? ( $api->version ?? null ),
            'activated'        => $activated,
            'activation_error' => $activation_error,
            'message'          => 'Plugin installed' . ( $activated ? ' and activated.' : '.' ),
        );
    }

    /**
     * Activate or deactivate an installed plugin by slug or plugin file.
     *
     * @param string $identifier Plugin slug or relative file.
     * @param string $action     activate|deactivate.
     * @return array|WP_Error
     */
    public static function toggle_plugin( $identifier, $action = 'activate' ) {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to activate or deactivate plugins.', array( 'status' => 403 ) );
        }
        if ( ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
            return new WP_Error( 'invalid_action', 'Action must be "activate" or "deactivate".', array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $file = self::resolve_plugin_file( $identifier );
        if ( is_wp_error( $file ) ) {
            return $file;
        }

        $slug = self::slug_from_file( $file );
        if ( in_array( $slug, self::PROTECTED_SLUGS, true ) ) {
            return new WP_Error( 'protected_plugin', 'PressAgent cannot toggle itself through this endpoint.', array( 'status' => 403 ) );
        }

        if ( 'activate' === $action ) {
            $res = activate_plugin( $file );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            $state = 'activated';
        } else {
            deactivate_plugins( $file );
            $state = 'deactivated';
        }

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        return array(
            'success'     => true,
            'plugin_file' => $file,
            'slug'        => $slug,
            'state'       => $state,
            'message'     => 'Plugin ' . $state . ': ' . $file,
        );
    }

    /**
     * Update a single installed plugin from its WordPress.org package.
     *
     * @param string $identifier Plugin slug or relative file.
     * @return array|WP_Error
     */
    public static function update_plugin( $identifier ) {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to update plugins.', array( 'status' => 403 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $file = self::resolve_plugin_file( $identifier );
        if ( is_wp_error( $file ) ) {
            return $file;
        }

        $updates = get_site_transient( 'update_plugins' );
        if ( ! $updates || ! isset( $updates->response[ $file ] ) ) {
            return new WP_Error( 'no_update', 'No WordPress.org update is available for: ' . $file, array( 'status' => 409 ) );
        }

        if ( ! is_plugin_active( $file ) ) {
            // Updating inactive plugins is safe and supported.
        }

        $old_version = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false )['Version'] ?? null;
        $new_version = $updates->response[ $file ]->new_version ?? null;

        $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
        $result   = $upgrader->upgrade( $file );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( ! $result ) {
            return new WP_Error( 'update_failed', 'Plugin update failed.', array( 'status' => 500 ) );
        }

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        return array(
            'success'     => true,
            'plugin_file' => $file,
            'old_version' => $old_version,
            'new_version' => $new_version,
            'message'     => 'Plugin updated: ' . $file,
        );
    }

    /**
     * Resolve a plugin slug or file path to a valid installed plugin file.
     *
     * @param string $identifier Slug or relative file.
     * @return string|WP_Error
     */
    public static function resolve_plugin_file( $identifier ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $identifier = trim( (string) $identifier );
        $all        = get_plugins();

        if ( isset( $all[ $identifier ] ) ) {
            return $identifier;
        }

        $slug = sanitize_title( basename( $identifier, '.php' ) );
        $matches = array();
        foreach ( $all as $file => $data ) {
            if ( self::slug_from_file( $file ) === $slug || basename( $file, '.php' ) === $slug ) {
                $matches[] = $file;
            }
        }

        if ( count( $matches ) === 1 ) {
            return $matches[0];
        }
        if ( count( $matches ) > 1 ) {
            return new WP_Error( 'ambiguous_plugin', 'Multiple plugins match "' . $identifier . '": ' . implode( ', ', $matches ) . '. Pass the plugin file instead.', array( 'status' => 409 ) );
        }

        return new WP_Error( 'plugin_missing', 'Plugin not found: ' . $identifier, array( 'status' => 404 ) );
    }

    private static function slug_from_file( $file ) {
        $dir = dirname( $file );
        if ( '.' !== $dir ) {
            return $dir;
        }
        return basename( $file, '.php' );
    }
}
