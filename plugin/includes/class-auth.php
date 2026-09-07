<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Auth {
    public function __construct() {}

    public static function authenticate( WP_REST_Request $request ) {
        $header = $request->get_header( 'authorization' );
        if ( ! $header || ! preg_match( '/Bearer\s+(.*)/i', $header, $matches ) ) {
            return new WP_Error( 'rest_forbidden', 'Missing or invalid Authorization header.', array( 'status' => 401 ) );
        }

        $token = $matches[1];
        $tokens = get_option( 'pressagent_tokens', array() );
        
        require_once ABSPATH . WPINC . '/class-phpass.php';
        $hasher = new PasswordHash( 8, true );

        foreach ( $tokens as $id => $token_data ) {
            if ( $hasher->CheckPassword( $token, $token_data['hash'] ) ) {
                $token_data['last_used'] = current_time( 'mysql' );
                $tokens[$id] = $token_data;
                update_option( 'pressagent_tokens', $tokens );
                
                $request->set_param( 'pressagent_scopes', $token_data['scopes'] );
                return true;
            }
        }
        
        return new WP_Error( 'rest_forbidden', 'Invalid token.', array( 'status' => 401 ) );
    }

    public static function has_scope( WP_REST_Request $request, $required_scope ) {
        $auth_result = self::authenticate( $request );
        if ( is_wp_error( $auth_result ) ) {
            return $auth_result;
        }
        
        $scopes = $request->get_param( 'pressagent_scopes' );
        if ( ! is_array( $scopes ) ) {
            return new WP_Error( 'rest_forbidden', 'Insufficient scope: ' . $required_scope, array( 'status' => 403 ) );
        }

        // God Mode / Wildcard bypasses individual scope requirements
        if ( in_array( '*', $scopes, true ) || in_array( 'god_mode', $scopes, true ) ) {
            return true;
        }
        
        if ( ! in_array( $required_scope, $scopes, true ) ) {
            return new WP_Error( 'rest_forbidden', 'Insufficient scope: ' . $required_scope, array( 'status' => 403 ) );
        }
        
        return true;
    }

    public static function generate_token( $label, $scopes ) {
        $token = wp_generate_password( 48, false, false );
        
        require_once ABSPATH . WPINC . '/class-phpass.php';
        $hasher = new PasswordHash( 8, true );
        $hash = $hasher->HashPassword( $token );
        
        $tokens = get_option( 'pressagent_tokens', array() );
        $id = wp_generate_uuid4();
        
        $tokens[$id] = array(
            'id' => $id,
            'label' => sanitize_text_field( $label ),
            'hash' => $hash,
            'raw_token' => $token,
            'scopes' => array_map('sanitize_text_field', $scopes),
            'created_at' => current_time( 'mysql' ),
            'last_used' => null
        );
        
        update_option( 'pressagent_tokens', $tokens );
        update_option( 'pressagent_last_token', $token );
        return $token; 
    }

    public static function revoke_token( $token_id ) {
        $tokens = get_option( 'pressagent_tokens', array() );
        if ( isset( $tokens[$token_id] ) ) {
            unset( $tokens[$token_id] );
            update_option( 'pressagent_tokens', $tokens );
            if ( empty( $tokens ) ) {
                delete_option( 'pressagent_last_token' );
            } else {
                $last = end( $tokens );
                if ( ! empty( $last['raw_token'] ) ) {
                    update_option( 'pressagent_last_token', $last['raw_token'] );
                }
            }
            return true;
        }
        return false;
    }
}
