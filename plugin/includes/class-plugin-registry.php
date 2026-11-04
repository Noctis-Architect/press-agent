<?php
/**
 * Validated plugin settings and action registry.
 *
 * Each supported plugin declares the exact options/fields it exposes. Agents can
 * only read or write declared settings, which replaces broad option wildcards.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Plugin_Registry {

    const SNAPSHOT_OPTION = 'pressagent_plugin_setting_snapshots';
    const MAX_SNAPSHOTS   = 50;

    /**
     * Full registry data.
     *
     * @return array
     */
    public static function all() {
        static $data = null;
        if ( null === $data ) {
            $file = __DIR__ . '/plugin-registry-data.php';
            $data = file_exists( $file ) ? require $file : array();
            if ( ! is_array( $data ) ) {
                $data = array();
            }
        }
        return $data;
    }

    /**
     * Public catalog for a single plugin (safe to send to agents).
     *
     * @param string $slug Plugin slug.
     * @return array|WP_Error
     */
    public static function get_catalog( $slug ) {
        $plugin = self::get_plugin( $slug );
        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }

        $settings = array();
        foreach ( $plugin['settings'] ?? array() as $id => $spec ) {
            $settings[] = self::public_setting_spec( $id, $spec );
        }

        $actions = array();
        foreach ( $plugin['actions'] ?? array() as $name => $spec ) {
            $actions[] = array(
                'name'   => $name,
                'label'  => $spec['label'] ?? $name,
                'safety' => $spec['safety'] ?? 'write',
                'params' => self::public_params( $spec['params'] ?? array() ),
            );
        }

        return array(
            'slug'     => $slug,
            'name'     => $plugin['name'] ?? $slug,
            'active'   => self::is_active( $slug ),
            'version'  => self::detect_version( $slug ),
            'settings' => $settings,
            'actions'  => $actions,
            'seo'      => ! empty( $plugin['seo'] ) ? array( 'provider' => $plugin['seo']['provider'] ?? '' ) : null,
            'notes'    => $plugin['notes'] ?? array(),
        );
    }

    /**
     * Read declared settings for a plugin.
     *
     * @param string   $slug   Plugin slug.
     * @param string[] $ids    Setting ids; empty means all.
     * @param string[] $fields Optional nested field filter.
     * @return array|WP_Error
     */
    public static function read_settings( $slug, $ids = array(), $fields = array() ) {
        $plugin = self::get_plugin( $slug );
        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }

        $requested = empty( $ids ) ? array_keys( $plugin['settings'] ?? array() ) : $ids;
        $result    = array();

        foreach ( $requested as $id ) {
            if ( ! isset( $plugin['settings'][ $id ] ) ) {
                return new WP_Error(
                    'unknown_setting',
                    'Unknown setting "' . $id . '". Available: ' . implode( ', ', array_keys( $plugin['settings'] ?? array() ) ),
                    array( 'status' => 400 )
                );
            }
            $spec  = $plugin['settings'][ $id ];
            $value = self::read_storage( $spec );
            if ( is_array( $value ) ) {
                $allowed_fields = array_keys( $spec['fields'] ?? array() );
                if ( ! empty( $allowed_fields ) ) {
                    // Keep reads compact and prevent reading undeclared secrets.
                    $requested_fields = ! empty( $fields ) ? array_values( array_intersect( $fields, $allowed_fields ) ) : $allowed_fields;
                    $value = self::filter_fields( $value, $requested_fields );
                } elseif ( ! empty( $fields ) ) {
                    $value = self::filter_fields( $value, $fields );
                }
            }
            $result[ $id ] = $value;
        }

        return array(
            'slug'     => $slug,
            'active'   => self::is_active( $slug ),
            'settings' => $result,
        );
    }

    /**
     * Write declared settings. Values are partial for array settings and are
     * deep-merged into the current value.
     *
     * @param string $slug   Plugin slug.
     * @param array  $values Map of setting id => partial value.
     * @return array|WP_Error
     */
    public static function write_settings( $slug, $values ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to change plugin settings.', array( 'status' => 403 ) );
        }

        $plugin = self::get_plugin( $slug );
        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }
        if ( ! is_array( $values ) || empty( $values ) ) {
            return new WP_Error( 'invalid_values', 'Provide an object of setting_id => value.', array( 'status' => 400 ) );
        }

        $updated      = array();
        $snapshot_ids = array();

        foreach ( $values as $id => $partial ) {
            if ( ! isset( $plugin['settings'][ $id ] ) ) {
                return new WP_Error(
                    'unknown_setting',
                    'Unknown setting "' . $id . '". Available: ' . implode( ', ', array_keys( $plugin['settings'] ?? array() ) ),
                    array( 'status' => 400 )
                );
            }

            $spec    = $plugin['settings'][ $id ];
            $current = self::read_storage( $spec );
            $type    = $spec['type'] ?? 'array';

            if ( 'array' === $type ) {
                if ( ! is_array( $partial ) ) {
                    return new WP_Error( 'invalid_value', 'Setting "' . $id . '" expects an object/array value.', array( 'status' => 400 ) );
                }
                $candidate = self::validate_partial( $partial, $spec, $id );
                if ( is_wp_error( $candidate ) ) {
                    return $candidate;
                }
                $new_value = ( isset( $spec['merge'] ) && 'replace' === $spec['merge'] )
                    ? $candidate
                    : self::deep_merge( is_array( $current ) ? $current : array(), $candidate );
            } else {
                if ( is_array( $partial ) ) {
                    return new WP_Error( 'invalid_value', 'Setting "' . $id . '" expects a scalar value of type ' . $type . '.', array( 'status' => 400 ) );
                }
                $new_value = self::sanitize_value( $partial, $type );
            }

            $snapshot_id = self::snapshot( $slug, $id, $current );
            if ( is_wp_error( $snapshot_id ) ) {
                return $snapshot_id;
            }
            $snapshot_ids[] = $snapshot_id;

            $written = self::write_storage( $spec, $new_value );
            if ( is_wp_error( $written ) ) {
                return $written;
            }

            $updated[ $id ] = array(
                'changed' => $current !== $new_value,
                'snapshot_id' => $snapshot_id,
            );
        }

        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array(
            'success'      => true,
            'slug'         => $slug,
            'updated'      => $updated,
            'snapshot_ids' => $snapshot_ids,
            'message'      => 'Settings updated. Use action "restore_setting" with a snapshot_id to roll back.',
        );
    }

    /**
     * Restore a settings snapshot created by write_settings.
     *
     * @param string $snapshot_id Snapshot id.
     * @return array|WP_Error
     */
    public static function restore_setting( $snapshot_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to restore plugin settings.', array( 'status' => 403 ) );
        }

        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        if ( ! is_array( $snapshots ) || ! isset( $snapshots[ $snapshot_id ] ) ) {
            return new WP_Error( 'snapshot_missing', 'Settings snapshot not found: ' . $snapshot_id, array( 'status' => 404 ) );
        }

        $snapshot = $snapshots[ $snapshot_id ];
        $plugin   = self::get_plugin( $snapshot['plugin'] ?? '' );
        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }
        $id = $snapshot['setting'] ?? '';
        if ( ! isset( $plugin['settings'][ $id ] ) ) {
            return new WP_Error( 'snapshot_invalid', 'The setting referenced by this snapshot is no longer supported.', array( 'status' => 409 ) );
        }

        $written = self::write_storage( $plugin['settings'][ $id ], $snapshot['value'] );
        if ( is_wp_error( $written ) ) {
            return $written;
        }

        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array(
            'success'     => true,
            'snapshot_id' => $snapshot_id,
            'plugin'      => $snapshot['plugin'],
            'setting'     => $id,
            'message'     => 'Setting restored.',
        );
    }

    /**
     * Run a declared plugin action.
     *
     * @param string $slug    Plugin slug.
     * @param string $action  Action name.
     * @param array  $params  Action parameters.
     * @param bool   $confirm Confirmation for destructive actions.
     * @return array|WP_Error
     */
    public static function run_action( $slug, $action, $params = array(), $confirm = false ) {
        $plugin = self::get_plugin( $slug );
        if ( is_wp_error( $plugin ) ) {
            return $plugin;
        }
        if ( ! isset( $plugin['actions'][ $action ] ) ) {
            return new WP_Error(
                'unknown_action',
                'Unknown action "' . $action . '". Available: ' . implode( ', ', array_keys( $plugin['actions'] ?? array() ) ),
                array( 'status' => 400 )
            );
        }

        $spec = $plugin['actions'][ $action ];
        $safety = $spec['safety'] ?? 'write';
        if ( 'destructive' === $safety && true !== $confirm ) {
            return new WP_Error( 'confirmation_required', 'This action is destructive. Call again with confirm:true after user approval.', array( 'status' => 409 ) );
        }

        $validated = self::validate_params( $params, $spec['params'] ?? array() );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $handler = $spec['handler'] ?? null;
        if ( ! is_callable( $handler ) ) {
            return new WP_Error( 'action_unavailable', 'Action handler is not implemented for: ' . $slug . '/' . $action, array( 'status' => 501 ) );
        }

        $result = call_user_func( $handler, $validated, $spec );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( class_exists( 'PressAgent_Generic_Settings' ) && ! empty( $spec['flush_cache'] ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array(
            'success' => true,
            'slug'    => $slug,
            'action'  => $action,
            'result'  => $result,
        );
    }

    /**
     * Plugin registry entry or error.
     *
     * @param string $slug Plugin slug.
     * @return array|WP_Error
     */
    public static function get_plugin( $slug ) {
        $all = self::all();
        if ( ! isset( $all[ $slug ] ) ) {
            return new WP_Error(
                'plugin_not_supported',
                'Plugin "' . $slug . '" is not in the PressAgent registry. Supported: ' . implode( ', ', array_keys( $all ) ),
                array( 'status' => 404 )
            );
        }
        return $all[ $slug ];
    }

    /**
     * Check whether the plugin is installed and active.
     *
     * @param string $slug Plugin slug.
     * @return bool
     */
    public static function is_active( $slug ) {
        $plugin = self::all()[ $slug ] ?? null;
        if ( ! $plugin ) {
            return false;
        }

        $file = $plugin['file'] ?? '';
        if ( $file && ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( $file && function_exists( 'is_plugin_active' ) && is_plugin_active( $file ) ) {
            return true;
        }
        if ( is_multisite() && $file && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) ) {
            return true;
        }

        foreach ( (array) ( $plugin['detect'] ?? array() ) as $probe ) {
            if ( self::detect_probe( $probe ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect the installed plugin version when possible.
     *
     * @param string $slug Plugin slug.
     * @return string|null
     */
    public static function detect_version( $slug ) {
        $plugin = self::all()[ $slug ] ?? null;
        if ( ! $plugin ) {
            return null;
        }
        if ( ! empty( $plugin['version_constant'] ) && defined( $plugin['version_constant'] ) ) {
            return (string) constant( $plugin['version_constant'] );
        }
        if ( ! empty( $plugin['file'] ) ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $abs = WP_PLUGIN_DIR . '/' . $plugin['file'];
            if ( file_exists( $abs ) ) {
                $data = get_plugin_data( $abs, false, false );
                if ( ! empty( $data['Version'] ) ) {
                    return $data['Version'];
                }
            }
        }
        return null;
    }

    /* ---------------------------------------------------------------------
     * Storage and validation internals
     * ------------------------------------------------------------------- */

    private static function read_storage( $spec ) {
        if ( isset( $spec['get_callback'] ) && is_callable( $spec['get_callback'] ) ) {
            return call_user_func( $spec['get_callback'] );
        }
        $option = $spec['option'] ?? '';
        return '' !== $option ? get_option( $option ) : null;
    }

    private static function write_storage( $spec, $value ) {
        if ( isset( $spec['set_callback'] ) && is_callable( $spec['set_callback'] ) ) {
            $result = call_user_func( $spec['set_callback'], $value );
            return false === $result ? new WP_Error( 'write_failed', 'Plugin storage rejected the update.', array( 'status' => 500 ) ) : true;
        }
        $option = $spec['option'] ?? '';
        if ( '' === $option ) {
            return new WP_Error( 'invalid_setting', 'Setting has no storage definition.', array( 'status' => 500 ) );
        }

        if ( class_exists( 'PressAgent_Action_Guard' ) ) {
            PressAgent_Action_Guard::create_snapshot( 'update_option', array( 'option_name' => $option ) );
        }
        update_option( $option, $value );
        return true;
    }

    private static function validate_partial( $partial, $spec, $setting_id ) {
        if ( ! is_array( $partial ) ) {
            return $partial;
        }
        $allowed = $spec['fields'] ?? array();
        $strict  = array_key_exists( 'strict_fields', $spec ) ? (bool) $spec['strict_fields'] : true;
        $out     = array();

        foreach ( $partial as $key => $value ) {
            if ( $strict && ! empty( $allowed ) && ! isset( $allowed[ $key ] ) ) {
                return new WP_Error(
                    'unknown_field',
                    'Unknown field "' . $key . '" in setting "' . $setting_id . '". Allowed: ' . implode( ', ', array_keys( $allowed ) ),
                    array( 'status' => 400 )
                );
            }
            $type = isset( $allowed[ $key ]['type'] ) ? $allowed[ $key ]['type'] : 'string';
            if ( is_array( $value ) && 'array' !== $type ) {
                foreach ( $value as $child_key => $child_value ) {
                    $out[ $key ][ $child_key ] = self::sanitize_value( $child_value, $type );
                }
                continue;
            }
            $out[ $key ] = self::sanitize_value( $value, $type );
        }
        return $out;
    }

    private static function sanitize_value( $value, $type ) {
        switch ( $type ) {
            case 'bool':
            case 'boolean':
                return rest_sanitize_boolean( $value );
            case 'int':
            case 'integer':
                return absint( $value );
            case 'url':
                return esc_url_raw( (string) $value );
            case 'email':
                return sanitize_email( (string) $value );
            case 'html':
                return wp_kses_post( (string) $value );
            case 'textarea':
                return sanitize_textarea_field( (string) $value );
            case 'array':
                if ( ! is_array( $value ) ) {
                    return array();
                }
                return array_map( array( __CLASS__, 'sanitize_mixed' ), $value );
            case 'string':
            default:
                if ( is_array( $value ) ) {
                    return array_map( array( __CLASS__, 'sanitize_mixed' ), $value );
                }
                if ( is_bool( $value ) ) {
                    return $value;
                }
                return sanitize_text_field( (string) $value );
        }
    }

    public static function sanitize_mixed( $value ) {
        if ( is_array( $value ) ) {
            return array_map( array( __CLASS__, 'sanitize_mixed' ), $value );
        }
        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
            return $value;
        }
        return sanitize_text_field( (string) $value );
    }

    private static function deep_merge( $current, $partial ) {
        foreach ( $partial as $key => $value ) {
            if ( is_array( $value ) && isset( $current[ $key ] ) && is_array( $current[ $key ] ) ) {
                $current[ $key ] = self::deep_merge( $current[ $key ], $value );
            } else {
                $current[ $key ] = $value;
            }
        }
        return $current;
    }

    private static function filter_fields( $value, $fields ) {
        $out = array();
        foreach ( $fields as $field ) {
            if ( array_key_exists( $field, $value ) ) {
                $out[ $field ] = $value[ $field ];
            }
        }
        return $out;
    }

    private static function snapshot( $slug, $setting_id, $value ) {
        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        if ( ! is_array( $snapshots ) ) {
            $snapshots = array();
        }

        $id = 'set_' . time() . '_' . wp_generate_password( 6, false, false );
        $snapshots[ $id ] = array(
            'plugin'     => $slug,
            'setting'    => $setting_id,
            'value'      => $value,
            'created_at' => current_time( 'mysql' ),
        );

        if ( count( $snapshots ) > self::MAX_SNAPSHOTS ) {
            $snapshots = array_slice( $snapshots, -self::MAX_SNAPSHOTS, null, true );
        }

        update_option( self::SNAPSHOT_OPTION, $snapshots, false );
        return $id;
    }

    private static function validate_params( $params, $schema ) {
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'invalid_params', 'Action params must be an object.', array( 'status' => 400 ) );
        }
        foreach ( $schema as $name => $spec ) {
            $required = ! empty( $spec['required'] );
            if ( $required && ! array_key_exists( $name, $params ) ) {
                return new WP_Error( 'missing_param', 'Missing required parameter: ' . $name, array( 'status' => 400 ) );
            }
            if ( array_key_exists( $name, $params ) ) {
                $params[ $name ] = self::sanitize_value( $params[ $name ], $spec['type'] ?? 'string' );
            }
        }
        return $params;
    }

    private static function public_setting_spec( $id, $spec ) {
        $public = array(
            'id'    => $id,
            'label' => $spec['label'] ?? $id,
            'type'  => $spec['type'] ?? 'array',
        );
        if ( ! empty( $spec['fields'] ) ) {
            $public['fields'] = array();
            foreach ( $spec['fields'] as $field => $field_spec ) {
                $public['fields'][ $field ] = array(
                    'type'  => $field_spec['type'] ?? 'string',
                    'label' => $field_spec['label'] ?? $field,
                );
                if ( ! empty( $field_spec['notes'] ) ) {
                    $public['fields'][ $field ]['notes'] = $field_spec['notes'];
                }
            }
        }
        if ( isset( $spec['strict_fields'] ) ) {
            $public['strict_fields'] = (bool) $spec['strict_fields'];
        }
        return $public;
    }

    private static function public_params( $params ) {
        $out = array();
        foreach ( $params as $name => $spec ) {
            $out[ $name ] = array(
                'type'     => $spec['type'] ?? 'string',
                'required' => ! empty( $spec['required'] ),
            );
        }
        return $out;
    }

    private static function detect_probe( $probe ) {
        if ( ! is_string( $probe ) || false === strpos( $probe, ':' ) ) {
            return false;
        }
        list( $type, $name ) = explode( ':', $probe, 2 );
        switch ( $type ) {
            case 'defined':
                return defined( $name );
            case 'class':
                return class_exists( $name );
            case 'function':
                return function_exists( $name );
        }
        return false;
    }
}
