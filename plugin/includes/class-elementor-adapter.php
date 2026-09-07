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
        
        $elements = self::get_layout( $page_id );
        if ( is_wp_error( $elements ) ) return $elements;
        
        $elements[] = $section_data;
        
        return self::_save_via_elementor( $page_id, $elements );
    }

    private static function _save_via_elementor( $page_id, $elements ) {
        $document = \Elementor\Plugin::$instance->documents->get( $page_id );
        if ( ! $document ) return new WP_Error( 'save_failed', 'Could not load document for saving' );
        
        $document->save( array( 'elements' => $elements ) );
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
