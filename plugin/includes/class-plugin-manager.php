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

    const PROTECTED_SLUGS   = array( 'pressagent', 'press-agent' );
    const MAX_SEARCH_RESULTS = 20;

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
                'file'         => $file,
                'slug'         => self::slug_from_file( $file ),
                'name'         => $data['Name'],
                'version'      => $data['Version'],
                'active'       => $is_active,
                'new_version'  => $new_version,
                'requires_php' => $data['RequiresPHP'] ?? null,
                'requires_wp'  => $data['RequiresWP'] ?? null,
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
     * Search the official WordPress.org directory, equivalent to Plugins → Add New.
     *
     * @param string $query Search term (plugin name, author or tag).
     * @param int    $page  Result page.
     * @param int    $limit Results per page.
     * @return array|WP_Error
     */
    public static function search_plugins( $query, $page = 1, $limit = 10 ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to browse the WordPress.org plugin directory.', array( 'status' => 403 ) );
        }

        $query = self::clean_text( $query );
        if ( '' === $query ) {
            return new WP_Error( 'invalid_query', 'A search query is required.', array( 'status' => 400 ) );
        }

        $page  = max( 1, (int) $page );
        $limit = max( 1, min( self::MAX_SEARCH_RESULTS, (int) $limit ) );
        $api   = self::query_repo( $query, $page, $limit );
        if ( is_wp_error( $api ) ) {
            return $api;
        }

        $installed = self::installed_index();
        $items     = array();

        foreach ( (array) ( $api->plugins ?? array() ) as $plugin ) {
            // plugins_api() decodes nested plugin items as arrays, not objects.
            $plugin = (object) $plugin;
            $slug   = sanitize_title( $plugin->slug ?? '' );
            if ( '' === $slug || ! empty( $plugin->external ) ) {
                continue;
            }

            $current = $installed[ $slug ] ?? null;

            $items[] = array(
                'slug'              => $slug,
                'name'              => self::clean_text( $plugin->name ?? $slug ),
                'version'           => (string) ( $plugin->version ?? '' ),
                'short_description' => self::clean_text( $plugin->short_description ?? '', true ),
                'author'            => self::clean_text( $plugin->author ?? '' ),
                'active_installs'   => isset( $plugin->active_installs ) ? (int) $plugin->active_installs : null,
                'rating'            => isset( $plugin->rating ) ? (int) $plugin->rating : null,
                'num_ratings'       => isset( $plugin->num_ratings ) ? (int) $plugin->num_ratings : null,
                'requires'          => (string) ( $plugin->requires ?? '' ),
                'tested'            => (string) ( $plugin->tested ?? '' ),
                'requires_php'      => (string) ( $plugin->requires_php ?? '' ),
                'last_updated'      => (string) ( $plugin->last_updated ?? '' ),
                'installed'         => null !== $current,
                'active'            => ! empty( $current['active'] ),
                'installed_version' => $current['version'] ?? null,
            );
        }

        return array(
            'query'   => $query,
            'page'    => $page,
            'limit'   => $limit,
            'total'   => isset( $api->info->results ) ? (int) $api->info->results : count( $items ),
            'plugins' => $items,
        );    }

    /**
     * Install a free plugin from the official WordPress.org directory.
     *
     * Accepts a slug, a WordPress.org plugin URL, or a search term such as
     * "Rank Math". When a search term matches more than one plugin, the
     * response lists candidates instead of guessing.
     *
     * @param string $identifier Plugin slug, URL or search term.
     * @param bool   $activate   Activate after install.
     * @return array|WP_Error
     */
    public static function install_plugin( $identifier, $activate = false ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to install plugins.', array( 'status' => 403 ) );
        }

        $activate   = (bool) $activate;
        $identifier = trim( wp_strip_all_tags( (string) $identifier ) );
        if ( '' === $identifier ) {
            return new WP_Error( 'invalid_slug', 'A plugin slug, WordPress.org URL or search term is required.', array( 'status' => 400 ) );
        }

        // PressAgent must never overwrite or toggle itself through this path.
        $slug_guess = self::normalize_slug( $identifier );
        if ( $slug_guess && in_array( $slug_guess, self::PROTECTED_SLUGS, true ) ) {
            return new WP_Error( 'protected_plugin', 'This plugin is managed by PressAgent and cannot be installed or toggled here.', array( 'status' => 403 ) );
        }

        // Mirror the dashboard: an installed plugin is reported as such instead of failing.
        $installed = $slug_guess ? self::find_installed( $slug_guess ) : null;
        if ( ! $installed ) {
            $installed = self::find_installed_by_name( $identifier );
        }
        if ( $installed ) {
            return self::already_installed_response( $installed, $activate );
        }

        $resolved = self::resolve_repo_plugin( $identifier );
        if ( is_wp_error( $resolved ) ) {
            return $resolved;
        }

        $slug = $resolved['slug'];
        $api  = $resolved['api'];

        if ( in_array( $slug, self::PROTECTED_SLUGS, true ) ) {
            return new WP_Error( 'protected_plugin', 'This plugin is managed by PressAgent and cannot be installed or toggled here.', array( 'status' => 403 ) );
        }

        $installed = self::find_installed( $slug );
        if ( $installed ) {
            return self::already_installed_response( $installed, $activate );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $warnings = self::compatibility_warnings( $api );
        $skin     = self::make_upgrader_skin();
        $upgrader = new Plugin_Upgrader( $skin );

        $install_output = '';
        ob_start();
        try {
            $result = $upgrader->install( $api->download_link );
        } finally {
            $install_output = trim( (string) ob_get_clean() );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( ! $result ) {
            $messages = $skin->get_messages();
            if ( '' !== $install_output ) {
                $messages[] = $install_output;
            }
            return new WP_Error(
                'install_failed',
                'Plugin installation failed.' . ( $messages ? ' ' . implode( ' ', $messages ) : '' ),
                array(
                    'status'   => 500,
                    'messages' => $messages,
                )
            );
        }

        $plugin_file = $upgrader->plugin_info();
        if ( ! $plugin_file ) {
            return new WP_Error( 'install_failed', 'Plugin was downloaded but no plugin file could be detected.', array( 'status' => 500 ) );
        }

        $activated         = false;
        $activation_error  = null;
        $activation_output = '';

        if ( $activate ) {
            $activation = self::activate_plugin_file( $plugin_file );
            if ( is_wp_error( $activation ) ) {
                $activation_error = $activation->get_error_message();
            } else {
                $activated         = true;
                $activation_output = (string) ( $activation['output'] ?? '' );
            }
        }

        self::flush_caches();

        $installed_after = get_plugins();
        $data            = $installed_after[ $plugin_file ] ?? array();

        return array(
            'success'           => true,
            'slug'              => $slug,
            'name'              => self::clean_text( $api->name ?? $slug ),
            'plugin_file'       => $plugin_file,
            'version'           => $data['Version'] ?? ( $api->version ?? null ),
            'activated'         => $activated,
            'activation_error'  => $activation_error,
            'activation_output' => $activation_output,
            'warnings'          => $warnings,
            'matched_by'        => $resolved['match'],
            'source'            => 'wordpress.org',
            'message'           => $activated
                ? 'Plugin installed and activated.'
                : ( $activation_error ? 'Plugin installed but activation failed: ' . $activation_error : 'Plugin installed.' ),
        );
    }

    /**
     * Resolve a slug, WordPress.org URL or search term to a repository plugin.
     *
     * @param string $identifier Slug, URL or search term.
     * @return array|WP_Error {
     *     @type string $slug    Canonical WordPress.org slug.
     *     @type object $api     plugins_api() plugin_information response.
     *     @type string $match   slug|search.
     * }
     */
    public static function resolve_repo_plugin( $identifier ) {
        $identifier = trim( wp_strip_all_tags( (string) $identifier ) );
        if ( '' === $identifier ) {
            return new WP_Error( 'invalid_slug', 'A plugin slug, WordPress.org URL or search term is required.', array( 'status' => 400 ) );
        }

        if ( preg_match( '#wordpress\.org/plugins/([a-z0-9\-]+)#i', $identifier, $matches ) ) {
            $identifier = $matches[1];
        } elseif ( preg_match( '#^([a-z0-9\-]+)/[a-z0-9\-]+\.php$#i', $identifier, $matches ) ) {
            $identifier = $matches[1];
        }

        $is_slug   = (bool) preg_match( '/^[a-z0-9][a-z0-9\-]*$/i', $identifier );
        $candidate = self::normalize_slug( $identifier );

        if ( '' === $candidate && $is_slug ) {
            return new WP_Error( 'invalid_slug', 'Invalid plugin slug.', array( 'status' => 400 ) );
        }

        $slug_error = null;
        if ( $is_slug ) {
            $api = self::repo_information( $candidate );
            if ( ! is_wp_error( $api ) ) {
                return array(
                    'slug'  => $candidate,
                    'api'   => $api,
                    'match' => 'slug',
                );
            }
            $slug_error = $api;
        }

        $search = self::clean_text( preg_replace( '/[\-_]+/', ' ', $identifier ) );
        $api    = self::query_repo( $search, 1, 10 );
        if ( is_wp_error( $api ) ) {
            return $slug_error ? $slug_error : $api;
        }

        $results = array();
        foreach ( (array) ( $api->plugins ?? array() ) as $plugin ) {
            $plugin = (object) $plugin;
            if ( ! empty( $plugin->slug ) && empty( $plugin->external ) ) {
                $results[] = $plugin;
            }
        }

        if ( empty( $results ) ) {
            return new WP_Error(
                'not_found',
                'No plugin on WordPress.org matches "' . $identifier . '".',
                array(
                    'status' => 404,
                    'query'  => $search,
                )
            );
        }

        $chosen = self::pick_search_match( $identifier, $candidate, $results );
        if ( is_wp_error( $chosen ) ) {
            return $chosen;
        }

        $slug = sanitize_title( $chosen->slug );
        $api  = self::repo_information( $slug );
        if ( is_wp_error( $api ) ) {
            return $api;
        }

        return array(
            'slug'  => $slug,
            'api'   => $api,
            'match' => 'search',
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

        $old_version = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false )['Version'] ?? null;
        $new_version = $updates->response[ $file ]->new_version ?? null;

        $skin     = self::make_upgrader_skin();
        $upgrader = new Plugin_Upgrader( $skin );

        $update_output = '';
        ob_start();
        try {
            $result = $upgrader->upgrade( $file );
        } finally {
            $update_output = trim( (string) ob_get_clean() );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( ! $result ) {
            $messages = $skin->get_messages();
            if ( '' !== $update_output ) {
                $messages[] = $update_output;
            }
            return new WP_Error(
                'update_failed',
                'Plugin update failed.' . ( $messages ? ' ' . implode( ' ', $messages ) : '' ),
                array(
                    'status'   => 500,
                    'messages' => $messages,
                )
            );
        }

        self::flush_caches();

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

        $slug    = sanitize_title( basename( $identifier, '.php' ) );
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

    private static function query_repo( $search, $page, $limit ) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $api = plugins_api(
            'query_plugins',
            array(
                'search'   => $search,
                'page'     => $page,
                'per_page' => $limit,
                'fields'   => array(
                    'short_description' => true,
                    'icons'             => false,
                    'banners'           => false,
                    'reviews'           => false,
                    'ratings'           => true,
                    'downloaded'        => false,
                    'last_updated'      => true,
                    'compatibility'     => true,
                ),
            )
        );

        if ( is_wp_error( $api ) ) {
            return new WP_Error( 'repo_error', 'WordPress.org search failed: ' . $api->get_error_message(), array( 'status' => 502 ) );
        }

        return $api;
    }

    private static function repo_information( $slug ) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $api = plugins_api(
            'plugin_information',
            array(
                'slug'   => $slug,
                'fields' => array(
                    'sections'          => false,
                    'short_description' => true,
                    'banners'           => false,
                    'icons'             => false,
                    'reviews'           => false,
                    'ratings'           => false,
                    'downloaded'        => false,
                    'last_updated'      => true,
                    'compatibility'     => true,
                ),
            )
        );

        if ( is_wp_error( $api ) ) {
            return $api;
        }
        if ( empty( $api->download_link ) ) {
            return new WP_Error(
                'not_available',
                'WordPress.org does not provide a download for "' . $slug . '". It may be closed, premium-only or discontinued.',
                array( 'status' => 404 )
            );
        }

        return $api;
    }

    private static function pick_search_match( $identifier, $candidate, $results ) {
        $normalized = strtolower( self::clean_text( $identifier ) );
        $results    = array_map(
            static function ( $plugin ) {
                return (object) $plugin;
            },
            $results
        );
        $exact      = array();

        foreach ( $results as $plugin ) {
            $name = strtolower( self::clean_text( $plugin->name ?? '' ) );
            $slug = strtolower( (string) ( $plugin->slug ?? '' ) );
            if ( $slug === $candidate || $name === $normalized ) {
                $exact[] = $plugin;
            }
        }

        if ( 1 === count( $exact ) ) {
            return $exact[0];
        }

        // WordPress.org orders by relevance; prefer a unique name that starts with the query.
        // Short generic terms (e.g. "seo") stay ambiguous instead of installing the wrong plugin.
        if ( strlen( $normalized ) >= 5 || count( preg_split( '/[\s\-_]+/', $normalized ) ) >= 2 ) {
            $prefix_matches = array();
            foreach ( $results as $plugin ) {
                $name = strtolower( self::clean_text( $plugin->name ?? '' ) );
                if ( '' !== $name && 0 === strpos( $name, $normalized ) ) {
                    $prefix_matches[] = $plugin;
                }
            }
            if ( 1 === count( $prefix_matches ) ) {
                return $prefix_matches[0];
            }

            // Localized plugin names (e.g. Persian) do not start with the English query,
            // so also accept a unique slug that extends the query: "litespeed" → "litespeed-cache".
            if ( '' !== $candidate ) {
                $slug_matches = array();
                foreach ( $results as $plugin ) {
                    $slug = strtolower( (string) ( $plugin->slug ?? '' ) );
                    if ( $slug === $candidate || 0 === strpos( $slug, $candidate . '-' ) ) {
                        $slug_matches[] = $plugin;
                    }
                }
                if ( 1 === count( $slug_matches ) ) {
                    return $slug_matches[0];
                }
            }
        }

        $tokens = array_values( array_filter( preg_split( '/[\s\-_]+/', $normalized ) ) );
        if ( ! empty( $tokens ) ) {
            $token_matches = array();
            foreach ( $results as $plugin ) {
                $haystack = strtolower( (string) ( $plugin->slug ?? '' ) . ' ' . self::clean_text( $plugin->name ?? '' ) );
                $all      = true;
                foreach ( $tokens as $token ) {
                    if ( false === strpos( $haystack, $token ) ) {
                        $all = false;
                        break;
                    }
                }
                if ( $all ) {
                    $token_matches[] = $plugin;
                }
            }

            if ( 1 === count( $token_matches ) ) {
                return $token_matches[0];
            }
            if ( count( $token_matches ) > 1 ) {
                $results = $token_matches;
            }
        }

        if ( 1 === count( $results ) ) {
            return $results[0];
        }

        $candidates = self::candidate_list( array_slice( $results, 0, 6 ) );
        $labels     = array();
        foreach ( $candidates as $candidate_item ) {
            $labels[] = $candidate_item['slug'] . ' (' . $candidate_item['name'] . ')';
        }

        return new WP_Error(
            'ambiguous_plugin',
            'Multiple WordPress.org plugins match "' . $identifier . '". Choose one slug: ' . implode( ', ', $labels ),
            array(
                'status'     => 409,
                'candidates' => $candidates,
            )
        );
    }

    private static function candidate_list( $plugins ) {
        $items = array();
        foreach ( $plugins as $plugin ) {
            $plugin  = (object) $plugin;
            $items[] = array(
                'slug'            => sanitize_title( $plugin->slug ?? '' ),
                'name'            => self::clean_text( $plugin->name ?? '' ),
                'version'         => (string) ( $plugin->version ?? '' ),
                'active_installs' => isset( $plugin->active_installs ) ? (int) $plugin->active_installs : null,
            );
        }
        return $items;
    }

    private static function already_installed_response( $installed, $activate ) {
        $activated         = ! empty( $installed['active'] );
        $activation_error  = null;
        $activation_output = '';

        if ( $activate && ! $activated ) {
            $activation = self::activate_plugin_file( $installed['file'] );
            if ( is_wp_error( $activation ) ) {
                $activation_error = $activation->get_error_message();
            } else {
                $activated         = true;
                $activation_output = (string) ( $activation['output'] ?? '' );
            }
        }

        self::flush_caches();

        return array(
            'success'           => true,
            'already_installed' => true,
            'slug'              => $installed['slug'],
            'plugin_file'       => $installed['file'],
            'version'           => $installed['version'],
            'activated'         => $activated,
            'activation_error'  => $activation_error,
            'activation_output' => $activation_output,
            'source'            => 'installed',
            'message'           => 'Plugin is already installed' . ( $activated ? ' and active.' : ( $activate ? '.' : ' but inactive.' ) ),
        );
    }

    private static function activate_plugin_file( $plugin_file ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        if ( ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'forbidden', 'Installed but not activated: missing activate_plugins capability.', array( 'status' => 403 ) );
        }

        if ( is_plugin_active( $plugin_file ) || is_plugin_active_for_network( $plugin_file ) ) {
            return array( 'output' => '' );
        }

        $output = '';
        ob_start();
        try {
            $result = activate_plugin( $plugin_file );
        } finally {
            $output = trim( (string) ob_get_clean() );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array( 'output' => $output );
    }

    private static function compatibility_warnings( $api ) {
        $warnings = array();
        $php      = defined( 'PHP_VERSION' ) ? PHP_VERSION : phpversion();

        if ( ! empty( $api->requires_php ) && version_compare( $php, $api->requires_php, '<' ) ) {
            $warnings[] = 'This plugin requires PHP ' . $api->requires_php . ' but this site runs PHP ' . $php . '.';
        }

        if ( ! empty( $api->requires ) ) {
            global $wp_version;
            $wp = ! empty( $wp_version ) ? $wp_version : get_bloginfo( 'version' );
            if ( $wp && version_compare( $wp, $api->requires, '<' ) ) {
                $warnings[] = 'This plugin requires WordPress ' . $api->requires . ' but this site runs WordPress ' . $wp . '.';
            }
        }

        return $warnings;
    }

    private static function find_installed( $slug ) {
        $installed = self::installed_index();
        return $installed[ $slug ] ?? null;
    }

    private static function find_installed_by_name( $identifier ) {
        $needle = strtolower( self::clean_text( $identifier ) );
        if ( '' === $needle ) {
            return null;
        }

        foreach ( self::installed_index() as $installed ) {
            if ( strtolower( self::clean_text( $installed['name'] ) ) === $needle ) {
                return $installed;
            }
        }

        return null;
    }

    private static function installed_index() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $all     = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );
        $network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
        $index   = array();

        foreach ( $all as $file => $data ) {
            $slug           = self::slug_from_file( $file );
            $index[ $slug ] = array(
                'slug'    => $slug,
                'file'    => $file,
                'name'    => $data['Name'] ?? $slug,
                'version' => $data['Version'] ?? '',
                'active'  => in_array( $file, $active, true ) || isset( $network[ $file ] ),
            );
        }

        return $index;
    }

    private static function flush_caches() {
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache();
        }
        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }
    }

    private static function make_upgrader_skin() {
        return new class() extends WP_Upgrader_Skin {
            private $messages = array();

            public function header() {}
            public function footer() {}

            public function feedback( $string, ...$args ) {
                if ( isset( $this->upgrader->strings[ $string ] ) ) {
                    $string = $this->upgrader->strings[ $string ];
                }
                if ( ! empty( $args ) ) {
                    $string = vsprintf( $string, $args );
                }
                $string = trim( wp_strip_all_tags( (string) $string ) );
                if ( '' !== $string ) {
                    $this->messages[] = $string;
                }
            }

            public function error( $errors ) {
                if ( is_wp_error( $errors ) ) {
                    foreach ( $errors->get_error_messages() as $message ) {
                        $this->feedback( $message );
                    }
                    return;
                }
                $this->feedback( (string) $errors );
            }

            public function get_messages() {
                return $this->messages;
            }
        };
    }

    private static function normalize_slug( $identifier ) {
        $identifier = strtolower( trim( (string) $identifier ) );
        $identifier = preg_replace( '/[^a-z0-9]+/', '-', $identifier );
        return trim( preg_replace( '/-+/', '-', $identifier ), '-' );
    }

    private static function clean_text( $value, $short = false ) {
        $text = wp_strip_all_tags( (string) $value );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );

        if ( $short && strlen( $text ) > 220 ) {
            $text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 217 ) : substr( $text, 0, 217 );
            $text .= '...';
        }

        return $text;
    }

    private static function slug_from_file( $file ) {
        $dir = dirname( $file );
        if ( '.' !== $dir ) {
            return $dir;
        }
        return basename( $file, '.php' );
    }
}
