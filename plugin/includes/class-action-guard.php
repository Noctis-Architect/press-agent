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
            update_option( $context['option_name'], $data );
        }

        $wpdb->update( $table_name, array( 'status' => 'rolled_back' ), array( 'id' => $snapshot_id ) );
        return true;
    }

    public static function is_reversible( $action_type ) {
        $reversible = array( 'update_option', 'update_post' );
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
