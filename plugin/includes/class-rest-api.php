<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_REST_API {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $namespace = 'pressagent/v1';

        // Pages
        register_rest_route( $namespace, '/pages', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( 'PressAgent_Pages_Controller', 'list_pages' ),
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'pages:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( 'PressAgent_Pages_Controller', 'create_page' ),
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'pages:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/pages/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array( 'PressAgent_Pages_Controller', 'delete_page' ),
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'pages:write' ); }
        ) );

        // Elementor
        register_rest_route( $namespace, '/elementor/(?P<page_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) { return rest_ensure_response( PressAgent_Elementor_Adapter::get_layout( $request->get_param( 'page_id' ) ) ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'elementor:read' ); }
        ) );

        register_rest_route( $namespace, '/elementor/(?P<page_id>\d+)/widget', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                return PressAgent_Elementor_Adapter::update_widget( $request->get_param( 'page_id' ), $params['widget_id'] ?? '', $params['settings'] ?? array() );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'elementor:write' ); }
        ) );

        register_rest_route( $namespace, '/elementor/(?P<page_id>\d+)/section', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $section_data = isset( $params['section_data'] ) ? $params['section_data'] : $params;
                return PressAgent_Elementor_Adapter::add_section( $request->get_param( 'page_id' ), $section_data );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'elementor:write' ); }
        ) );

        // Settings
        register_rest_route( $namespace, '/settings/(?P<plugin_slug>[a-zA-Z0-9_-]+)/(?P<key>[a-zA-Z0-9_\.\-]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) { return rest_ensure_response( PressAgent_Generic_Settings::read_option( $request->get_param( 'plugin_slug' ), $request->get_param( 'key' ) ) ); },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    return PressAgent_Generic_Settings::write_option( $request->get_param( 'plugin_slug' ), $request->get_param( 'key' ), $params['value'] ?? null );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/cache/purge', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                return PressAgent_Generic_Settings::purge_cache( $params['plugin_slug'] ?? 'all' );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
        ) );

        register_rest_route( $namespace, '/cache/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array( 'PressAgent_Generic_Settings', 'get_cache_status' ),
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
        ) );

        // Debug Log
        register_rest_route( $namespace, '/debug-log', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                return rest_ensure_response( PressAgent_Debug_Log_Parser::read_log( $request->get_param( 'lines' ) ?: 100, $request->get_param( 'level' ) ?: 'all' ) );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'debug:read' ); }
        ) );

        register_rest_route( $namespace, '/debug-log/diagnose', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                return rest_ensure_response( PressAgent_Debug_Log_Parser::diagnose( $params['error_text'] ?? '' ) );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'debug:write' ); }
        ) );

        // Security
        register_rest_route( $namespace, '/security/summary', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Security_Audit::get_summary() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'security:read' ); }
        ) );

        // Action Guard Rollback
        register_rest_route( $namespace, '/action-guard/rollback/(?P<snapshot_id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $result = PressAgent_Action_Guard::rollback( (int) $request->get_param( 'snapshot_id' ) );
                if ( is_wp_error( $result ) ) return $result;
                return rest_ensure_response( array( 'message' => 'Rollback successful.' ) );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
        ) );

        // Code Executor
        register_rest_route( $namespace, '/code/create-plugin', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                return PressAgent_Code_Executor::create_plugin( $params['name'] ?? '', $params['code'] ?? '' );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'code:execute' ); }
        ) );
    }
}
