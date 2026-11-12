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
        register_rest_route( $namespace, '/elementor/kit', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Elementor_Adapter::get_kit() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'elementor:read' ); }
        ) );

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
                if ( is_string( $params ) ) {
                    $decoded = json_decode( $params, true );
                    if ( is_array( $decoded ) ) {
                        $params = $decoded;
                    }
                }
                $section_data = isset( $params['section_data'] ) ? $params['section_data'] : $params;
                return PressAgent_Elementor_Adapter::add_section( $request->get_param( 'page_id' ), $section_data );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'elementor:write' ); }
        ) );

        // Plugin lifecycle
        register_rest_route( $namespace, '/plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $include_inactive = null === $request->get_param( 'include_inactive' ) ? true : (bool) $request->get_param( 'include_inactive' );
                return rest_ensure_response( PressAgent_Plugin_Manager::list_plugins( $include_inactive ) );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:read' ); }
        ) );

        register_rest_route( $namespace, '/plugins/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $query = $request->get_param( 'q' );
                if ( null === $query || '' === $query ) {
                    $query = $request->get_param( 'query' );
                }
                $res = PressAgent_Plugin_Manager::search_plugins(
                    $query,
                    (int) ( $request->get_param( 'page' ) ?: 1 ),
                    (int) ( $request->get_param( 'limit' ) ?: 10 )
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:read' ); }
        ) );

        register_rest_route( $namespace, '/plugins/install', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params     = $request->get_json_params();
                $identifier = $params['slug'] ?? ( $params['plugin'] ?? ( $params['query'] ?? '' ) );
                $res        = PressAgent_Plugin_Manager::install_plugin( $identifier, $params['activate'] ?? false );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:write' ); }
        ) );

        register_rest_route( $namespace, '/plugins/toggle', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Plugin_Manager::toggle_plugin( $params['plugin'] ?? '', $params['action'] ?? 'activate' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:write' ); }
        ) );

        register_rest_route( $namespace, '/plugins/update', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Plugin_Manager::update_plugin( $params['plugin'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:write' ); }
        ) );

        // Plugin settings and actions (validated registry)
        register_rest_route( $namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/settings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $ids = $request->get_param( 'ids' );
                    if ( empty( $ids ) ) {
                        $res = PressAgent_Plugin_Registry::get_catalog( $request->get_param( 'slug' ) );
                    } else {
                        $fields = array_filter( array_map( 'trim', explode( ',', (string) $request->get_param( 'fields' ) ) ) );
                        $ids    = array_filter( array_map( 'trim', explode( ',', (string) $ids ) ) );
                        $res    = PressAgent_Plugin_Registry::read_settings( $request->get_param( 'slug' ), $ids, $fields );
                    }
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Plugin_Registry::write_settings( $request->get_param( 'slug' ), $params['values'] ?? array() );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/action', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Plugin_Registry::run_action(
                    $request->get_param( 'slug' ),
                    $params['action'] ?? '',
                    $params['params'] ?? array(),
                    $params['confirm'] ?? false
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'plugins:write' ); }
        ) );

        register_rest_route( $namespace, '/plugins/settings/restore', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Plugin_Registry::restore_setting( $params['snapshot_id'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
        ) );

        // SEO metadata (Yoast / Rank Math)
        register_rest_route( $namespace, '/seo/providers', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Seo_Manager::providers() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'seo:read' ); }
        ) );

        register_rest_route( $namespace, '/seo/post/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $res = PressAgent_Seo_Manager::get( 'post', $request->get_param( 'id' ), '', $request->get_param( 'provider' ) ?: 'auto' );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'seo:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Seo_Manager::update( 'post', $request->get_param( 'id' ), $params['fields'] ?? array(), '', $params['provider'] ?? 'auto' );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'seo:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/seo/term/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $res = PressAgent_Seo_Manager::get( 'term', $request->get_param( 'id' ), (string) $request->get_param( 'taxonomy' ), $request->get_param( 'provider' ) ?: 'auto' );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'seo:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Seo_Manager::update( 'term', $request->get_param( 'id' ), $params['fields'] ?? array(), (string) ( $params['taxonomy'] ?? $request->get_param( 'taxonomy' ) ), $params['provider'] ?? 'auto' );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'seo:write' ); }
            )
        ) );

        // Forms (CF7 full CRUD, WPForms read)
        register_rest_route( $namespace, '/forms', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Forms_Manager::list_forms( $request->get_param( 'provider' ) ?: 'auto' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'forms:read' ); }
        ) );

        register_rest_route( $namespace, '/forms/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Forms_Manager::get_form(
                    $request->get_param( 'id' ),
                    $request->get_param( 'provider' ) ?: 'auto',
                    null === $request->get_param( 'include_data' ) ? true : (bool) $request->get_param( 'include_data' )
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'forms:read' ); }
        ) );

        register_rest_route( $namespace, '/forms/cf7', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Forms_Manager::save_cf7( $params );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'forms:write' ); }
        ) );

        register_rest_route( $namespace, '/forms/cf7/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $params['id'] = $request->get_param( 'id' );
                $res = PressAgent_Forms_Manager::save_cf7( $params );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'forms:write' ); }
        ) );

        register_rest_route( $namespace, '/forms/cf7/(?P<id>\d+)/delete', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Forms_Manager::delete_cf7( $request->get_param( 'id' ), $params['confirm'] ?? false );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'forms:write' ); }
        ) );

        // WooCommerce products and orders
        register_rest_route( $namespace, '/commerce/products', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $res = PressAgent_Commerce_Manager::list_products( array(
                        'limit'  => $request->get_param( 'limit' ),
                        'page'   => $request->get_param( 'page' ),
                        'search' => $request->get_param( 'search' ),
                        'status' => $request->get_param( 'status' ),
                    ) );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Commerce_Manager::create_product( $params );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/commerce/products/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $res = PressAgent_Commerce_Manager::get_product( $request->get_param( 'id' ), (bool) $request->get_param( 'include_description' ) );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Commerce_Manager::update_product( $request->get_param( 'id' ), $params );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/commerce/orders', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Commerce_Manager::list_orders( array(
                    'limit'  => $request->get_param( 'limit' ),
                    'page'   => $request->get_param( 'page' ),
                    'status' => $request->get_param( 'status' ),
                ) );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:read' ); }
        ) );

        register_rest_route( $namespace, '/commerce/orders/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Commerce_Manager::get_order( $request->get_param( 'id' ) );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:read' ); }
        ) );

        register_rest_route( $namespace, '/commerce/orders/(?P<id>\d+)/status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Commerce_Manager::update_order_status(
                    $request->get_param( 'id' ),
                    $params['status'] ?? '',
                    $params['note'] ?? '',
                    $params['customer_note'] ?? false
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:write' ); }
        ) );

        register_rest_route( $namespace, '/commerce/orders/(?P<id>\d+)/note', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Commerce_Manager::add_order_note(
                    $request->get_param( 'id' ),
                    $params['note'] ?? '',
                    $params['customer_note'] ?? false
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'commerce:write' ); }
        ) );

        // Appearance / Child Theme
        register_rest_route( $namespace, '/theme/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Child_Theme_Manager::get_status() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:read' ); }
        ) );

        register_rest_route( $namespace, '/theme/files', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Child_Theme_Manager::list_files( (int) ( $request->get_param( 'limit' ) ?: 200 ) );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:read' ); }
        ) );

        register_rest_route( $namespace, '/theme/file', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $res = PressAgent_Child_Theme_Manager::read_file( (string) $request->get_param( 'path' ) );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_Child_Theme_Manager::write_file( $params['path'] ?? '', $params['content'] ?? '' );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/theme/create-child', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Child_Theme_Manager::create_child(
                    $params['parent_slug'] ?? '',
                    $params['slug'] ?? '',
                    $params['activate'] ?? true,
                    $params['overwrite'] ?? false
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
        ) );

        register_rest_route( $namespace, '/theme/switch', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Child_Theme_Manager::switch_theme_to( $params['slug'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
        ) );

        register_rest_route( $namespace, '/theme/mode', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Child_Theme_Manager::set_mode( $params['mode'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
        ) );

        register_rest_route( $namespace, '/theme/setup', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Child_Theme_Manager::setup( $params['mode'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
        ) );

        register_rest_route( $namespace, '/theme/backups', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Child_Theme_Manager::list_backups() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:read' ); }
        ) );

        register_rest_route( $namespace, '/theme/restore', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Child_Theme_Manager::restore_backup( $params['backup_id'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'theme:write' ); }
        ) );

        // Performance audit and safe fixes
        register_rest_route( $namespace, '/performance/audit', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function() { return rest_ensure_response( PressAgent_Performance_Manager::audit() ); },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
        ) );

        register_rest_route( $namespace, '/performance/page', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => function( $request ) {
                $res = PressAgent_Performance_Manager::page(
                    (int) ( $request->get_param( 'page_id' ) ?: 0 ),
                    (string) ( $request->get_param( 'url' ) ?: '' )
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
        ) );

        register_rest_route( $namespace, '/performance/fix', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Performance_Manager::fix(
                    $params['fix_id'] ?? '',
                    $params,
                    $params['confirm'] ?? false
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
        ) );

        // WordPress core settings
        register_rest_route( $namespace, '/wp-settings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => function( $request ) {
                    $groups = $request->get_param( 'groups' );
                    $groups = empty( $groups ) ? array() : array_filter( array_map( 'trim', explode( ',', (string) $groups ) ) );
                    return rest_ensure_response( PressAgent_WP_Settings_Manager::get( $groups ) );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:read' ); }
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => function( $request ) {
                    $params = $request->get_json_params();
                    $res = PressAgent_WP_Settings_Manager::set( $params['values'] ?? array(), $params['confirm'] ?? false );
                    return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
                },
                'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
            )
        ) );

        register_rest_route( $namespace, '/wp-settings/rollback', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_WP_Settings_Manager::rollback( $params['snapshot_id'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'settings:write' ); }
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

        // God Mode / Developer Code Execution
        register_rest_route( $namespace, '/code/create-plugin', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Code_Executor::create_plugin(
                    $params['name'] ?? '',
                    $params['code'] ?? '',
                    $params['description'] ?? '',
                    $params['activate'] ?? true
                );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'code:execute' ); }
        ) );

        register_rest_route( $namespace, '/code/delete-plugin', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Code_Executor::delete_plugin( $params['slug'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'code:execute' ); }
        ) );

        register_rest_route( $namespace, '/code/toggle-plugin', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Code_Executor::toggle_plugin( $params['slug'] ?? '', $params['action'] ?? 'activate' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'code:execute' ); }
        ) );

        register_rest_route( $namespace, '/code/run', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function( $request ) {
                $params = $request->get_json_params();
                $res = PressAgent_Code_Executor::execute_snippet( $params['code'] ?? '' );
                return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
            },
            'permission_callback' => function( $request ) { return PressAgent_Auth::has_scope( $request, 'code:execute' ); }
        ) );
    }
}
