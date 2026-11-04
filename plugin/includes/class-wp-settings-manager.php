<?php
/**
 * Validated WordPress core settings (general, reading, discussion, media,
 * permalinks, privacy) with snapshots and rollback.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_WP_Settings_Manager {

    const SNAPSHOT_OPTION = 'pressagent_core_setting_snapshots';
    const MAX_SNAPSHOTS   = 50;

    private static $groups = array(
        'general' => array(
            'blogname',
            'blogdescription',
            'siteurl',
            'home',
            'admin_email',
            'users_can_register',
            'default_role',
            'timezone_string',
            'gmt_offset',
            'date_format',
            'time_format',
            'start_of_week',
            'WPLANG',
            'blog_public',
        ),
        'reading' => array(
            'show_on_front',
            'page_on_front',
            'page_for_posts',
            'posts_per_page',
        ),
        'discussion' => array(
            'default_ping_status',
            'default_comment_status',
            'comment_registration',
            'close_comments_for_old_posts',
            'comment_max_links',
            'thread_comments',
            'page_comments',
            'comments_per_page',
        ),
        'media' => array(
            'thumbnail_size_w',
            'thumbnail_size_h',
            'medium_size_w',
            'medium_size_h',
            'large_size_w',
            'large_size_h',
            'thumbnail_crop',
            'uploads_use_yearmonth_folders',
        ),
        'permalinks' => array(
            'permalink_structure',
            'category_base',
            'tag_base',
        ),
        'privacy' => array(
            'wp_page_for_privacy_policy',
            'site_icon',
        ),
    );

    public static function get( $groups = array() ) {
        $groups = empty( $groups ) ? array_keys( self::$groups ) : array_values( array_intersect( $groups, array_keys( self::$groups ) ) );
        $out    = array();
        foreach ( $groups as $group ) {
            foreach ( self::$groups[ $group ] as $key ) {
                $out[ $group ][ $key ] = self::normalize_read( $key, get_option( $key ) );
            }
        }
        return $out;
    }

    public static function set( $values, $confirm = false ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to change site settings.', array( 'status' => 403 ) );
        }
        if ( ! is_array( $values ) || empty( $values ) ) {
            return new WP_Error( 'missing_values', 'Provide at least one settings group.', array( 'status' => 400 ) );
        }

        $changed       = array();
        $snapshot_ids  = array();
        $warnings      = array();
        $flush_rewrite = false;

        foreach ( $values as $group => $items ) {
            if ( ! isset( self::$groups[ $group ] ) ) {
                return new WP_Error( 'unknown_group', 'Unknown settings group: ' . $group . '. Allowed: ' . implode( ', ', array_keys( self::$groups ) ), array( 'status' => 400 ) );
            }
            if ( ! is_array( $items ) ) {
                return new WP_Error( 'invalid_group', 'Settings group "' . $group . '" must be an object.', array( 'status' => 400 ) );
            }

            foreach ( $items as $key => $value ) {
                if ( ! in_array( $key, self::$groups[ $group ], true ) ) {
                    return new WP_Error( 'unknown_option', 'Unknown option "' . $key . '" in group "' . $group . '". Allowed: ' . implode( ', ', self::$groups[ $group ] ), array( 'status' => 400 ) );
                }

                if ( in_array( $key, array( 'siteurl', 'home' ), true ) && true !== $confirm ) {
                    return new WP_Error( 'confirmation_required', 'Changing siteurl/home can break the site. Call again with confirm:true after user approval.', array( 'status' => 409 ) );
                }

                $cleanup = self::validate( $key, $value );
                if ( is_wp_error( $cleanup ) ) {
                    return $cleanup;
                }
                $value = $cleanup;

                $old = get_option( $key );
                if ( $old === $value ) {
                    continue;
                }

                $snapshot_id = self::snapshot( $key, $old );
                if ( is_wp_error( $snapshot_id ) ) {
                    return $snapshot_id;
                }
                $snapshot_ids[] = $snapshot_id;

                update_option( $key, $value );
                $changed[ $group ][ $key ] = array( 'old' => self::normalize_read( $key, $old ), 'new' => self::normalize_read( $key, $value ) );

                if ( in_array( $key, array( 'permalink_structure', 'category_base', 'tag_base', 'siteurl', 'home' ), true ) ) {
                    $flush_rewrite = true;
                }
                if ( in_array( $key, array( 'siteurl', 'home' ), true ) ) {
                    $warnings[] = $key . ' changed; verify login and frontend URLs immediately.';
                }
            }
        }

        if ( $flush_rewrite ) {
            flush_rewrite_rules( false );
        }
        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array(
            'success'      => true,
            'changed'      => $changed,
            'snapshot_ids' => $snapshot_ids,
            'warnings'     => $warnings,
            'message'      => empty( $changed ) ? 'No changes were needed.' : 'Site settings updated.',
        );
    }

    public static function rollback( $snapshot_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to restore site settings.', array( 'status' => 403 ) );
        }
        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        if ( ! is_array( $snapshots ) || empty( $snapshots[ $snapshot_id ] ) ) {
            return new WP_Error( 'snapshot_missing', 'Settings snapshot not found: ' . $snapshot_id, array( 'status' => 404 ) );
        }
        $snapshot = $snapshots[ $snapshot_id ];
        $key      = $snapshot['option'] ?? '';
        if ( ! self::is_allowed_key( $key ) ) {
            return new WP_Error( 'snapshot_invalid', 'This option is no longer supported by the snapshot restore.', array( 'status' => 409 ) );
        }

        update_option( $key, $snapshot['value'] );
        if ( in_array( $key, array( 'permalink_structure', 'category_base', 'tag_base' ), true ) ) {
            flush_rewrite_rules( false );
        }
        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array( 'success' => true, 'snapshot_id' => $snapshot_id, 'option' => $key, 'message' => 'Setting restored.' );
    }

    /* ---------------------------------------------------------------------
     * Validation and snapshots
     * ------------------------------------------------------------------- */

    private static function validate( $key, $value ) {
        $bool_options = array( 'users_can_register', 'blog_public', 'comment_registration', 'close_comments_for_old_posts', 'thread_comments', 'page_comments', 'thumbnail_crop', 'uploads_use_yearmonth_folders' );
        $int_options  = array( 'start_of_week', 'posts_per_page', 'comment_max_links', 'comments_per_page', 'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h', 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy', 'site_icon' );
        $page_options = array( 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy', 'site_icon' );

        if ( in_array( $key, $bool_options, true ) ) {
            return rest_sanitize_boolean( $value ) ? '1' : '0';
        }
        if ( in_array( $key, $int_options, true ) ) {
            $number = absint( $value );
            if ( 'start_of_week' === $key && $number > 6 ) {
                return new WP_Error( 'invalid_value', 'start_of_week must be 0-6.', array( 'status' => 400 ) );
            }
            if ( 'posts_per_page' === $key && ( $number < 1 || $number > 100 ) ) {
                return new WP_Error( 'invalid_value', 'posts_per_page must be 1-100.', array( 'status' => 400 ) );
            }
            if ( in_array( $key, array( 'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h' ), true ) && $number > 5000 ) {
                return new WP_Error( 'invalid_value', $key . ' is too large.', array( 'status' => 400 ) );
            }
            if ( $number > 0 && in_array( $key, $page_options, true ) ) {
                $post = get_post( $number );
                if ( ! $post ) {
                    return new WP_Error( 'invalid_value', $key . ' references a missing post/page: ' . $number, array( 'status' => 400 ) );
                }
                if ( 'site_icon' === $key && ! wp_attachment_is_image( $number ) ) {
                    return new WP_Error( 'invalid_value', 'site_icon must be an image attachment ID.', array( 'status' => 400 ) );
                }
                if ( in_array( $key, array( 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy' ), true ) && 'page' !== $post->post_type ) {
                    return new WP_Error( 'invalid_value', $key . ' must reference a page.', array( 'status' => 400 ) );
                }
            }
            return $number;
        }

        switch ( $key ) {
            case 'admin_email':
                return is_email( $value ) ? sanitize_email( $value ) : new WP_Error( 'invalid_email', 'admin_email is not valid.', array( 'status' => 400 ) );
            case 'default_role':
                $role = sanitize_key( $value );
                if ( ! function_exists( 'wp_roles' ) || ! wp_roles()->is_role( $role ) ) {
                    return new WP_Error( 'invalid_role', 'Unknown role: ' . $role, array( 'status' => 400 ) );
                }
                return $role;
            case 'timezone_string':
                $value = sanitize_text_field( $value );
                if ( '' !== $value && ! in_array( $value, timezone_identifiers_list(), true ) ) {
                    return new WP_Error( 'invalid_timezone', 'Invalid timezone identifier.', array( 'status' => 400 ) );
                }
                return $value;
            case 'gmt_offset':
                $offset = (float) $value;
                if ( $offset < -12 || $offset > 14 ) {
                    return new WP_Error( 'invalid_offset', 'gmt_offset must be between -12 and 14.', array( 'status' => 400 ) );
                }
                return $offset;
            case 'show_on_front':
                return in_array( $value, array( 'posts', 'page' ), true ) ? $value : new WP_Error( 'invalid_value', 'show_on_front must be posts or page.', array( 'status' => 400 ) );
            case 'default_comment_status':
            case 'default_ping_status':
                return in_array( $value, array( 'open', 'closed' ), true ) ? $value : 'closed';
            case 'permalink_structure':
                return sanitize_text_field( (string) $value );
            case 'category_base':
            case 'tag_base':
                return sanitize_title( (string) $value );
            case 'siteurl':
            case 'home':
                $url = esc_url_raw( (string) $value );
                $parts = wp_parse_url( $url );
                if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
                    return new WP_Error( 'invalid_url', $key . ' must be an absolute http(s) URL.', array( 'status' => 400 ) );
                }
                return untrailingslashit( $url );
            case 'WPLANG':
                return sanitize_text_field( (string) $value );
            default:
                return sanitize_text_field( (string) $value );
        }
    }

    private static function normalize_read( $key, $value ) {
        $bool_options = array( 'users_can_register', 'blog_public', 'comment_registration', 'close_comments_for_old_posts', 'thread_comments', 'page_comments', 'thumbnail_crop', 'uploads_use_yearmonth_folders' );
        if ( in_array( $key, $bool_options, true ) ) {
            return rest_sanitize_boolean( $value );
        }
        if ( in_array( $key, array( 'gmt_offset' ), true ) ) {
            return (float) $value;
        }
        if ( in_array( $key, array( 'posts_per_page', 'start_of_week', 'comment_max_links', 'comments_per_page', 'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h', 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy', 'site_icon' ), true ) ) {
            return (int) $value;
        }
        return $value;
    }

    private static function is_allowed_key( $key ) {
        foreach ( self::$groups as $keys ) {
            if ( in_array( $key, $keys, true ) ) {
                return true;
            }
        }
        return false;
    }

    private static function snapshot( $key, $value ) {
        $snapshots = get_option( self::SNAPSHOT_OPTION, array() );
        if ( ! is_array( $snapshots ) ) {
            $snapshots = array();
        }
        $id = 'wp_' . time() . '_' . wp_generate_password( 6, false, false );
        $snapshots[ $id ] = array(
            'option'     => $key,
            'value'      => $value,
            'created_at' => current_time( 'mysql' ),
        );
        if ( count( $snapshots ) > self::MAX_SNAPSHOTS ) {
            $snapshots = array_slice( $snapshots, -self::MAX_SNAPSHOTS, null, true );
        }
        update_option( self::SNAPSHOT_OPTION, $snapshots, false );
        return $id;
    }
}
