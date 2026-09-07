<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Class PressAgent_Code_Executor
 * 
 * Intentionally stubbed implementation of the Code Executor / God Mode.
 * This class exists so that the REST API can reference it safely without 
 * introducing arbitrary remote code execution (RCE) vulnerabilities.
 * It is restricted and should only be implemented securely on staging environments.
 */
class PressAgent_Code_Executor {
    
    /**
     * Always returns false to ensure God Mode is fully disabled by default.
     * 
     * @return bool
     */
    public static function is_enabled() {
        return false;
    }

    /**
     * Stub for creating a plugin. Returns an error indicating God mode is disabled.
     * 
     * @param string $name The requested plugin name.
     * @param string $code The requested plugin code.
     * @return WP_Error
     */
    public static function create_plugin( $name, $code ) {
        return new WP_Error(
            'god_mode_disabled',
            'God Mode is disabled. This feature is only available on staging environments with explicit administrator confirmation.',
            array( 'status' => 403 )
        );
    }
}
