<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Pages_Controller {
    public static function list_pages( WP_REST_Request $request ) {
        $args = array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'any'
        );
        $pages = get_posts( $args );
        
        $response = array();
        foreach ( $pages as $page ) {
            $response[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'slug' => $page->post_name,
                'status' => $page->post_status,
                'date' => $page->post_date,
                'elementor_enabled' => get_post_meta( $page->ID, '_elementor_edit_mode', true ) === 'builder'
            );
        }
        return rest_ensure_response( $response );
    }

    public static function create_page( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $page_data = array(
            'post_title'   => sanitize_text_field( $params['title'] ?? 'New Page' ),
            'post_content' => wp_kses_post( $params['content'] ?? '' ),
            'post_status'  => sanitize_text_field( $params['status'] ?? 'draft' ),
            'post_type'    => 'page'
        );
        
        $page_id = wp_insert_post( $page_data, true );
        if ( is_wp_error( $page_id ) ) {
            return $page_id;
        }

        if ( ! empty( $params['elementor_enabled'] ) ) {
            update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
        }

        return rest_ensure_response( array( 'id' => $page_id, 'message' => 'Page created successfully.' ) );
    }

    public static function delete_page( WP_REST_Request $request ) {
        $page_id = (int) $request->get_param( 'id' );
        
        $action_type = 'delete_page';
        $context = array( 'page_id' => $page_id );
        
        if ( ! PressAgent_Action_Guard::is_reversible( $action_type ) ) {
            $confirm = $request->get_param( 'confirmation_token' );
            if ( ! $confirm ) {
                $token = PressAgent_Action_Guard::require_confirmation( $action_type, $context );
                return new WP_Error( 'confirmation_required', 'This action is non-reversible. Please submit again with the confirmation_token.', array( 'status' => 400, 'confirmation_token' => $token ) );
            }
        }

        $result = wp_delete_post( $page_id, true );
        if ( ! $result ) {
            return new WP_Error( 'delete_failed', 'Failed to delete page.', array( 'status' => 500 ) );
        }
        return rest_ensure_response( array( 'message' => 'Page deleted successfully.' ) );
    }
}
