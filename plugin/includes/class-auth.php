<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Auth {
    /**
     * In-memory cache for validated tokens during a single request lifecycle.
     * Prevents repeating the PasswordHash computation multiple times.
     *
     * @var array
     */
    private static $token_cache = array();

    public function __construct() {
        add_filter( 'determine_current_user', array( __CLASS__, 'determine_current_user' ), 20 );
    }

    /**
     * Helper to extract the Bearer token from the incoming HTTP request.
     *
     * @return string|null
     */
    public static function get_bearer_token() {
        $header = null;
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $header = trim( $_SERVER['HTTP_AUTHORIZATION'] );
        } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
            $header = trim( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
        } elseif ( function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
            if ( isset( $headers['Authorization'] ) ) {
                $header = trim( $headers['Authorization'] );
            } elseif ( isset( $headers['authorization'] ) ) {
                $header = trim( $headers['authorization'] );
            }
        }

        if ( $header && preg_match( '/Bearer\s+(.*)/i', $header, $matches ) ) {
            return trim( $matches[1] );
        }

        return null;
    }

    /**
     * Cryptographically verify a token against stored hashed tokens.
     *
     * @param string $token
     * @return array|null Returns array('id' => $id, 'data' => $token_data) on success, null on failure.
     */
    public static function verify_token( $token ) {
        if ( empty( $token ) || ! is_string( $token ) ) {
            return null;
        }

        if ( array_key_exists( $token, self::$token_cache ) ) {
            return self::$token_cache[$token];
        }

        $tokens = get_option( 'pressagent_tokens', array() );
        if ( empty( $tokens ) || ! is_array( $tokens ) ) {
            self::$token_cache[$token] = null;
            return null;
        }

        require_once ABSPATH . WPINC . '/class-phpass.php';
        $hasher = new PasswordHash( 8, true );

        foreach ( $tokens as $id => $token_data ) {
            if ( ! empty( $token_data['hash'] ) && $hasher->CheckPassword( $token, $token_data['hash'] ) ) {
                $result = array(
                    'id'   => $id,
                    'data' => $token_data,
                );
                self::$token_cache[$token] = $result;
                return $result;
            }
        }

        self::$token_cache[$token] = null;
        return null;
    }

    /**
     * Resolve the corresponding WP_User instance for a verified token.
     *
     * @param array $token_data
     * @return WP_User|null
     */
    public static function get_user_for_token( $token_data ) {
        $user_id = ! empty( $token_data['user_id'] ) ? (int) $token_data['user_id'] : 0;
        if ( $user_id > 0 ) {
            $user = get_userdata( $user_id );
            if ( $user && $user->exists() ) {
                return $user;
            }
        }

        // Fallback for legacy tokens: associate with the primary active administrator
        $admins = get_users( array(
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
            'order'   => 'ASC',
        ) );

        if ( ! empty( $admins ) ) {
            return $admins[0];
        }

        return null;
    }

    /**
     * WordPress determine_current_user filter callback.
     * Maps valid PressAgent Bearer tokens to their associated administrator user.
     *
     * @param int|false $user_id
     * @return int|false
     */
    public static function determine_current_user( $user_id ) {
        // If a user is already authenticated (e.g. standard WP cookie), keep it
        if ( ! empty( $user_id ) ) {
            return $user_id;
        }

        $token = self::get_bearer_token();
        if ( empty( $token ) ) {
            return $user_id;
        }

        $verified = self::verify_token( $token );
        if ( ! $verified ) {
            return $user_id;
        }

        $user = self::get_user_for_token( $verified['data'] );
        if ( $user && $user->exists() ) {
            return (int) $user->ID;
        }

        return $user_id;
    }

    /**
     * Authenticate an incoming WP_REST_Request.
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function authenticate( WP_REST_Request $request ) {
        $header = $request->get_header( 'authorization' );
        if ( ! $header || ! preg_match( '/Bearer\s+(.*)/i', $header, $matches ) ) {
            return new WP_Error( 'rest_forbidden', 'Missing or invalid Authorization header.', array( 'status' => 401 ) );
        }

        $token = trim( $matches[1] );
        $verified = self::verify_token( $token );
        if ( ! $verified ) {
            return new WP_Error( 'rest_forbidden', 'Invalid token.', array( 'status' => 401 ) );
        }

        $id = $verified['id'];
        $token_data = $verified['data'];

        // Update last_used timestamp
        $token_data['last_used'] = current_time( 'mysql' );
        $tokens = get_option( 'pressagent_tokens', array() );
        $tokens[$id] = $token_data;
        update_option( 'pressagent_tokens', $tokens );

        $request->set_param( 'pressagent_scopes', $token_data['scopes'] );
        $request->set_param( 'pressagent_token_id', $id );

        // Associate user context with the authenticated token
        $user = self::get_user_for_token( $token_data );
        if ( $user && $user->exists() ) {
            wp_set_current_user( $user->ID );
            $request->set_param( 'pressagent_user_id', $user->ID );
        }

        return true;
    }

    /**
     * Check if request has the required scope.
     *
     * @param WP_REST_Request $request
     * @param string $required_scope
     * @return true|WP_Error
     */
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

    /**
     * Generate a new cryptographically secure token and store its hash and user association.
     *
     * @param string $label
     * @param array $scopes
     * @param int|null $user_id
     * @return string
     */
    public static function generate_token( $label, $scopes, $user_id = null ) {
        $token = wp_generate_password( 48, false, false );

        require_once ABSPATH . WPINC . '/class-phpass.php';
        $hasher = new PasswordHash( 8, true );
        $hash = $hasher->HashPassword( $token );

        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }
        if ( empty( $user_id ) ) {
            $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
            if ( ! empty( $admins ) ) {
                $user_id = (int) $admins[0];
            }
        }

        $tokens = get_option( 'pressagent_tokens', array() );
        $id = wp_generate_uuid4();

        $tokens[$id] = array(
            'id'         => $id,
            'label'      => sanitize_text_field( $label ),
            'hash'       => $hash,
            'user_id'    => (int) $user_id,
            'scopes'     => array_map( 'sanitize_text_field', $scopes ),
            'created_at' => current_time( 'mysql' ),
            'last_used'  => null,
        );

        update_option( 'pressagent_tokens', $tokens );
        return $token;
    }

    /**
     * Revoke a token by its UUID.
     *
     * @param string $token_id
     * @return bool
     */
    public static function revoke_token( $token_id ) {
        $tokens = get_option( 'pressagent_tokens', array() );
        if ( isset( $tokens[$token_id] ) ) {
            unset( $tokens[$token_id] );
            update_option( 'pressagent_tokens', $tokens );
            self::$token_cache = array();
            return true;
        }
        return false;
    }
}

// Hook into WordPress authentication pipeline
add_filter( 'determine_current_user', array( 'PressAgent_Auth', 'determine_current_user' ), 20 );
