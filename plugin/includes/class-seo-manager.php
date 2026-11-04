<?php
/**
 * Provider-aware SEO metadata for Yoast SEO and Rank Math.
 *
 * Verified against wordpress-seo 28.8 and seo-by-rank-math 1.0.281.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Seo_Manager {

    const FIELDS = array(
        'title',
        'description',
        'focus_keyword',
        'canonical',
        'noindex',
        'nofollow',
        'advanced_robots',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'primary_category',
        'schema_page_type',
        'schema_article_type',
        'cornerstone',
    );

    public static function providers() {
        $providers = array();
        if ( defined( 'WPSEO_VERSION' ) ) {
            $providers['yoast'] = array(
                'label'   => 'Yoast SEO',
                'version' => WPSEO_VERSION,
                'term_storage' => 'option:wpseo_taxonomy_meta',
            );
        }
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            $providers['rankmath'] = array(
                'label'   => 'Rank Math SEO',
                'version' => RANK_MATH_VERSION,
                'term_storage' => 'term_meta',
            );
        }
        return $providers;
    }

    public static function get( $object_type, $object_id, $taxonomy = '', $provider = 'auto' ) {
        $resolved = self::resolve_provider( $provider );
        if ( is_wp_error( $resolved ) ) {
            return $resolved;
        }
        $provider = $resolved;

        $object = self::validate_object( $object_type, $object_id, $taxonomy );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        $fields = 'yoast' === $provider
            ? self::read_yoast( $object_type, $object_id, $taxonomy )
            : self::read_rankmath( $object_type, $object_id, $taxonomy );

        return array(
            'provider'    => $provider,
            'providers'   => array_keys( self::providers() ),
            'object_type' => $object_type,
            'object_id'   => (int) $object_id,
            'taxonomy'    => 'term' === $object_type ? $taxonomy : null,
            'fields'      => $fields,
        );
    }

    public static function update( $object_type, $object_id, $fields, $taxonomy = '', $provider = 'auto' ) {
        if ( 'post' === $object_type && ! current_user_can( 'edit_post', (int) $object_id ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit this post.', array( 'status' => 403 ) );
        }
        if ( 'term' === $object_type && ! current_user_can( 'edit_term', (int) $object_id ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit this term.', array( 'status' => 403 ) );
        }

        $resolved = self::resolve_provider( $provider );
        if ( is_wp_error( $resolved ) ) {
            return $resolved;
        }
        $provider = $resolved;

        $object = self::validate_object( $object_type, $object_id, $taxonomy );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        if ( ! is_array( $fields ) || empty( $fields ) ) {
            return new WP_Error( 'missing_fields', 'Provide a fields object with at least one SEO field.', array( 'status' => 400 ) );
        }

        $unknown = array_diff( array_keys( $fields ), self::FIELDS );
        if ( ! empty( $unknown ) ) {
            return new WP_Error( 'unknown_field', 'Unknown SEO fields: ' . implode( ', ', $unknown ) . '. Allowed: ' . implode( ', ', self::FIELDS ), array( 'status' => 400 ) );
        }

        $result = 'yoast' === $provider
            ? self::write_yoast( $object_type, $object_id, $fields, $taxonomy )
            : self::write_rankmath( $object_type, $object_id, $fields, $taxonomy );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }

        return array_merge(
            array(
                'success'     => true,
                'provider'    => $provider,
                'object_type' => $object_type,
                'object_id'   => (int) $object_id,
                'taxonomy'    => 'term' === $object_type ? $taxonomy : null,
            ),
            $result
        );
    }

    /* ---------------------------------------------------------------------
     * Yoast
     * ------------------------------------------------------------------- */

    private static function read_yoast( $object_type, $object_id, $taxonomy ) {
        $out = array();
        if ( 'term' === $object_type ) {
            $raw = class_exists( 'WPSEO_Taxonomy_Meta' )
                ? WPSEO_Taxonomy_Meta::get_term_meta( (int) $object_id, $taxonomy )
                : get_option( 'wpseo_taxonomy_meta', array() );
            if ( class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
                $out['title']            = self::str_or_null( $raw['wpseo_title'] ?? '' );
                $out['description']      = self::str_or_null( $raw['wpseo_desc'] ?? '' );
                $out['focus_keyword']    = self::str_or_null( $raw['wpseo_focuskw'] ?? '' );
                $out['canonical']        = self::str_or_null( $raw['wpseo_canonical'] ?? '' );
                $out['noindex']          = self::yoast_term_noindex_read( $raw['wpseo_noindex'] ?? 'default' );
                $out['og_title']         = self::str_or_null( $raw['wpseo_opengraph-title'] ?? '' );
                $out['og_description']   = self::str_or_null( $raw['wpseo_opengraph-description'] ?? '' );
                $out['og_image']         = self::str_or_null( $raw['wpseo_opengraph-image'] ?? '' );
                $out['twitter_title']    = self::str_or_null( $raw['wpseo_twitter-title'] ?? '' );
                $out['twitter_description'] = self::str_or_null( $raw['wpseo_twitter-description'] ?? '' );
                $out['twitter_image']    = self::str_or_null( $raw['wpseo_twitter-image'] ?? '' );
            }
            return array_filter( $out, static function ( $value ) {
                return null !== $value;
            } );
        }

        $out['title']            = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_title', true ) );
        $out['description']      = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_metadesc', true ) );
        $out['focus_keyword']    = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_focuskw', true ) );
        $out['canonical']        = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_canonical', true ) );
        $out['noindex']          = self::yoast_post_noindex_read( get_post_meta( $object_id, '_yoast_wpseo_meta-robots-noindex', true ) );
        $out['nofollow']         = '1' === (string) get_post_meta( $object_id, '_yoast_wpseo_meta-robots-nofollow', true );
        $out['advanced_robots']  = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_meta-robots-adv', true ) );
        $out['og_title']         = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_opengraph-title', true ) );
        $out['og_description']   = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_opengraph-description', true ) );
        $out['og_image']         = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_opengraph-image', true ) );
        $out['twitter_title']    = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_twitter-title', true ) );
        $out['twitter_description'] = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_twitter-description', true ) );
        $out['twitter_image']    = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_twitter-image', true ) );
        $out['primary_category'] = (int) get_post_meta( $object_id, '_yoast_wpseo_primary_category', true ) ?: null;
        $out['schema_page_type'] = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_schema_page_type', true ) );
        $out['schema_article_type'] = self::str_or_null( get_post_meta( $object_id, '_yoast_wpseo_schema_article_type', true ) );
        $out['cornerstone']      = '1' === (string) get_post_meta( $object_id, '_yoast_wpseo_is_cornerstone', true );

        return array_filter( $out, static function ( $value ) {
            return null !== $value;
        } );
    }

    private static function write_yoast( $object_type, $object_id, $fields, $taxonomy ) {
        $warnings = array();
        $indexable_rebuilt = false;

        if ( 'term' === $object_type ) {
            if ( ! class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
                return new WP_Error( 'yoast_api_missing', 'Yoast taxonomy API is unavailable.', array( 'status' => 500 ) );
            }
            $map = array(
                'title'              => 'wpseo_title',
                'description'        => 'wpseo_desc',
                'focus_keyword'      => 'wpseo_focuskw',
                'canonical'          => 'wpseo_canonical',
                'og_title'           => 'wpseo_opengraph-title',
                'og_description'     => 'wpseo_opengraph-description',
                'og_image'           => 'wpseo_opengraph-image',
                'twitter_title'      => 'wpseo_twitter-title',
                'twitter_description'=> 'wpseo_twitter-description',
                'twitter_image'      => 'wpseo_twitter-image',
            );
            foreach ( $fields as $field => $value ) {
                if ( 'noindex' === $field ) {
                    WPSEO_Taxonomy_Meta::set_value( $object_id, $taxonomy, 'wpseo_noindex', self::yoast_term_noindex_write( $value ) );
                    continue;
                }
                if ( ! isset( $map[ $field ] ) ) {
                    $warnings[] = 'Field "' . $field . '" is not supported for Yoast terms.';
                    continue;
                }
                WPSEO_Taxonomy_Meta::set_value( $object_id, $taxonomy, $map[ $field ], self::sanitize_for_field( $field, $value ) );
            }
            $warnings[] = 'Term indexables may be stale until Yoast rebuilds them.';
            return array( 'warnings' => $warnings, 'indexable_rebuilt' => false );
        }

        // Prefer Yoast's ability (WP 6.9+) because it also rebuilds the indexable.
        $ability_fields = array();
        $ability_map = array(
            'title'                 => 'seo_title',
            'description'           => 'meta_description',
            'canonical'             => 'canonical',
            'noindex'               => 'noindex',
            'nofollow'              => 'nofollow',
            'og_title'              => 'open_graph_title',
            'og_description'        => 'open_graph_description',
            'twitter_title'         => 'twitter_title',
            'twitter_description'   => 'twitter_description',
            'schema_page_type'      => 'schema_page_type',
            'schema_article_type'   => 'schema_article_type',
        );
        foreach ( $fields as $field => $value ) {
            if ( ! isset( $ability_map[ $field ] ) ) {
                continue;
            }
            $clean = self::sanitize_for_field( $field, $value );
            if ( 'nofollow' === $field && null === $clean ) {
                $clean = false;
            }
            $ability_fields[ $ability_map[ $field ] ] = $clean;
        }

        if ( ! empty( $ability_fields ) && function_exists( 'wp_get_ability' ) ) {
            $ability = wp_get_ability( 'yoast-seo/update-post-seo-data' );
            if ( $ability && method_exists( $ability, 'execute' ) ) {
                $ability_fields['post_id'] = (int) $object_id;
                try {
                    $result = $ability->execute( $ability_fields );
                    if ( ! is_wp_error( $result ) ) {
                        $indexable_rebuilt = true;
                    } else {
                        $warnings[] = 'Yoast ability failed: ' . $result->get_error_message();
                    }
                } catch ( \Throwable $e ) {
                    $warnings[] = 'Yoast ability threw an exception: ' . $e->getMessage();
                }
            }
        }

        $direct_map = array(
            'title'                 => '_yoast_wpseo_title',
            'description'           => '_yoast_wpseo_metadesc',
            'focus_keyword'         => '_yoast_wpseo_focuskw',
            'canonical'             => '_yoast_wpseo_canonical',
            'og_title'              => '_yoast_wpseo_opengraph-title',
            'og_description'        => '_yoast_wpseo_opengraph-description',
            'og_image'              => '_yoast_wpseo_opengraph-image',
            'twitter_title'         => '_yoast_wpseo_twitter-title',
            'twitter_description'   => '_yoast_wpseo_twitter-description',
            'twitter_image'         => '_yoast_wpseo_twitter-image',
            'primary_category'      => '_yoast_wpseo_primary_category',
            'schema_page_type'      => '_yoast_wpseo_schema_page_type',
            'schema_article_type'   => '_yoast_wpseo_schema_article_type',
        );

        foreach ( $fields as $field => $value ) {
            if ( $indexable_rebuilt && isset( $ability_map[ $field ] ) ) {
                continue;
            }
            if ( 'noindex' === $field || 'nofollow' === $field ) {
                $meta_key = 'noindex' === $field ? '_yoast_wpseo_meta-robots-noindex' : '_yoast_wpseo_meta-robots-nofollow';
                $stored   = self::yoast_post_robots_write( $field, $value );
                update_post_meta( $object_id, $meta_key, $stored );
                continue;
            }
            if ( 'advanced_robots' === $field ) {
                update_post_meta( $object_id, '_yoast_wpseo_meta-robots-adv', self::sanitize_advanced_robots( $value ) );
                continue;
            }
            if ( 'cornerstone' === $field ) {
                update_post_meta( $object_id, '_yoast_wpseo_is_cornerstone', $value ? '1' : '0' );
                continue;
            }
            if ( ! isset( $direct_map[ $field ] ) ) {
                $warnings[] = 'Field "' . $field . '" is not supported for Yoast posts.';
                continue;
            }
            $clean = self::sanitize_for_field( $field, $value );
            if ( null === $clean ) {
                delete_post_meta( $object_id, $direct_map[ $field ] );
            } else {
                update_post_meta( $object_id, $direct_map[ $field ], $clean );
            }
        }

        if ( ! $indexable_rebuilt ) {
            $warnings[] = 'Yoast indexables may be stale because the WP 6.9+ ability was unavailable.';
        }

        return array( 'warnings' => $warnings, 'indexable_rebuilt' => $indexable_rebuilt );
    }

    /* ---------------------------------------------------------------------
     * Rank Math
     * ------------------------------------------------------------------- */

    private static function read_rankmath( $object_type, $object_id, $taxonomy ) {
        $get = static function ( $key ) use ( $object_type, $object_id ) {
            return 'term' === $object_type
                ? get_term_meta( $object_id, $key, true )
                : get_post_meta( $object_id, $key, true );
        };

        $robots = (array) $get( 'rank_math_robots' );
        $out = array(
            'title'                 => self::str_or_null( $get( 'rank_math_title' ) ),
            'description'           => self::str_or_null( $get( 'rank_math_description' ) ),
            'focus_keyword'         => self::str_or_null( $get( 'rank_math_focus_keyword' ) ),
            'canonical'             => self::str_or_null( $get( 'rank_math_canonical_url' ) ),
            'noindex'               => in_array( 'noindex', $robots, true ),
            'nofollow'              => in_array( 'nofollow', $robots, true ),
            'advanced_robots'       => ! empty( $get( 'rank_math_advanced_robots' ) ) ? $get( 'rank_math_advanced_robots' ) : null,
            'og_title'              => self::str_or_null( $get( 'rank_math_facebook_title' ) ),
            'og_description'        => self::str_or_null( $get( 'rank_math_facebook_description' ) ),
            'og_image'              => self::str_or_null( $get( 'rank_math_facebook_image' ) ),
            'twitter_title'         => self::str_or_null( $get( 'rank_math_twitter_title' ) ),
            'twitter_description'    => self::str_or_null( $get( 'rank_math_twitter_description' ) ),
            'twitter_image'         => self::str_or_null( $get( 'rank_math_twitter_image' ) ),
            'primary_category'      => (int) $get( 'rank_math_primary_category' ) ?: null,
        );

        return array_filter( $out, static function ( $value ) {
            return null !== $value;
        } );
    }

    private static function write_rankmath( $object_type, $object_id, $fields, $taxonomy ) {
        $update = static function ( $key, $value ) use ( $object_type, $object_id ) {
            return 'term' === $object_type
                ? update_term_meta( $object_id, $key, $value )
                : update_post_meta( $object_id, $key, $value );
        };
        $delete = static function ( $key ) use ( $object_type, $object_id ) {
            return 'term' === $object_type
                ? delete_term_meta( $object_id, $key )
                : delete_post_meta( $object_id, $key );
        };

        $warnings = array();
        foreach ( $fields as $field => $value ) {
            switch ( $field ) {
                case 'title':
                    self::write_or_delete( $value, 'rank_math_title', $update, $delete );
                    break;
                case 'description':
                    self::write_or_delete( $value, 'rank_math_description', $update, $delete );
                    break;
                case 'focus_keyword':
                    self::write_or_delete( $value, 'rank_math_focus_keyword', $update, $delete );
                    break;
                case 'canonical':
                    self::write_or_delete( $value, 'rank_math_canonical_url', $update, $delete, 'url' );
                    break;
                case 'og_title':
                    self::write_or_delete( $value, 'rank_math_facebook_title', $update, $delete );
                    break;
                case 'og_description':
                    self::write_or_delete( $value, 'rank_math_facebook_description', $update, $delete );
                    break;
                case 'og_image':
                    self::write_or_delete( $value, 'rank_math_facebook_image', $update, $delete, 'url' );
                    break;
                case 'twitter_title':
                    self::write_or_delete( $value, 'rank_math_twitter_title', $update, $delete );
                    break;
                case 'twitter_description':
                    self::write_or_delete( $value, 'rank_math_twitter_description', $update, $delete );
                    break;
                case 'twitter_image':
                    self::write_or_delete( $value, 'rank_math_twitter_image', $update, $delete, 'url' );
                    break;
                case 'primary_category':
                    if ( null === $value || '' === $value ) {
                        $delete( 'rank_math_primary_category' );
                    } else {
                        $update( 'rank_math_primary_category', absint( $value ) );
                    }
                    break;
                case 'noindex':
                case 'nofollow':
                    $robots = (array) ( 'term' === $object_type
                        ? get_term_meta( $object_id, 'rank_math_robots', true )
                        : get_post_meta( $object_id, 'rank_math_robots', true ) );
                    $robots = array_values( array_filter( array_map( 'sanitize_text_field', $robots ) ) );
                    $tag    = $field;
                    $robots = array_values( array_diff( $robots, array( $tag ) ) );
                    if ( $value ) {
                        $robots[] = $tag;
                    }
                    $update( 'rank_math_robots', array_values( array_unique( $robots ) ) );
                    break;
                case 'advanced_robots':
                    if ( null === $value || '' === $value ) {
                        $delete( 'rank_math_advanced_robots' );
                    } else {
                        $update( 'rank_math_advanced_robots', map_deep( (array) $value, 'sanitize_text_field' ) );
                    }
                    break;
                case 'schema_page_type':
                case 'schema_article_type':
                    $warnings[] = 'Schema type writes are not exposed for Rank Math; use the Rank Math editor.';
                    break;
                case 'cornerstone':
                    $warnings[] = 'Cornerstone is not exposed for Rank Math.';
                    break;
            }
        }

        return array( 'warnings' => $warnings, 'indexable_rebuilt' => false );
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private static function resolve_provider( $provider ) {
        $providers = self::providers();
        if ( empty( $providers ) ) {
            return new WP_Error( 'seo_plugin_missing', 'Neither Yoast SEO nor Rank Math is active.', array( 'status' => 409 ) );
        }
        if ( 'auto' === $provider || empty( $provider ) ) {
            if ( count( $providers ) > 1 ) {
                return new WP_Error(
                    'provider_ambiguous',
                    'Both Yoast SEO and Rank Math are active. Pass provider:"yoast" or provider:"rankmath".',
                    array( 'status' => 409 )
                );
            }
            return array_key_first( $providers );
        }
        if ( ! isset( $providers[ $provider ] ) ) {
            return new WP_Error( 'provider_missing', 'Provider "' . $provider . '" is not active. Active: ' . implode( ', ', array_keys( $providers ) ), array( 'status' => 409 ) );
        }
        return $provider;
    }

    private static function validate_object( $object_type, $object_id, $taxonomy ) {
        if ( ! in_array( $object_type, array( 'post', 'term' ), true ) ) {
            return new WP_Error( 'invalid_object_type', 'object_type must be "post" or "term".', array( 'status' => 400 ) );
        }
        if ( 'post' === $object_type ) {
            $post = get_post( (int) $object_id );
            if ( ! $post ) {
                return new WP_Error( 'post_missing', 'Post not found: ' . $object_id, array( 'status' => 404 ) );
            }
            return $post;
        }
        if ( empty( $taxonomy ) ) {
            return new WP_Error( 'missing_taxonomy', 'taxonomy is required for term SEO.', array( 'status' => 400 ) );
        }
        $term = get_term( (int) $object_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'term_missing', 'Term not found: ' . $object_id . ' in ' . $taxonomy, array( 'status' => 404 ) );
        }
        return $term;
    }

    private static function sanitize_for_field( $field, $value ) {
        if ( null === $value ) {
            return null;
        }
        switch ( $field ) {
            case 'canonical':
            case 'og_image':
            case 'twitter_image':
                return '' === trim( (string) $value ) ? null : esc_url_raw( (string) $value );
            case 'primary_category':
                return '' === (string) $value ? null : absint( $value );
            case 'noindex':
            case 'nofollow':
            case 'cornerstone':
                return rest_sanitize_boolean( $value );
            case 'advanced_robots':
                return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : sanitize_text_field( (string) $value );
            default:
                return '' === trim( (string) $value ) ? null : sanitize_text_field( (string) $value );
        }
    }

    private static function sanitize_advanced_robots( $value ) {
        if ( is_array( $value ) ) {
            return map_deep( $value, 'sanitize_text_field' );
        }
        return sanitize_text_field( (string) $value );
    }

    private static function yoast_post_noindex_read( $value ) {
        $value = (string) $value;
        if ( '1' === $value ) {
            return true;
        }
        if ( '2' === $value ) {
            return false;
        }
        return null;
    }

    private static function yoast_post_robots_write( $field, $value ) {
        if ( null === $value ) {
            return '0';
        }
        $value = rest_sanitize_boolean( $value );
        if ( 'noindex' === $field ) {
            return $value ? '1' : '2';
        }
        return $value ? '1' : '0';
    }

    private static function yoast_term_noindex_read( $value ) {
        if ( 'noindex' === $value ) {
            return true;
        }
        if ( 'index' === $value ) {
            return false;
        }
        return null;
    }

    private static function yoast_term_noindex_write( $value ) {
        if ( null === $value ) {
            return 'default';
        }
        return rest_sanitize_boolean( $value ) ? 'noindex' : 'index';
    }

    private static function write_or_delete( $value, $key, $update, $delete, $type = 'text' ) {
        if ( null === $value || '' === trim( (string) $value ) ) {
            $delete( $key );
            return;
        }
        $clean = 'url' === $type ? esc_url_raw( (string) $value ) : sanitize_text_field( (string) $value );
        $update( $key, $clean );
    }

    private static function str_or_null( $value ) {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return null;
        }
        return (string) $value;
    }
}
