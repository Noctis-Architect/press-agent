<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Action_Guard {
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action_type varchar(100) NOT NULL,
            context longtext NOT NULL,
            snapshot_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function create_snapshot( $action_type, $context ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        
        $snapshot_data = self::gather_snapshot_data( $action_type, $context );

        $wpdb->insert(
            $table_name,
            array(
                'action_type' => $action_type,
                'context' => wp_json_encode( $context ),
                'snapshot_data' => wp_json_encode( $snapshot_data ),
                'status' => 'active',
                'created_at' => current_time( 'mysql' )
            )
        );

        return $wpdb->insert_id;
    }

    private static function gather_snapshot_data( $action_type, $context ) {
        if ( $action_type === 'update_option' && isset( $context['option_name'] ) ) {
            return get_option( $context['option_name'] );
        }
        if ( $action_type === 'update_elementor' && isset( $context['page_id'] ) ) {
            $page_id = (int) $context['page_id'];
            return array(
                'data' => get_post_meta( $page_id, '_elementor_data', true ),
                'template' => get_post_meta( $page_id, '_wp_page_template', true ),
                'edit_mode' => get_post_meta( $page_id, '_elementor_edit_mode', true ),
                'content' => get_post_field( 'post_content', $page_id ),
                'title' => get_the_title( $page_id )
            );
        }
        return array();
    }

    public static function rollback( $snapshot_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        
        $snapshot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d AND status = 'active'", $snapshot_id ) );
        if ( ! $snapshot ) {
            return new WP_Error( 'invalid_snapshot', 'Snapshot not found or already rolled back.', array( 'status' => 404 ) );
        }

        $context = json_decode( $snapshot->context, true );
        $data = json_decode( $snapshot->snapshot_data, true );

        if ( $snapshot->action_type === 'update_option' && isset( $context['option_name'] ) ) {
            $option_name = $context['option_name'];

            // Only allow rollback of options that are still within the settings allowlist,
            // and never roll back the allowlist itself or security-critical options.
            if ( $option_name === 'pressagent_settings_allowlist' ) {
                return new WP_Error( 'forbidden', 'Rollback of the settings allowlist is not allowed.', array( 'status' => 403 ) );
            }
            if ( ! PressAgent_Generic_Settings::is_allowed( $option_name ) ) {
                return new WP_Error( 'forbidden', 'Rollback is limited to allowlisted settings.', array( 'status' => 403 ) );
            }

            update_option( $option_name, $data );
            if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
                PressAgent_Generic_Settings::purge_cache( 'all' );
            }
        } elseif ( $snapshot->action_type === 'update_elementor' && isset( $context['page_id'] ) ) {
            $page_id = (int) $context['page_id'];
            if ( isset( $data['data'] ) ) {
                update_post_meta( $page_id, '_elementor_data', wp_slash( $data['data'] ) );
            }
            if ( isset( $data['template'] ) ) {
                update_post_meta( $page_id, '_wp_page_template', $data['template'] );
            }
            if ( isset( $data['edit_mode'] ) ) {
                update_post_meta( $page_id, '_elementor_edit_mode', $data['edit_mode'] );
            }
            if ( isset( $data['content'] ) ) {
                wp_update_post( array( 'ID' => $page_id, 'post_content' => $data['content'] ) );
            }

            delete_post_meta( $page_id, '_elementor_element_cache' );
            delete_post_meta( $page_id, '_elementor_css' );
            if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
                PressAgent_Generic_Settings::purge_cache( 'all' );
            }
        }

        $wpdb->update( $table_name, array( 'status' => 'rolled_back' ), array( 'id' => $snapshot_id ) );
        return true;
    }

    public static function get_snapshots( $limit = 30 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
    }

    public static function count_snapshots() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
    }

    public static function delete_snapshot( $snapshot_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pressagent_snapshots';
        return $wpdb->delete( $table_name, array( 'id' => (int) $snapshot_id ) );
    }

    public static function is_reversible( $action_type ) {
        $reversible = array( 'update_option', 'update_post', 'update_elementor' );
        return in_array( $action_type, $reversible, true );
    }

    public static function require_confirmation( $action_type, $context ) {
        if ( self::is_reversible( $action_type ) ) {
            return false;
        }
        
        // Generates a confirmation token for non-reversible actions
        return wp_generate_password( 24, false );
    }
}
