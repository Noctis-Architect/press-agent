<?php
/**
 * Storage adapters and maintenance actions for registry-supported plugins.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Supported_Plugin_Actions {

    /* ---------------------------------------------------------------------
     * LiteSpeed Cache
     * ------------------------------------------------------------------- */

    public static function litespeed_get_setting( $id ) {
        if ( ! class_exists( '\LiteSpeed\Conf' ) ) {
            return null;
        }
        $conf = \LiteSpeed\Conf::cls();
        return method_exists( $conf, 'conf' ) ? $conf->conf( $id ) : null;
    }

    public static function litespeed_save_setting( $id, $value ) {
        if ( ! class_exists( '\LiteSpeed\API' ) ) {
            return false;
        }
        $api = \LiteSpeed\API::cls();
        if ( ! method_exists( $api, 'save_conf' ) ) {
            return false;
        }
        $api->save_conf( array( $id => $value ) );
        return true;
    }

    public static function litespeed_purge_all() {
        if ( ! defined( 'LSCWP_V' ) ) {
            return new WP_Error( 'litespeed_missing', 'LiteSpeed Cache is not active.', array( 'status' => 409 ) );
        }
        do_action( 'litespeed_purge_all' );
        return array( 'purged' => 'all' );
    }

    public static function litespeed_purge_url( $params ) {
        if ( ! defined( 'LSCWP_V' ) ) {
            return new WP_Error( 'litespeed_missing', 'LiteSpeed Cache is not active.', array( 'status' => 409 ) );
        }
        $url = esc_url_raw( $params['url'] ?? '' );
        if ( empty( $url ) ) {
            return new WP_Error( 'missing_url', 'A URL is required.', array( 'status' => 400 ) );
        }
        do_action( 'litespeed_purge_url', $url );
        return array( 'purged' => $url );
    }

    public static function litespeed_purge_post( $params ) {
        if ( ! defined( 'LSCWP_V' ) ) {
            return new WP_Error( 'litespeed_missing', 'LiteSpeed Cache is not active.', array( 'status' => 409 ) );
        }
        $post_id = absint( $params['post_id'] ?? 0 );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_post_id', 'post_id is required.', array( 'status' => 400 ) );
        }
        do_action( 'litespeed_purge_post', $post_id );
        return array( 'purged_post' => $post_id );
    }

    public static function litespeed_purge_posttype( $params ) {
        if ( ! defined( 'LSCWP_V' ) ) {
            return new WP_Error( 'litespeed_missing', 'LiteSpeed Cache is not active.', array( 'status' => 409 ) );
        }
        $post_type = sanitize_key( $params['post_type'] ?? '' );
        if ( empty( $post_type ) ) {
            return new WP_Error( 'missing_post_type', 'post_type is required.', array( 'status' => 400 ) );
        }
        do_action( 'litespeed_purge_posttype', $post_type );
        return array( 'purged_post_type' => $post_type );
    }

    /* ---------------------------------------------------------------------
     * Elementor
     * ------------------------------------------------------------------- */

    public static function elementor_clear_cache() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_missing', 'Elementor is not active.', array( 'status' => 409 ) );
        }
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        return array( 'cleared' => 'elementor_cache' );
    }

    public static function elementor_clear_document_cache( $params ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return new WP_Error( 'elementor_missing', 'Elementor is not active.', array( 'status' => 409 ) );
        }
        $post_id = absint( $params['post_id'] ?? 0 );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_post_id', 'post_id is required.', array( 'status' => 400 ) );
        }
        delete_post_meta( $post_id, '_elementor_css' );
        delete_post_meta( $post_id, '_elementor_element_cache' );
        delete_post_meta( $post_id, '_elementor_page_assets' );
        if ( isset( \Elementor\Plugin::$instance->element_cache ) && method_exists( \Elementor\Plugin::$instance->element_cache, 'clear_cache' ) ) {
            \Elementor\Plugin::$instance->element_cache->clear_cache( $post_id );
        }
        return array( 'cleared_post' => $post_id );
    }

    /* ---------------------------------------------------------------------
     * Redirection
     * ------------------------------------------------------------------- */

    public static function redirection_get_options() {
        return function_exists( 'red_get_options' ) ? red_get_options() : get_option( 'redirection_options', array() );
    }

    public static function redirection_save_options( $value ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( function_exists( 'red_set_options' ) ) {
            red_set_options( is_array( $value ) ? $value : array() );
            return true;
        }
        return (bool) update_option( 'redirection_options', $value );
    }

    public static function redirection_list( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $limit = min( 500, max( 1, (int) ( $params['limit'] ?? 100 ) ) );
        $items = \Red_Item::get_all();
        $out   = array();
        foreach ( array_slice( $items, 0, $limit ) as $item ) {
            $out[] = $item->to_json();
        }
        return array( 'total' => count( $items ), 'returned' => count( $out ), 'items' => $out );
    }

    public static function redirection_create( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $url = $params['url'] ?? '';
        if ( empty( $url ) ) {
            return new WP_Error( 'missing_url', 'Source URL is required.', array( 'status' => 400 ) );
        }
        $target = $params['target'] ?? ( $params['action_data']['url'] ?? '' );
        $details = array(
            'url'         => $url,
            'action_type' => $params['action_type'] ?? 'url',
            'action_code' => (int) ( $params['action_code'] ?? 301 ),
            'action_data' => array( 'url' => esc_url_raw( $target ) ),
            'title'       => sanitize_text_field( $params['title'] ?? '' ),
            'match_type'  => $params['match_type'] ?? 'url',
            'group_id'    => absint( $params['group_id'] ?? 0 ),
            'regex'       => absint( $params['regex'] ?? 0 ),
        );
        if ( empty( $details['group_id'] ) ) {
            $groups = \Red_Group::get_all();
            if ( empty( $groups ) ) {
                return new WP_Error( 'group_missing', 'Create a redirect group first.', array( 'status' => 409 ) );
            }
            $details['group_id'] = (int) $groups[0]['id'];
        }
        $item = \Red_Item::create( $details );
        if ( is_wp_error( $item ) ) {
            return $item;
        }
        if ( ! $item ) {
            return new WP_Error( 'create_failed', 'Redirection rejected the redirect (check group, match type and action).', array( 'status' => 400 ) );
        }
        return array( 'redirect' => $item->to_json() );
    }

    public static function redirection_update( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $item = \Red_Item::get_by_id( absint( $params['id'] ?? 0 ) );
        if ( ! $item ) {
            return new WP_Error( 'redirect_missing', 'Redirect not found.', array( 'status' => 404 ) );
        }
        $details = array();
        foreach ( array( 'url', 'action_type', 'action_code', 'group_id', 'title', 'match_type' ) as $key ) {
            if ( array_key_exists( $key, $params ) ) {
                $details[ $key ] = $params[ $key ];
            }
        }
        if ( isset( $params['target'] ) ) {
            $details['action_data'] = array( 'url' => esc_url_raw( $params['target'] ) );
        }
        if ( empty( $details ) ) {
            return new WP_Error( 'missing_update', 'Provide at least one redirect field to update.', array( 'status' => 400 ) );
        }
        $updated = $item->update( $details );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }
        if ( ! $updated ) {
            return new WP_Error( 'update_failed', 'Redirection rejected the update.', array( 'status' => 400 ) );
        }
        $fresh = \Red_Item::get_by_id( $item->get_id() );
        return array( 'redirect' => $fresh ? $fresh->to_json() : null );
    }

    public static function redirection_delete( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $item = \Red_Item::get_by_id( absint( $params['id'] ?? 0 ) );
        if ( ! $item ) {
            return new WP_Error( 'redirect_missing', 'Redirect not found.', array( 'status' => 404 ) );
        }
        $item->delete();
        return array( 'deleted' => $item->get_id() );
    }

    public static function redirection_set_enabled( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $item = \Red_Item::get_by_id( absint( $params['id'] ?? 0 ) );
        if ( ! $item ) {
            return new WP_Error( 'redirect_missing', 'Redirect not found.', array( 'status' => 404 ) );
        }
        $enabled = rest_sanitize_boolean( $params['enabled'] ?? true );
        $enabled ? $item->enable() : $item->disable();
        $fresh = \Red_Item::get_by_id( $item->get_id() );
        return array( 'redirect' => $fresh ? $fresh->to_json() : null );
    }

    public static function redirection_lookup_404s( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $filter = array();
        if ( ! empty( $params['url'] ) ) {
            $filter['url-exact'] = sanitize_text_field( $params['url'] );
        }
        if ( ! empty( $params['filterBy'] ) && is_array( $params['filterBy'] ) ) {
            $filter = array_merge( $filter, $params['filterBy'] );
        }
        $query = array(
            'filterBy' => $filter,
            'per_page' => min( 200, max( 5, (int) ( $params['per_page'] ?? 50 ) ) ),
            'page'     => max( 0, (int) ( $params['page'] ?? 0 ) ),
        );
        $result = \Red_404_Log::get_filtered( $query );
        return array( 'total' => $result['total'], 'items' => $result['items'] );
    }

    public static function redirection_list_groups() {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        return array( 'groups' => \Red_Group::get_all() );
    }

    public static function redirection_create_group( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $name      = sanitize_text_field( $params['name'] ?? '' );
        $module_id = absint( $params['module_id'] ?? 1 );
        if ( '' === $name ) {
            return new WP_Error( 'missing_name', 'Group name is required.', array( 'status' => 400 ) );
        }
        $group = \Red_Group::create( $name, $module_id, rest_sanitize_boolean( $params['enabled'] ?? true ) );
        if ( ! $group ) {
            return new WP_Error( 'create_failed', 'Redirection rejected the group (check module id).', array( 'status' => 400 ) );
        }
        return array( 'group' => $group->to_json() );
    }

    public static function redirection_update_group( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $group = \Red_Group::get( absint( $params['id'] ?? 0 ) );
        if ( ! $group ) {
            return new WP_Error( 'group_missing', 'Redirect group not found.', array( 'status' => 404 ) );
        }
        $group->update( array(
            'name'     => $params['name'] ?? $group->get_name(),
            'moduleId' => $params['module_id'] ?? $group->get_module_id(),
        ) );
        $fresh = \Red_Group::get( $group->get_id(), true );
        return array( 'group' => $fresh ? $fresh->to_json() : null );
    }

    public static function redirection_delete_group( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $group = \Red_Group::get( absint( $params['id'] ?? 0 ) );
        if ( ! $group ) {
            return new WP_Error( 'group_missing', 'Redirect group not found.', array( 'status' => 404 ) );
        }
        $id = $group->get_id();
        $group->delete();
        return array( 'deleted_group' => $id, 'message' => 'Group and its redirects were deleted.' );
    }

    public static function redirection_lookup_logs( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        if ( ! class_exists( 'Red_Redirect_Log' ) ) {
            return new WP_Error( 'log_unavailable', 'Redirection log classes are unavailable.', array( 'status' => 409 ) );
        }
        $filter = array();
        if ( ! empty( $params['url'] ) ) {
            $filter['url'] = sanitize_text_field( $params['url'] );
        }
        $query = array(
            'filterBy' => $filter,
            'per_page' => min( 200, max( 5, (int) ( $params['per_page'] ?? 50 ) ) ),
            'page'     => max( 0, (int) ( $params['page'] ?? 0 ) ),
        );
        $result = \Red_Redirect_Log::get_filtered( $query );
        return array( 'total' => $result['total'], 'items' => $result['items'] );
    }

    public static function redirection_flush_cache( $params ) {
        $guard = self::redirection_guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $group_id = absint( $params['group_id'] ?? 0 );
        if ( $group_id ) {
            \Red_Module::flush( $group_id );
        }
        return array( 'flushed_group' => $group_id );
    }

    /* ---------------------------------------------------------------------
     * WP Mail SMTP
     * ------------------------------------------------------------------- */

    public static function wpmailsmtp_get_options() {
        if ( ! class_exists( '\WPMailSMTP\Options' ) ) {
            return array();
        }
        $options = \WPMailSMTP\Options::init()->get_all();
        $pass_set = ! empty( $options['smtp']['pass'] );
        if ( isset( $options['smtp'] ) ) {
            // Never let a read carry the secret back into a later write.
            unset( $options['smtp']['pass'] );
            $options['smtp']['pass_set'] = $pass_set;
        }
        return $options;
    }

    public static function wpmailsmtp_save_options( $value ) {
        if ( ! class_exists( '\WPMailSMTP\Options' ) || ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( isset( $value['smtp'] ) && is_array( $value['smtp'] ) ) {
            unset( $value['smtp']['pass_set'] );
            if ( array_key_exists( 'pass', $value['smtp'] ) && '' === trim( (string) $value['smtp']['pass'] ) ) {
                unset( $value['smtp']['pass'] );
            }
        }
        \WPMailSMTP\Options::init()->set( is_array( $value ) ? $value : array(), false, false );
        return true;
    }

    public static function wpmailsmtp_send_test( $params ) {
        if ( ! function_exists( 'wp_mail_smtp' ) ) {
            return new WP_Error( 'smtp_missing', 'WP Mail SMTP is not active.', array( 'status' => 409 ) );
        }
        $recipient = sanitize_email( $params['recipient'] ?? '' );
        if ( ! is_email( $recipient ) ) {
            return new WP_Error( 'invalid_email', 'A valid recipient email is required.', array( 'status' => 400 ) );
        }
        if ( ! class_exists( '\WPMailSMTP\TestEmail\TestEmail' ) ) {
            return new WP_Error( 'api_missing', 'WP Mail SMTP test-email API is unavailable.', array( 'status' => 500 ) );
        }
        $test   = new \WPMailSMTP\TestEmail\TestEmail();
        $result = $test->send( $recipient );
        $labels = array(
            \WPMailSMTP\TestEmail\TestEmail::SUCCESS => 'success',
            \WPMailSMTP\TestEmail\TestEmail::FAILED  => 'failed',
            \WPMailSMTP\TestEmail\TestEmail::FAILED_DOMAIN_CHECK => 'failed_domain_check',
        );
        return array(
            'recipient' => $recipient,
            'result'    => $labels[ $result ] ?? 'invalid_recipient',
        );
    }

    public static function wpmailsmtp_debug_events( $params ) {
        if ( ! class_exists( '\WPMailSMTP\Admin\DebugEvents\EventsCollection' ) ) {
            return new WP_Error( 'smtp_missing', 'WP Mail SMTP debug events are unavailable.', array( 'status' => 409 ) );
        }
        $collection_params = array(
            'per_page' => min( 100, max( 1, (int) ( $params['per_page'] ?? 20 ) ) ),
            'offset'   => max( 0, (int) ( $params['offset'] ?? 0 ) ),
        );
        if ( isset( $params['type'] ) && '' !== $params['type'] ) {
            $collection_params['type'] = (int) $params['type'];
        }
        $collection = new \WPMailSMTP\Admin\DebugEvents\EventsCollection( $collection_params );
        $events = array();
        foreach ( $collection->get() as $event ) {
            $events[] = array(
                'id'      => $event->get_id(),
                'type'    => $event->get_type_name(),
                'title'   => $event->get_title(),
                'content' => wp_strip_all_tags( $event->get_content() ),
                'created' => $event->get_created_at_formatted(),
            );
        }
        return array( 'total' => $collection->get_count(), 'events' => $events );
    }

    /* ---------------------------------------------------------------------
     * Wordfence
     * ------------------------------------------------------------------- */

    public static function wordfence_scan_status() {
        if ( ! defined( 'WORDFENCE_VERSION' ) ) {
            return new WP_Error( 'wordfence_missing', 'Wordfence is not active.', array( 'status' => 409 ) );
        }
        $running = false;
        if ( class_exists( 'wfScanEngine' ) ) {
            $running = (bool) wfScanEngine::isScanRunning( false );
        }
        $issues = 0;
        if ( class_exists( 'wfIssues' ) ) {
            $wf_issues = new wfIssues();
            $issues    = (int) $wf_issues->getIssueCount();
        }
        return array(
            'running'     => $running,
            'issue_count' => $issues,
        );
    }

    public static function wordfence_start_scan() {
        if ( ! defined( 'WORDFENCE_VERSION' ) || ! class_exists( 'wfScanEngine' ) ) {
            return new WP_Error( 'wordfence_missing', 'Wordfence is not active.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to start a scan.', array( 'status' => 403 ) );
        }
        wfScanEngine::startScan();
        return array( 'started' => true, 'message' => 'Scan start requested. Check scan_status for progress.' );
    }

    public static function wordfence_issues( $params ) {
        if ( ! defined( 'WORDFENCE_VERSION' ) || ! class_exists( 'wfIssues' ) ) {
            return new WP_Error( 'wordfence_missing', 'Wordfence is not active.', array( 'status' => 409 ) );
        }
        $limit = min( 50, max( 1, (int) ( $params['limit'] ?? 20 ) ) );
        $wf_issues = new wfIssues();
        $issues    = $wf_issues->getIssues( 0, $limit );
        $out       = array();
        foreach ( (array) ( $issues['new'] ?? array() ) as $issue ) {
            $out[] = array(
                'id'       => (int) ( $issue['id'] ?? 0 ),
                'severity' => (int) ( $issue['severity'] ?? 0 ),
                'type'     => $issue['type'] ?? '',
                'status'   => $issue['status'] ?? '',
                'short'    => wp_strip_all_tags( $issue['shortMsg'] ?? '' ),
                'time'     => (int) ( $issue['time'] ?? 0 ),
            );
        }
        return array( 'total' => (int) $wf_issues->getIssueCount(), 'issues' => $out );
    }

    /* ---------------------------------------------------------------------
     * Really Simple Security
     * ------------------------------------------------------------------- */

    public static function rsssl_get_options() {
        if ( is_multisite() && function_exists( 'rsssl_is_networkwide_active' ) && rsssl_is_networkwide_active() ) {
            return get_site_option( 'rsssl_options', array() );
        }
        return get_option( 'rsssl_options', array() );
    }

    public static function rsssl_save_options( $value ) {
        if ( ! function_exists( 'rsssl_update_option' ) || ! function_exists( 'rsssl_get_option' ) || ! current_user_can( 'manage_security' ) ) {
            return false;
        }
        foreach ( (array) $value as $key => $new_value ) {
            $current = rsssl_get_option( $key );
            if ( $current !== $new_value ) {
                rsssl_update_option( $key, $new_value );
            }
        }
        return true;
    }

    public static function rsssl_rebuild_rules() {
        if ( ! defined( 'rsssl_file' ) && ! class_exists( 'REALLY_SIMPLE_SSL' ) ) {
            return new WP_Error( 'rsssl_missing', 'Really Simple Security is not active.', array( 'status' => 409 ) );
        }
        do_action( 'rsssl_update_rules' );
        return array( 'rebuild' => 'requested' );
    }

    public static function rsssl_send_test_mail() {
        if ( ! class_exists( 'rsssl_mailer' ) ) {
            return new WP_Error( 'rsssl_missing', 'Really Simple Security mailer is unavailable.', array( 'status' => 409 ) );
        }
        $mailer = new rsssl_mailer();
        if ( ! method_exists( $mailer, 'send_test_mail' ) ) {
            return new WP_Error( 'api_missing', 'Test mail API is unavailable.', array( 'status' => 500 ) );
        }
        $result = $mailer->send_test_mail();
        return array(
            'sent'   => ! empty( $result['success'] ),
            'result' => $result,
        );
    }

    /* ---------------------------------------------------------------------
     * AIOS
     * ------------------------------------------------------------------- */

    public static function aios_get_config() {
        $configs = get_option( 'aio_wp_security_configs', array() );
        return is_array( $configs ) ? $configs : array();
    }

    public static function aios_save_config( $value ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        update_option( 'aio_wp_security_configs', is_array( $value ) ? $value : array() );
        global $aio_wp_security;
        if ( is_object( $aio_wp_security ) && isset( $aio_wp_security->configs ) ) {
            $aio_wp_security->configs->load_config();
        }
        if ( class_exists( 'AIOWPSecurity_Utility' ) && AIOWPSecurity_Utility::allow_to_write_to_htaccess() && class_exists( 'AIOWPSecurity_Utility_Htaccess' ) ) {
            AIOWPSecurity_Utility_Htaccess::write_to_htaccess( false );
        }
        return true;
    }

    public static function aios_list_audit_events( $params ) {
        if ( ! defined( 'AIO_WP_SECURITY_VERSION' ) ) {
            return new WP_Error( 'aios_missing', 'AIOS is not active.', array( 'status' => 409 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'aiowps_audit_log';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return new WP_Error( 'audit_unavailable', 'AIOS audit log table does not exist.', array( 'status' => 409 ) );
        }
        $limit  = min( 200, max( 1, (int) ( $params['limit'] ?? 50 ) ) );
        $offset = max( 0, (int) ( $params['offset'] ?? 0 ) );
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT username, ip, level, event_type, details, created FROM {$table} ORDER BY created DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        return array( 'total' => $total, 'items' => is_array( $rows ) ? $rows : array() );
    }

    public static function aios_rebuild_rules() {
        if ( ! defined( 'AIO_WP_SECURITY_VERSION' ) ) {
            return new WP_Error( 'aios_missing', 'AIOS is not active.', array( 'status' => 409 ) );
        }
        if ( class_exists( 'AIOWPSecurity_Utility' ) && AIOWPSecurity_Utility::allow_to_write_to_htaccess() && class_exists( 'AIOWPSecurity_Utility_Htaccess' ) ) {
            AIOWPSecurity_Utility_Htaccess::write_to_htaccess();
            return array( 'rebuild' => 'completed' );
        }
        return new WP_Error( 'htaccess_unwritable', '.htaccess is not writable on this site.', array( 'status' => 409 ) );
    }

    /* ---------------------------------------------------------------------
     * Rank Math
     * ------------------------------------------------------------------- */

    public static function rankmath_get_settings( $type ) {
        $option = get_option( 'rank-math-options-' . $type, array() );
        return is_array( $option ) ? $option : array();
    }

    public static function rankmath_save_settings( $type, $value ) {
        if ( ! class_exists( '\RankMath\Admin\Option_Center' ) || ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        $current = class_exists( '\RankMath\Helper' ) ? \RankMath\Helper::get_settings( $type ) : self::rankmath_get_settings( $type );
        $current = is_array( $current ) ? $current : array();

        $changed     = array();
        $field_types = array();
        foreach ( (array) $value as $field => $new_value ) {
            if ( ! array_key_exists( $field, $current ) || $current[ $field ] !== $new_value ) {
                $changed[ $field ]     = $new_value;
                $field_types[ $field ] = self::rankmath_field_type( $field, $new_value );
            }
        }
        if ( empty( $changed ) ) {
            return true;
        }

        \RankMath\Admin\Option_Center::save_settings( $type, $changed, $field_types, array_keys( $changed ), false );
        return true;
    }

    private static function rankmath_field_type( $field, $value ) {
        if ( false !== strpos( $field, 'image' ) ) {
            return 'file';
        }
        if ( is_bool( $value ) ) {
            return 'toggle';
        }
        if ( is_int( $value ) ) {
            return 'number';
        }
        if ( is_array( $value ) ) {
            return 'checkboxlist';
        }
        return 'text';
    }

    public static function rankmath_list_modules() {
        if ( ! defined( 'RANK_MATH_VERSION' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active.', array( 'status' => 409 ) );
        }
        $available = array();
        if ( function_exists( 'rank_math' ) && isset( rank_math()->manager ) && is_object( rank_math()->manager ) && ! empty( rank_math()->manager->modules ) ) {
            $available = array_keys( (array) rank_math()->manager->modules );
        }
        return array(
            'active'    => array_values( (array) get_option( 'rank_math_modules', array() ) ),
            'available' => array_values( $available ),
        );
    }

    public static function rankmath_set_module( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! class_exists( '\RankMath\Helper' ) || ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active or permission is denied.', array( 'status' => 409 ) );
        }
        $module  = sanitize_key( $params['module'] ?? '' );
        $enabled = rest_sanitize_boolean( $params['enabled'] ?? true );
        if ( empty( $module ) ) {
            return new WP_Error( 'missing_module', 'Module id is required.', array( 'status' => 400 ) );
        }
        $active = array_values( (array) get_option( 'rank_math_modules', array() ) );
        $known  = $active;
        if ( function_exists( 'rank_math' ) && isset( rank_math()->manager ) && is_object( rank_math()->manager ) && ! empty( rank_math()->manager->modules ) ) {
            $known = array_keys( (array) rank_math()->manager->modules );
        }
        if ( ! empty( $known ) && ! in_array( $module, $known, true ) ) {
            return new WP_Error( 'unknown_module', 'Unknown Rank Math module: ' . $module, array( 'status' => 400 ) );
        }
        \RankMath\Helper::update_modules( array( $module => $enabled ? 'on' : 'off' ) );
        return self::rankmath_list_modules();
    }

    public static function rankmath_list_redirections( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! class_exists( '\RankMath\Redirections\DB' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active.', array( 'status' => 409 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_redirections';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return new WP_Error( 'module_inactive', 'Rank Math redirection tables do not exist. Activate the redirections module first.', array( 'status' => 409 ) );
        }
        $result = \RankMath\Redirections\DB::get_redirections( array(
            'limit'  => min( 200, max( 1, (int) ( $params['limit'] ?? 50 ) ) ),
            'paged'  => max( 1, (int) ( $params['paged'] ?? 1 ) ),
            'status' => 'any',
            'search' => sanitize_text_field( $params['search'] ?? '' ),
        ) );
        $rows = $result['redirections'] ?? array();
        foreach ( $rows as &$row ) {
            if ( isset( $row['sources'] ) ) {
                $row['sources'] = maybe_unserialize( $row['sources'] );
            }
        }
        unset( $row );
        return array(
            'count'        => (int) ( $result['count'] ?? 0 ),
            'redirections' => $rows,
        );
    }

    public static function rankmath_create_redirection( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! class_exists( '\RankMath\Redirections\Redirection' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to manage redirections.', array( 'status' => 403 ) );
        }
        $source = trim( (string) ( $params['source'] ?? '' ) );
        $target = esc_url_raw( (string) ( $params['target'] ?? '' ) );
        if ( empty( $source ) || empty( $target ) ) {
            return new WP_Error( 'missing_params', 'source and target are required.', array( 'status' => 400 ) );
        }
        $redirection = \RankMath\Redirections\Redirection::from( array(
            'sources'     => array( array( 'pattern' => $source, 'comparison' => 'exact' ) ),
            'url_to'      => $target,
            'header_code' => (int) ( $params['header_code'] ?? 301 ),
            'status'      => 'active',
        ) );
        if ( $redirection->is_infinite_loop() ) {
            return new WP_Error( 'infinite_loop', 'Source and target create an infinite redirect loop.', array( 'status' => 400 ) );
        }
        $id = $redirection->save();
        if ( ! $id ) {
            return new WP_Error( 'create_failed', 'Rank Math rejected the redirection.', array( 'status' => 400 ) );
        }
        return array( 'id' => (int) $id, 'source' => $source, 'target' => $target, 'header_code' => (int) ( $params['header_code'] ?? 301 ) );
    }

    public static function rankmath_update_redirection( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! class_exists( '\RankMath\Redirections\Redirection' ) || ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active or permission is denied.', array( 'status' => 409 ) );
        }
        global $wpdb;
        $id = absint( $params['id'] ?? 0 );
        if ( ! $id ) {
            return new WP_Error( 'missing_id', 'Redirection id is required.', array( 'status' => 400 ) );
        }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rank_math_redirections WHERE id = %d", $id ), ARRAY_A );
        if ( ! $row ) {
            return new WP_Error( 'redirection_missing', 'Redirection not found: ' . $id, array( 'status' => 404 ) );
        }
        $status  = isset( $params['status'] ) && in_array( $params['status'], array( 'active', 'inactive', 'trashed' ), true )
            ? $params['status']
            : $row['status'];
        $sources = maybe_unserialize( $row['sources'] );
        if ( ! is_array( $sources ) || empty( $sources ) ) {
            return new WP_Error( 'invalid_sources', 'This redirection has no readable sources.', array( 'status' => 409 ) );
        }
        $data = array(
            'id'          => $id,
            'sources'     => $sources,
            'url_to'      => isset( $params['target'] ) ? esc_url_raw( (string) $params['target'] ) : $row['url_to'],
            'header_code' => isset( $params['header_code'] ) ? (int) $params['header_code'] : (int) $row['header_code'],
            'status'      => $status,
        );
        $redirection = \RankMath\Redirections\Redirection::from( $data );
        if ( $redirection->is_infinite_loop() ) {
            return new WP_Error( 'infinite_loop', 'Source and target create an infinite redirect loop.', array( 'status' => 400 ) );
        }
        $saved = $redirection->save();
        return array( 'id' => (int) $saved, 'status' => $status, 'target' => $data['url_to'], 'header_code' => $data['header_code'] );
    }

    public static function rankmath_delete_redirection( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) || ! class_exists( '\RankMath\Redirections\DB' ) || ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active or permission is denied.', array( 'status' => 409 ) );
        }
        $id = absint( $params['id'] ?? 0 );
        if ( ! $id ) {
            return new WP_Error( 'missing_id', 'Redirection id is required.', array( 'status' => 400 ) );
        }
        \RankMath\Redirections\DB::delete( $id );
        return array( 'deleted' => $id );
    }

    public static function rankmath_list_404_errors( $params ) {
        if ( ! defined( 'RANK_MATH_VERSION' ) ) {
            return new WP_Error( 'rankmath_missing', 'Rank Math is not active.', array( 'status' => 409 ) );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'rank_math_404_logs';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return new WP_Error( 'module_inactive', 'Rank Math 404 monitor tables do not exist. Activate the 404-monitor module first.', array( 'status' => 409 ) );
        }
        $limit  = min( 200, max( 1, (int) ( $params['limit'] ?? 50 ) ) );
        $offset = max( 0, (int) ( $params['offset'] ?? 0 ) );
        $rows   = $wpdb->get_results(
            $wpdb->prepare( "SELECT uri, accessed, times_accessed, referer, user_agent FROM {$table} ORDER BY accessed DESC LIMIT %d OFFSET %d", $limit, $offset ),
            ARRAY_A
        );
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        return array( 'total' => $total, 'items' => is_array( $rows ) ? $rows : array() );
    }

    /* ---------------------------------------------------------------------
     * AIOS firewall config (separate firewall resource)
     * ------------------------------------------------------------------- */

    public static function aios_firewall_get() {
        if ( ! class_exists( 'AIOS_Firewall_Resource' ) ) {
            return array();
        }
        $config = AIOS_Firewall_Resource::request( AIOS_Firewall_Resource::CONFIG );
        if ( ! is_object( $config ) || ! method_exists( $config, 'get_contents' ) ) {
            return array();
        }
        $contents = $config->get_contents();
        return is_array( $contents ) ? $contents : array();
    }

    public static function aios_firewall_save( $value ) {
        if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'AIOS_Firewall_Resource' ) ) {
            return false;
        }
        $config = AIOS_Firewall_Resource::request( AIOS_Firewall_Resource::CONFIG );
        if ( ! is_object( $config ) || ! method_exists( $config, 'set_contents' ) ) {
            return false;
        }
        $result = $config->set_contents( is_array( $value ) ? $value : array() );
        if ( false === $result ) {
            return false;
        }
        if ( class_exists( 'AIOWPSecurity_Utility' ) && AIOWPSecurity_Utility::allow_to_write_to_htaccess() && class_exists( 'AIOWPSecurity_Utility_Htaccess' ) ) {
            AIOWPSecurity_Utility_Htaccess::write_to_htaccess( false );
        }
        return true;
    }

    /* ---------------------------------------------------------------------
     * WPForms
     * ------------------------------------------------------------------- */

    public static function wpforms_get_settings() {
        return get_option( 'wpforms_settings', array() );
    }

    public static function wpforms_save_settings( $value ) {
        if ( ! function_exists( 'wpforms_update_settings' ) ) {
            return false;
        }
        wpforms_update_settings( is_array( $value ) ? $value : array() );
        return true;
    }

    /* ---------------------------------------------------------------------
     * Guards
     * ------------------------------------------------------------------- */

    private static function redirection_guard() {
        if ( ! defined( 'REDIRECTION_VERSION' ) || ! class_exists( 'Red_Item' ) ) {
            return new WP_Error( 'redirection_missing', 'Redirection is not active.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to manage redirects.', array( 'status' => 403 ) );
        }
        return true;
    }
}
