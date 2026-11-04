<?php
/**
 * Contact Form 7 and WPForms integrations.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Forms_Manager {

    public static function is_cf7() {
        return defined( 'WPCF7_VERSION' ) && class_exists( 'WPCF7_ContactForm' );
    }

    public static function is_wpforms() {
        return defined( 'WPFORMS_VERSION' ) && function_exists( 'wpforms' );
    }

    /**
     * List forms across active form plugins.
     *
     * @param string $provider auto|cf7|wpforms
     * @return array|WP_Error
     */
    public static function list_forms( $provider = 'auto' ) {
        $result = array( 'providers' => array() );

        if ( ( 'auto' === $provider || 'cf7' === $provider ) && self::is_cf7() ) {
            $result['providers']['cf7'] = self::list_cf7();
        }
        if ( ( 'auto' === $provider || 'wpforms' === $provider ) && self::is_wpforms() ) {
            $result['providers']['wpforms'] = self::list_wpforms();
        }

        if ( empty( $result['providers'] ) ) {
            return new WP_Error( 'forms_missing', 'Neither Contact Form 7 nor WPForms is active.', array( 'status' => 409 ) );
        }
        return $result;
    }

    /**
     * Get one form.
     *
     * @param int    $form_id     Form id.
     * @param string $provider    cf7|wpforms|auto
     * @param bool   $include_data Include full form data (WPForms JSON / CF7 properties).
     * @return array|WP_Error
     */
    public static function get_form( $form_id, $provider = 'auto', $include_data = true ) {
        if ( 'auto' === $provider ) {
            $provider = self::is_cf7() ? 'cf7' : ( self::is_wpforms() ? 'wpforms' : '' );
        }

        if ( 'cf7' === $provider ) {
            return self::get_cf7( $form_id, $include_data );
        }
        if ( 'wpforms' === $provider ) {
            return self::get_wpforms( $form_id, $include_data );
        }
        return new WP_Error( 'forms_missing', 'No supported form plugin is active.', array( 'status' => 409 ) );
    }

    /**
     * Create or update a Contact Form 7 form.
     *
     * @param array $data Form fields: id, title, locale, form, mail, mail_2, messages, additional_settings.
     * @return array|WP_Error
     */
    public static function save_cf7( $data ) {
        if ( ! self::is_cf7() ) {
            return new WP_Error( 'cf7_missing', 'Contact Form 7 is not active.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit Contact Form 7 forms.', array( 'status' => 403 ) );
        }
        if ( ! function_exists( 'wpcf7_save_contact_form' ) ) {
            return new WP_Error( 'cf7_api_missing', 'wpcf7_save_contact_form() is unavailable.', array( 'status' => 500 ) );
        }

        $id = isset( $data['id'] ) ? (int) $data['id'] : -1;

        $payload = array( 'id' => $id );
        if ( isset( $data['title'] ) ) {
            $payload['title'] = sanitize_text_field( $data['title'] );
        }
        if ( isset( $data['locale'] ) && function_exists( 'wpcf7_is_valid_locale' ) && wpcf7_is_valid_locale( $data['locale'] ) ) {
            $payload['locale'] = $data['locale'];
        }
        if ( isset( $data['form'] ) ) {
            $payload['form'] = (string) $data['form'];
        }
        foreach ( array( 'mail', 'mail_2', 'messages' ) as $key ) {
            if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                $payload[ $key ] = $data[ $key ];
            }
        }
        if ( isset( $data['additional_settings'] ) ) {
            $payload['additional_settings'] = (string) $data['additional_settings'];
        }

        $form = wpcf7_save_contact_form( $payload, 'save' );
        if ( ! $form ) {
            return new WP_Error( 'save_failed', 'Contact Form 7 could not save the form.', array( 'status' => 500 ) );
        }

        $config_errors = array();
        if ( class_exists( 'WPCF7_ConfigValidator' ) ) {
            $validator = new WPCF7_ConfigValidator( $form );
            $validator->validate();
            $config_errors = $validator->collect_error_messages( array( 'decodes_html_entities' => true ) );
            $validator->save();
        }

        return array(
            'success'       => true,
            'provider'      => 'cf7',
            'form_id'       => $form->id(),
            'title'         => $form->title(),
            'shortcode'     => $form->shortcode(),
            'config_errors' => $config_errors,
            'message'       => $id > 0 ? 'Form updated.' : 'Form created.',
        );
    }

    /**
     * Permanently delete a Contact Form 7 form.
     *
     * @param int  $form_id Form id.
     * @param bool $confirm Explicit confirmation.
     * @return array|WP_Error
     */
    public static function delete_cf7( $form_id, $confirm = false ) {
        if ( ! self::is_cf7() ) {
            return new WP_Error( 'cf7_missing', 'Contact Form 7 is not active.', array( 'status' => 409 ) );
        }
        if ( true !== $confirm ) {
            return new WP_Error( 'confirmation_required', 'Deletion is permanent. Call again with confirm:true after user approval.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'wpcf7_delete_contact_form', $form_id ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to delete this form.', array( 'status' => 403 ) );
        }

        $form = wpcf7_contact_form( (int) $form_id );
        if ( ! $form ) {
            return new WP_Error( 'form_missing', 'Contact Form 7 form not found: ' . $form_id, array( 'status' => 404 ) );
        }

        $title = $form->title();
        $form->delete();

        return array(
            'success'  => true,
            'provider' => 'cf7',
            'form_id'  => (int) $form_id,
            'title'    => $title,
            'message'  => 'Form deleted permanently.',
        );
    }

    /* ---------------------------------------------------------------------
     * Providers
     * ------------------------------------------------------------------- */

    private static function list_cf7() {
        $forms = WPCF7_ContactForm::find( array( 'post_status' => 'any' ) );
        $items = array();
        foreach ( $forms as $form ) {
            $properties = $form->get_properties();
            $form_html  = (string) ( $properties['form'] ?? '' );
            $items[]    = array(
                'id'          => $form->id(),
                'title'       => $form->title(),
                'locale'      => $form->locale(),
                'shortcode'   => $form->shortcode(),
                'field_count' => preg_match_all( '/\[[a-zA-Z0-9_*\-]+/', $form_html ),
            );
        }
        return $items;
    }

    private static function get_cf7( $form_id, $include_data ) {
        $form = wpcf7_contact_form( (int) $form_id );
        if ( ! $form ) {
            return new WP_Error( 'form_missing', 'Contact Form 7 form not found: ' . $form_id, array( 'status' => 404 ) );
        }

        $data = array(
            'provider'  => 'cf7',
            'id'        => $form->id(),
            'title'     => $form->title(),
            'locale'    => $form->locale(),
            'shortcode' => $form->shortcode(),
        );
        if ( $include_data ) {
            $data['properties'] = $form->get_properties();
        }
        return $data;
    }

    private static function list_wpforms() {
        $forms = wpforms()->obj( 'form' )->get( '', array( 'post_status' => 'any' ) );
        $items = array();
        if ( is_array( $forms ) ) {
            foreach ( $forms as $post ) {
                $data  = wpforms_decode( $post->post_content );
                $items[] = array(
                    'id'          => $post->ID,
                    'title'       => get_the_title( $post ),
                    'status'      => $post->post_status,
                    'shortcode'   => '[wpforms id="' . $post->ID . '"]',
                    'entries'     => (int) get_post_meta( $post->ID, 'wpforms_entries_count', true ),
                    'field_count' => is_array( $data['fields'] ?? null ) ? count( $data['fields'] ) : 0,
                );
            }
        }
        return $items;
    }

    private static function get_wpforms( $form_id, $include_data ) {
        $form = wpforms()->obj( 'form' )->get( (int) $form_id, array( 'post_status' => 'any', 'content_only' => true ) );
        if ( ! $form || ! is_array( $form ) ) {
            return new WP_Error( 'form_missing', 'WPForms form not found: ' . $form_id, array( 'status' => 404 ) );
        }

        $fields = is_array( $form['fields'] ?? null ) ? $form['fields'] : array();
        $summary = array(
            'provider'    => 'wpforms',
            'id'          => (int) $form_id,
            'title'       => $form['settings']['form_title'] ?? get_the_title( $form_id ),
            'field_count' => count( $fields ),
            'field_types' => array_values( array_filter( array_map( static function ( $field ) {
                return is_array( $field ) ? ( $field['type'] ?? null ) : null;
            }, $fields ) ) ),
        );

        if ( $include_data ) {
            $summary['data'] = $form;
        }

        return $summary;
    }
}
