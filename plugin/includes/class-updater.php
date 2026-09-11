<?php
/**
 * PressAgent GitHub Auto-Updater
 *
 * Handles checking GitHub repository for releases/updates,
 * integrating with WordPress plugin update transients,
 * and performing direct in-place updates from GitHub.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Updater {

    /**
     * GitHub repository in format :owner/:repo
     */
    const GITHUB_REPO = 'Noctis-Architect/press-agent';

    /**
     * Default branch to track
     */
    const GITHUB_BRANCH = 'main';

    /**
     * Transient key for caching update status
     */
    const TRANSIENT_KEY = 'pressagent_github_update_info';

    /**
     * Cache expiration in seconds (12 hours)
     */
    const CACHE_TTL = 43200;

    /**
     * Initialize updater hooks
     */
    public static function init() {
        // Integrate with WordPress native update checks
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_plugins_transient' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 20, 3 );

        // Schedule periodic update checks if not already scheduled
        if ( ! wp_next_scheduled( 'pressagent_check_updates_event' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'pressagent_check_updates_event' );
        }
        add_action( 'pressagent_check_updates_event', array( __CLASS__, 'check_update_event' ) );
    }

    /**
     * Scheduled event callback
     */
    public static function check_update_event() {
        self::check_update( true );
    }

    /**
     * Check for updates on GitHub
     *
     * @param bool $force Force refresh cache
     * @return array
     */
    public static function check_update( $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::TRANSIENT_KEY );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $current_version = defined( 'PRESSAGENT_VERSION' ) ? PRESSAGENT_VERSION : '1.0.0';
        $remote_version  = $current_version;
        $download_url    = sprintf( 'https://github.com/%s/archive/refs/heads/%s.zip', self::GITHUB_REPO, self::GITHUB_BRANCH );
        $commit_sha      = '';
        $commit_msg      = '';
        $commit_date     = '';
        $release_notes   = '';

        // 1. Fetch remote plugin file directly (bypasses GitHub API rate limits)
        $raw_url = sprintf( 'https://raw.githubusercontent.com/%s/%s/plugin/pressagent.php', self::GITHUB_REPO, self::GITHUB_BRANCH );
        $raw_response = wp_remote_get( $raw_url, array(
            'timeout'    => 10,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; PressAgent/' . $current_version . '; ' . home_url(),
            'headers'    => array(
                'Cache-Control' => 'no-cache',
                'Pragma'        => 'no-cache',
            ),
        ) );

        if ( ! is_wp_error( $raw_response ) && wp_remote_retrieve_response_code( $raw_response ) === 200 ) {
            $raw_body = wp_remote_retrieve_body( $raw_response );
            if ( preg_match( "/define\(\s*['\"]PRESSAGENT_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $raw_body, $matches ) ) {
                $remote_version = trim( $matches[1] );
            } elseif ( preg_match( "/Version:\s*([0-9.]+)/i", $raw_body, $matches ) ) {
                $remote_version = trim( $matches[1] );
            }
        }

        // 2. Fetch latest commit metadata for change context
        $commits_url = sprintf( 'https://api.github.com/repos/%s/commits/%s', self::GITHUB_REPO, self::GITHUB_BRANCH );
        $commit_response = wp_remote_get( $commits_url, array(
            'timeout'    => 8,
            'user-agent' => 'PressAgent-Updater',
            'headers'    => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        ) );

        if ( ! is_wp_error( $commit_response ) && wp_remote_retrieve_response_code( $commit_response ) === 200 ) {
            $commit_data = json_decode( wp_remote_retrieve_body( $commit_response ), true );
            if ( ! empty( $commit_data['sha'] ) ) {
                $commit_sha  = substr( $commit_data['sha'], 0, 7 );
                $commit_msg  = ! empty( $commit_data['commit']['message'] ) ? $commit_data['commit']['message'] : '';
                $commit_date = ! empty( $commit_data['commit']['author']['date'] ) ? $commit_data['commit']['author']['date'] : '';
            }
        }

        // 3. Check for formal GitHub Releases if available
        $release_url = sprintf( 'https://api.github.com/repos/%s/releases/latest', self::GITHUB_REPO );
        $release_response = wp_remote_get( $release_url, array(
            'timeout'    => 8,
            'user-agent' => 'PressAgent-Updater',
            'headers'    => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        ) );

        if ( ! is_wp_error( $release_response ) && wp_remote_retrieve_response_code( $release_response ) === 200 ) {
            $release_data = json_decode( wp_remote_retrieve_body( $release_response ), true );
            if ( ! empty( $release_data['tag_name'] ) ) {
                $tag_version = ltrim( $release_data['tag_name'], 'vV' );
                if ( version_compare( $tag_version, $remote_version, '>' ) ) {
                    $remote_version = $tag_version;
                }
                if ( ! empty( $release_data['zipball_url'] ) ) {
                    $download_url = $release_data['zipball_url'];
                }
                if ( ! empty( $release_data['body'] ) ) {
                    $release_notes = $release_data['body'];
                }
            }
        }

        $has_update = version_compare( $remote_version, $current_version, '>' );

        $info = array(
            'has_update'       => $has_update,
            'current_version'  => $current_version,
            'remote_version'   => $remote_version,
            'download_url'     => $download_url,
            'commit_sha'       => $commit_sha,
            'commit_message'   => $commit_msg,
            'commit_date'      => $commit_date,
            'release_notes'    => $release_notes,
            'last_checked'     => current_time( 'mysql' ),
            'last_checked_ts'  => time(),
            'repo'             => self::GITHUB_REPO,
            'branch'           => self::GITHUB_BRANCH,
        );

        set_transient( self::TRANSIENT_KEY, $info, self::CACHE_TTL );

        return $info;
    }

    /**
     * Download and apply update from GitHub
     *
     * @return array|WP_Error
     */
    public static function perform_update() {
        if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'unauthorized', 'Insufficient permissions to update plugins.' );
        }

        // 1. Create a safety snapshot with Action Guard before modifying files
        if ( class_exists( 'PressAgent_Action_Guard' ) ) {
            PressAgent_Action_Guard::create_snapshot(
                'plugin_update',
                array(
                    'action'       => 'pre_github_update',
                    'prev_version' => defined( 'PRESSAGENT_VERSION' ) ? PRESSAGENT_VERSION : '1.0.0',
                    'repo'         => self::GITHUB_REPO,
                )
            );
        }

        // 2. Initialize WordPress Filesystem
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        WP_Filesystem();
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            return new WP_Error( 'fs_init_failed', 'Could not initialize WP_Filesystem.' );
        }

        $update_info = self::check_update( true );
        $download_url = ! empty( $update_info['download_url'] ) ? $update_info['download_url'] : sprintf( 'https://github.com/%s/archive/refs/heads/%s.zip', self::GITHUB_REPO, self::GITHUB_BRANCH );

        // 3. Download the GitHub archive
        $temp_zip = download_url( $download_url, 300 );
        if ( is_wp_error( $temp_zip ) ) {
            return $temp_zip;
        }

        // 4. Create temporary extraction directory
        $temp_extract_dir = get_temp_dir() . 'pressagent-update-' . wp_generate_password( 8, false ) . '/';
        $wp_filesystem->mkdir( $temp_extract_dir );

        $unzip_res = unzip_file( $temp_zip, $temp_extract_dir );
        @unlink( $temp_zip );

        if ( is_wp_error( $unzip_res ) ) {
            $wp_filesystem->delete( $temp_extract_dir, true );
            return $unzip_res;
        }

        // 5. Locate the plugin source directory containing pressagent.php
        $source_plugin_dir = self::locate_plugin_directory( $temp_extract_dir );
        if ( ! $source_plugin_dir ) {
            $wp_filesystem->delete( $temp_extract_dir, true );
            return new WP_Error( 'invalid_archive', 'Could not locate pressagent.php in the downloaded GitHub package.' );
        }

        // 6. Destination plugin directory
        $target_dir = self::trailingslash( PRESSAGENT_PLUGIN_DIR );

        // 7. Copy new files into the plugin directory
        $copy_res = copy_dir( $source_plugin_dir, $target_dir );
        $wp_filesystem->delete( $temp_extract_dir, true );

        if ( is_wp_error( $copy_res ) ) {
            return $copy_res;
        }

        // 8. Purge OPcache and object caches to load newly installed files immediately
        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        // 9. Reset transients
        delete_transient( self::TRANSIENT_KEY );
        delete_site_transient( 'update_plugins' );

        return array(
            'success'      => true,
            'message'      => 'PressAgent was successfully updated from GitHub.',
            'new_version'  => $update_info['remote_version'],
            'commit_sha'   => $update_info['commit_sha'],
        );
    }

    /**
     * Helper to safely append trailing slash
     *
     * @param string $path
     * @return string
     */
    private static function trailingslash( $path ) {
        return function_exists( 'trailingslashit' ) ? trailingslashit( $path ) : rtrim( $path, '/\\' ) . '/';
    }

    /**
     * Recursively locate the folder containing pressagent.php
     *
     * @param string $dir Root search directory
     * @return string|false
     */
    private static function locate_plugin_directory( $dir ) {
        if ( file_exists( self::trailingslash( $dir ) . 'pressagent.php' ) ) {
            return self::trailingslash( $dir );
        }

        // Check common GitHub subdirectories: {repo}-main/plugin/ or {repo}-main/
        $items = glob( self::trailingslash( $dir ) . '*', GLOB_MARK );
        if ( empty( $items ) ) {
            return false;
        }

        // First pass: check direct subfolder/plugin
        foreach ( $items as $item ) {
            if ( is_dir( $item ) ) {
                if ( file_exists( $item . 'plugin/pressagent.php' ) ) {
                    return self::trailingslash( $item . 'plugin' );
                }
                if ( file_exists( $item . 'pressagent.php' ) ) {
                    return self::trailingslash( $item );
                }
            }
        }

        // Second pass: recursive search up to 3 levels deep
        foreach ( $items as $item ) {
            if ( is_dir( $item ) ) {
                $sub = self::locate_plugin_directory( $item );
                if ( $sub ) {
                    return $sub;
                }
            }
        }

        return false;
    }

    /**
     * Hook into WordPress plugin update transient
     *
     * @param object $transient
     * @return object
     */
    public static function filter_update_plugins_transient( $transient ) {
        if ( empty( $transient ) || ! is_object( $transient ) ) {
            return $transient;
        }

        $info = self::check_update( false );
        if ( empty( $info['has_update'] ) ) {
            return $transient;
        }

        $plugin_file = plugin_basename( PRESSAGENT_PLUGIN_DIR . 'pressagent.php' );

        $package = ! empty( $info['download_url'] ) ? $info['download_url'] : '';

        $transient->response[ $plugin_file ] = (object) array(
            'id'            => 'pressagent',
            'slug'          => 'pressagent',
            'plugin'        => $plugin_file,
            'new_version'   => $info['remote_version'],
            'url'           => 'https://github.com/' . self::GITHUB_REPO,
            'package'       => $package,
            'icons'         => array(),
            'banners'       => array(),
            'banners_rtl'   => array(),
            'tested'        => get_bloginfo( 'version' ),
            'requires_php'  => '7.4',
        );

        return $transient;
    }

    /**
     * Hook into plugins_api for view version details modal
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object
     */
    public static function filter_plugins_api( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || 'pressagent' !== $args->slug ) {
            return $result;
        }

        $info = self::check_update( false );

        $plugin_info = new stdClass();
        $plugin_info->name          = 'PressAgent';
        $plugin_info->slug          = 'pressagent';
        $plugin_info->version       = $info['remote_version'];
        $plugin_info->author        = '<a href="https://github.com/' . self::GITHUB_REPO . '">PressAgent Team</a>';
        $plugin_info->homepage      = 'https://github.com/' . self::GITHUB_REPO;
        $plugin_info->requires      = '5.8';
        $plugin_info->tested        = get_bloginfo( 'version' );
        $plugin_info->requires_php  = '7.4';
        $plugin_info->last_updated  = $info['last_checked'];
        $plugin_info->download_link = $info['download_url'];
        $plugin_info->sections      = array(
            'description' => 'The Autonomous AI Ops Bridge for WordPress. Seamlessly connect AI coding agents (Antigravity CLI, Claude Code, Cursor, Windsurf) to manage pages, Elementor layouts, and site diagnostics.',
            'changelog'   => ! empty( $info['commit_message'] ) ? nl2br( esc_html( $info['commit_message'] ) ) : 'Latest updates from GitHub main branch.',
        );

        return $plugin_info;
    }
}
