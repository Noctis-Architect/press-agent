<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Elementor_Adapter {
    public static function is_active() {
        return did_action( 'elementor/loaded' );
    }

    public static function get_layout( $page_id ) {
        if ( ! self::is_active() ) return new WP_Error( 'elementor_missing', 'Elementor is not active' );
        
        $document = \Elementor\Plugin::$instance->documents->get( $page_id );
        if ( ! $document ) return new WP_Error( 'invalid_page', 'Invalid page or not built with Elementor' );
        
        return $document->get_elements_data();
    }

    public static function update_widget( $page_id, $widget_id, $settings ) {
        if ( ! self::is_active() ) return new WP_Error( 'elementor_missing', 'Elementor is not active' );
        
        $elements = self::get_layout( $page_id );
        if ( is_wp_error( $elements ) ) return $elements;
        
        $found = self::_find_widget_recursive( $elements, $widget_id, $settings );
        if ( ! $found ) return new WP_Error( 'widget_not_found', 'Widget ID not found' );
        
        return self::_save_via_elementor( $page_id, $elements );
    }

    public static function add_section( $page_id, $section_data ) {
        if ( ! self::is_active() ) return new WP_Error( 'elementor_missing', 'Elementor is not active' );
        
        if ( isset( $section_data['_replace_all'] ) && $section_data['_replace_all'] === true ) {
            $elements = $section_data['elements'] ?? array();
        } else {
            $elements = self::get_layout( $page_id );
            if ( is_wp_error( $elements ) ) return $elements;
            if ( is_array( $elements ) ) {
                $elements = array_values( array_filter( $elements, function( $el ) {
                    return ! empty( $el['elType'] );
                } ) );
            } else {
                $elements = array();
            }
            if ( ! empty( $section_data['elType'] ) ) {
                $elements[] = $section_data;
            }
        }

        // Clean out any element missing elType
        $elements = array_values( array_filter( $elements, function( $el ) {
            return ! empty( $el['elType'] );
        } ) );
        
        return self::_save_via_elementor( $page_id, $elements );
    }

    private static function _save_via_elementor( $page_id, $elements ) {
        // Create an Action Guard snapshot before modifying Elementor layout
        if ( class_exists( 'PressAgent_Action_Guard' ) ) {
            PressAgent_Action_Guard::create_snapshot( 'update_elementor', array( 'page_id' => $page_id ) );
        }

        // Elementor save requires edit_posts or edit_pages capability on the authenticated user.
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
            return new WP_Error( 'forbidden', 'Elementor writes require a logged-in user with edit_posts or edit_pages capability.', array( 'status' => 403 ) );
        }

        $document = \Elementor\Plugin::$instance->documents->get( $page_id );
        if ( ! $document ) return new WP_Error( 'save_failed', 'Could not load document for saving' );
        
        $save_result = $document->save( array( 'elements' => $elements ) );
        
        // Ensure _elementor_data and edit mode are properly updated
        update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
        update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );

        // Clear Elementor element and CSS caches for this page
        delete_post_meta( $page_id, '_elementor_element_cache' );
        delete_post_meta( $page_id, '_elementor_css' );
        if ( isset( \Elementor\Plugin::$instance->element_cache ) ) {
            \Elementor\Plugin::$instance->element_cache->clear_cache( $page_id );
        }
        if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        // Purge site caches
        PressAgent_Generic_Settings::purge_cache( 'all' );

        return rest_ensure_response( array( 'message' => 'Saved successfully.' ) );
    }

    private static function _find_widget_recursive( &$elements, $widget_id, $new_settings ) {
        foreach ( $elements as &$element ) {
            if ( isset( $element['id'] ) && $element['id'] === $widget_id ) {
                $element['settings'] = array_merge( $element['settings'] ?? array(), $new_settings );
                return true;
            }
            if ( ! empty( $element['elements'] ) ) {
                if ( self::_find_widget_recursive( $element['elements'], $widget_id, $new_settings ) ) {
                    return true;
                }
            }
        }
        return false;
    }
}
