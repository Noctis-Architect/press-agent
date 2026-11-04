<?php
/**
 * Child theme detection, scaffolding, switching and safe file editing.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Child_Theme_Manager {

    const MODE_OPTION = 'pressagent_appearance_mode';
    const MODES       = array( 'elementor', 'child-theme', 'hybrid' );
    const BACKUP_DIR  = 'pressagent-backups/themes';

    const READABLE_EXTENSIONS = array( 'php', 'css', 'js', 'json', 'txt', 'md', 'svg', 'html', 'map' );
    const WRITABLE_EXTENSIONS = array( 'php', 'css', 'js', 'json', 'txt', 'md', 'svg', 'html' );

    const MAX_READ_BYTES  = 262144;  // 256 KB
    const MAX_WRITE_BYTES = 524288;  // 512 KB

    /**
     * Current theme facts, Elementor integration and appearance mode guidance.
     *
     * @return array
     */
    public static function get_status() {
        $theme   = wp_get_theme();
        $slug    = self::current_stylesheet();
        $parent  = $theme->parent();
        $is_child = (bool) $parent;
        $mode    = self::get_mode( $slug );

        $elementor_active = class_exists( '\Elementor\Plugin' );
        $kit_id           = (int) get_option( 'elementor_active_kit', 0 );
        $theme_builder    = 0;

        if ( $elementor_active ) {
            $counts = wp_count_posts( 'elementor_library' );
            if ( $counts ) {
                foreach ( array( 'publish', 'draft', 'private' ) as $status ) {
                    if ( isset( $counts->$status ) ) {
                        $theme_builder += (int) $counts->$status;
                    }
                }
            }
        }

        $stylesheet_path = get_stylesheet_directory() . '/style.css';
        $functions_path  = get_stylesheet_directory() . '/functions.php';

        return array(
            'site'             => array(
                'name' => get_bloginfo( 'name' ),
                'url'  => home_url( '/' ),
            ),
            'active_theme'     => array(
                'name'        => $theme->get( 'Name' ),
                'slug'        => $slug,
                'version'     => $theme->get( 'Version' ),
                'is_child'    => $is_child,
                'parent_slug' => $is_child ? $theme->get_template() : null,
                'parent_name' => $is_child && $parent ? $parent->get( 'Name' ) : null,
                'template'    => $theme->get_template(),
                'stylesheet'  => $theme->get_stylesheet(),
                'is_block'    => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
                'has_functions'  => file_exists( $functions_path ),
                'style_bytes'    => file_exists( $stylesheet_path ) ? (int) filesize( $stylesheet_path ) : 0,
            ),
            'elementor'        => array(
                'active'          => $elementor_active,
                'version'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
                'active_kit_id'   => $kit_id,
                'library_count'   => $theme_builder,
            ),
            'appearance_mode'  => $mode,
            'mode_guidance'    => self::mode_guidance( $mode ),
            'backups'          => self::list_backups( $slug, 8 ),
            'recommendations'  => self::recommendations( $is_child, $elementor_active, $mode ),
        );
    }

    /**
     * List files inside the active child theme (read-only view).
     *
     * @param int $limit Maximum files.
     * @return array|WP_Error
     */
    public static function list_files( $limit = 200 ) {
        $dir = self::editable_theme_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $files = array();
        self::scan_dir( $dir['path'], $dir['path'], $files, 4, (int) $limit );
        usort(
            $files,
            static function ( $a, $b ) {
                return strcmp( $a['path'], $b['path'] );
            }
        );

        return array(
            'theme' => $dir['slug'],
            'files' => $files,
            'count' => count( $files ),
        );
    }

    /**
     * Read a file from the active child theme.
     *
     * @param string $relative_path Relative path.
     * @return array|WP_Error
     */
    public static function read_file( $relative_path ) {
        $dir = self::editable_theme_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $target = self::resolve_path( $dir['path'], $relative_path );
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        if ( ! file_exists( $target ) ) {
            return new WP_Error( 'not_found', 'File not found: ' . $relative_path, array( 'status' => 404 ) );
        }
        if ( ! is_file( $target ) ) {
            return new WP_Error( 'invalid_file', 'Path is not a regular file.', array( 'status' => 400 ) );
        }

        $ext = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::READABLE_EXTENSIONS, true ) ) {
            return new WP_Error( 'forbidden_ext', 'Reading .' . $ext . ' files is not allowed.', array( 'status' => 403 ) );
        }

        $size = (int) filesize( $target );
        if ( $size > self::MAX_READ_BYTES ) {
            return new WP_Error( 'file_too_large', 'File exceeds the 256 KB read limit.', array( 'status' => 413 ) );
        }

        $content = file_get_contents( $target );
        if ( false === $content ) {
            return new WP_Error( 'read_failed', 'Could not read the file.', array( 'status' => 500 ) );
        }

        return array(
            'theme'      => $dir['slug'],
            'path'       => $relative_path,
            'extension'  => $ext,
            'bytes'      => $size,
            'modified'   => gmdate( 'c', (int) filemtime( $target ) ),
            'content'    => $content,
        );
    }

    /**
     * Write a file inside the active child theme with a verified backup and PHP lint.
     *
     * @param string $relative_path Relative path.
     * @param string $content       Full file content.
     * @return array|WP_Error
     */
    public static function write_file( $relative_path, $content ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit theme files.', array( 'status' => 403 ) );
        }
        if ( ! is_string( $content ) ) {
            return new WP_Error( 'invalid_content', 'Content must be a string.', array( 'status' => 400 ) );
        }
        if ( strlen( $content ) > self::MAX_WRITE_BYTES ) {
            return new WP_Error( 'file_too_large', 'File exceeds the 512 KB write limit.', array( 'status' => 413 ) );
        }
        if ( false !== strpos( $content, "\0" ) ) {
            return new WP_Error( 'invalid_content', 'Null bytes are not allowed.', array( 'status' => 400 ) );
        }

        $dir = self::editable_theme_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $target = self::resolve_path( $dir['path'], $relative_path, true );
        if ( is_wp_error( $target ) ) {
            return $target;
        }

        $ext = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::WRITABLE_EXTENSIONS, true ) ) {
            return new WP_Error( 'forbidden_ext', 'Writing .' . $ext . ' files is not allowed.', array( 'status' => 403 ) );
        }

        if ( 'php' === $ext && class_exists( 'PressAgent_Code_Executor' ) ) {
            $lint = PressAgent_Code_Executor::validate_php_syntax( $content );
            if ( is_wp_error( $lint ) ) {
                return $lint;
            }
        }

        $backup_id = null;
        if ( file_exists( $target ) ) {
            $backup_id = self::backup_file( $dir['slug'], $dir['path'], $target, $relative_path );
            if ( is_wp_error( $backup_id ) ) {
                return $backup_id;
            }
        }

        $parent = dirname( $target );
        if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
            return new WP_Error( 'mkdir_failed', 'Could not create the target directory.', array( 'status' => 500 ) );
        }

        if ( false === file_put_contents( $target, $content ) ) {
            return new WP_Error( 'write_failed', 'Could not write the file. Check filesystem permissions.', array( 'status' => 500 ) );
        }

        self::flush_caches();

        return array(
            'success'    => true,
            'theme'      => $dir['slug'],
            'path'       => $relative_path,
            'bytes'      => strlen( $content ),
            'backup_id'  => $backup_id,
            'message'    => 'File written successfully.',
        );
    }

    /**
     * Create a child theme for a parent theme and optionally activate it.
     *
     * @param string $parent_slug Parent theme slug.
     * @param string $slug        Child theme slug.
     * @param bool   $activate    Activate after creation.
     * @param bool   $overwrite   Overwrite existing child theme files.
     * @return array|WP_Error
     */
    public static function create_child( $parent_slug = '', $slug = '', $activate = true, $overwrite = false ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to manage themes.', array( 'status' => 403 ) );
        }

        if ( empty( $parent_slug ) ) {
            $parent_slug = get_template();
        }
        $parent_slug = sanitize_title( $parent_slug );
        $parent      = wp_get_theme( $parent_slug );
        if ( ! $parent->exists() ) {
            return new WP_Error( 'parent_missing', 'Parent theme not found: ' . $parent_slug, array( 'status' => 404 ) );
        }
        if ( $parent->parent() ) {
            return new WP_Error( 'parent_is_child', 'The selected parent is itself a child theme. Choose its root parent.', array( 'status' => 400 ) );
        }

        if ( empty( $slug ) ) {
            $slug = $parent_slug . '-child';
        }
        $slug = sanitize_title( $slug );
        if ( empty( $slug ) ) {
            return new WP_Error( 'invalid_slug', 'Invalid child theme slug.', array( 'status' => 400 ) );
        }

        $theme_root = get_theme_root();
        $child_dir  = trailingslashit( $theme_root ) . $slug;

        if ( is_dir( $child_dir ) && ! $overwrite ) {
            return new WP_Error( 'exists', 'Child theme directory already exists: ' . $slug . '. Pass overwrite:true to replace its scaffold files.', array( 'status' => 409 ) );
        }

        if ( ! is_dir( $child_dir ) && ! wp_mkdir_p( $child_dir ) ) {
            return new WP_Error( 'mkdir_failed', 'Could not create the child theme directory.', array( 'status' => 500 ) );
        }
        if ( ! is_dir( $child_dir . '/assets/css' ) ) {
            wp_mkdir_p( $child_dir . '/assets/css' );
        }

        $parent_name = $parent->get( 'Name' );
        $style_css   = "/*\n";
        $style_css  .= "Theme Name: {$parent_name} Child\n";
        $style_css  .= "Theme URI: " . home_url( '/' ) . "\n";
        $style_css  .= "Description: Child theme for {$parent_name}, managed by PressAgent.\n";
        $style_css  .= "Template: {$parent_slug}\n";
        $style_css  .= "Version: 1.0.0\n";
        $style_css  .= "Text Domain: {$slug}\n";
        $style_css  .= "*/\n\n";
        $style_css  .= "/* Global design tokens and child-only overrides live here or in assets/css/pressagent.css. */\n";

        $functions_php = "<?php\n";
        $functions_php .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";
        $functions_php .= self::enqueue_block();
        $functions_php .= "\n";

        $global_css = "/* PressAgent global layer: tokens, typography and cross-page utilities. */\n";
        $global_css .= ":root {\n";
        $global_css .= "  /* --pa-primary: #...; */\n";
        $global_css .= "}\n";

        $files = array(
            'style.css'                     => $style_css,
            'functions.php'                 => $functions_php,
            'assets/css/pressagent.css'     => $global_css,
        );

        foreach ( $files as $relative => $content ) {
            $target = $child_dir . '/' . $relative;
            if ( file_exists( $target ) && ! $overwrite ) {
                continue;
            }
            wp_mkdir_p( dirname( $target ) );
            if ( false === file_put_contents( $target, $content ) ) {
                return new WP_Error( 'write_failed', 'Could not scaffold ' . $relative . '.', array( 'status' => 500 ) );
            }
        }

        if ( $activate ) {
            switch_theme( $slug );
            self::flush_caches();
        }

        return array(
            'success'  => true,
            'child'    => $slug,
            'parent'   => $parent_slug,
            'activated'=> $activate,
            'files'    => array_keys( $files ),
            'message'  => 'Child theme ' . $slug . ' created' . ( $activate ? ' and activated.' : '.' ),
            'next_steps' => array(
                'Set the appearance mode with pressagent_theme action "set_mode" (elementor, child-theme, hybrid).',
                'Put global tokens in assets/css/pressagent.css and page-specific CSS in style.css.',
                'Use action "setup" to ensure the PressAgent enqueue block exists in functions.php.',
            ),
        );
    }

    /**
     * Switch to an installed theme.
     *
     * @param string $slug Theme stylesheet slug.
     * @return array|WP_Error
     */
    public static function switch_theme_to( $slug ) {
        if ( ! current_user_can( 'switch_themes' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to switch themes.', array( 'status' => 403 ) );
        }

        $slug  = sanitize_title( $slug );
        $theme = wp_get_theme( $slug );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'theme_missing', 'Theme not found: ' . $slug, array( 'status' => 404 ) );
        }
        if ( $slug === self::current_stylesheet() ) {
            return new WP_Error( 'already_active', 'Theme is already active: ' . $slug, array( 'status' => 409 ) );
        }

        switch_theme( $slug );
        self::flush_caches();

        return array(
            'success'        => true,
            'active_theme'   => $slug,
            'is_child'       => (bool) $theme->parent(),
            'parent_slug'    => $theme->parent() ? $theme->get_template() : null,
            'message'        => 'Theme switched to ' . $slug . '.',
            'recommendation' => 'Run action "status" and review the appearance mode before editing files.',
        );
    }

    /**
     * Set the appearance mode for this site.
     *
     * @param string $mode elementor|child-theme|hybrid
     * @return array|WP_Error
     */
    public static function set_mode( $mode ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to change the appearance mode.', array( 'status' => 403 ) );
        }
        if ( ! in_array( $mode, self::MODES, true ) ) {
            return new WP_Error( 'invalid_mode', 'Mode must be one of: ' . implode( ', ', self::MODES ), array( 'status' => 400 ) );
        }

        update_option( self::MODE_OPTION, $mode, false );

        return array(
            'success'       => true,
            'appearance_mode' => $mode,
            'guidance'      => self::mode_guidance( $mode ),
            'status'        => self::get_status(),
        );
    }

    /**
     * Ensure the PressAgent global CSS file exists and the enqueue block is present.
     *
     * @param string $mode Optional mode to apply.
     * @return array|WP_Error
     */
    public static function setup( $mode = '' ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit theme files.', array( 'status' => 403 ) );
        }

        $dir = self::editable_theme_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $created = array();
        $css_rel = 'assets/css/pressagent.css';
        $css_abs = $dir['path'] . '/' . $css_rel;
        if ( ! file_exists( $css_abs ) ) {
            wp_mkdir_p( dirname( $css_abs ) );
            file_put_contents( $css_abs, "/* PressAgent global layer: tokens, typography and cross-page utilities. */\n" );
            $created[] = $css_rel;
        }

        $functions = $dir['path'] . '/functions.php';
        $backup_id = null;
        $content   = file_exists( $functions ) ? file_get_contents( $functions ) : "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n";
        if ( false === strpos( $content, 'PressAgent child enqueue' ) ) {
            $backup_id = file_exists( $functions ) ? self::backup_file( $dir['slug'], $dir['path'], $functions, 'functions.php' ) : null;
            if ( is_wp_error( $backup_id ) ) {
                return $backup_id;
            }

            $trimmed = rtrim( $content );
            if ( substr( $trimmed, -2 ) === '?>' ) {
                $trimmed = rtrim( substr( $trimmed, 0, -2 ) );
            }
            $content = $trimmed . "\n\n" . self::enqueue_block() . "\n";

            if ( class_exists( 'PressAgent_Code_Executor' ) ) {
                $lint = PressAgent_Code_Executor::validate_php_syntax( $content );
                if ( is_wp_error( $lint ) ) {
                    return $lint;
                }
            }
            if ( false === file_put_contents( $functions, $content ) ) {
                return new WP_Error( 'write_failed', 'Could not update functions.php.', array( 'status' => 500 ) );
            }
        }

        if ( ! empty( $mode ) ) {
            $mode_result = self::set_mode( $mode );
            if ( is_wp_error( $mode_result ) ) {
                return $mode_result;
            }
        }

        self::flush_caches();

        return array(
            'success'   => true,
            'theme'     => $dir['slug'],
            'created'   => $created,
            'backup_id' => $backup_id,
            'message'   => 'Child theme setup complete.',
            'status'    => self::get_status(),
        );
    }

    /**
     * Restore a previously created theme file backup.
     *
     * @param string $backup_id Backup identifier.
     * @return array|WP_Error
     */
    public static function restore_backup( $backup_id ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to restore theme files.', array( 'status' => 403 ) );
        }

        $dir = self::editable_theme_dir();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }

        $backup_id = preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $backup_id );
        $backup_dir = self::backups_root( $dir['slug'] ) . '/' . $backup_id;
        $meta_path  = $backup_dir . '/meta.json';
        if ( ! $backup_id || ! file_exists( $meta_path ) ) {
            return new WP_Error( 'backup_missing', 'Backup not found: ' . $backup_id, array( 'status' => 404 ) );
        }

        $meta = json_decode( file_get_contents( $meta_path ), true );
        $rel  = isset( $meta['path'] ) ? $meta['path'] : '';
        $file = $backup_dir . '/file';
        if ( ! $rel || ! file_exists( $file ) ) {
            return new WP_Error( 'backup_corrupt', 'Backup metadata or payload is missing.', array( 'status' => 500 ) );
        }

        $target = self::resolve_path( $dir['path'], $rel, true );
        if ( is_wp_error( $target ) ) {
            return $target;
        }

        $content = file_get_contents( $file );
        if ( false === $content || strlen( $content ) > self::MAX_WRITE_BYTES ) {
            return new WP_Error( 'backup_too_large', 'Backup content is unreadable or exceeds the write limit.', array( 'status' => 413 ) );
        }
        $ext     = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
        if ( 'php' === $ext && class_exists( 'PressAgent_Code_Executor' ) ) {
            $lint = PressAgent_Code_Executor::validate_php_syntax( $content );
            if ( is_wp_error( $lint ) ) {
                return $lint;
            }
        }

        if ( false === file_put_contents( $target, $content ) ) {
            return new WP_Error( 'write_failed', 'Could not restore the file.', array( 'status' => 500 ) );
        }

        self::flush_caches();

        return array(
            'success'   => true,
            'path'      => $rel,
            'backup_id' => $backup_id,
            'message'   => 'Backup restored successfully.',
        );
    }

    /**
     * List available backups for the active child theme.
     *
     * @param string $slug  Theme slug.
     * @param int    $limit Maximum entries.
     * @return array
     */
    public static function list_backups( $slug = '', $limit = 20 ) {
        $slug = $slug ? $slug : self::current_stylesheet();
        $root = self::backups_root( $slug );
        if ( ! is_dir( $root ) ) {
            return array();
        }

        $items = array();
        foreach ( (array) scandir( $root ) as $entry ) {
            if ( '.' === $entry || '..' === $entry ) {
                continue;
            }
            $meta_path = $root . '/' . $entry . '/meta.json';
            if ( ! file_exists( $meta_path ) ) {
                continue;
            }
            $meta = json_decode( file_get_contents( $meta_path ), true );
            if ( ! is_array( $meta ) ) {
                continue;
            }
            $meta['backup_id'] = $entry;
            $items[] = $meta;
        }

        usort(
            $items,
            static function ( $a, $b ) {
                return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
            }
        );

        return array_slice( $items, 0, max( 1, (int) $limit ) );
    }

    /* ---------------------------------------------------------------------
     * Internal helpers
     * ------------------------------------------------------------------- */

    private static function get_mode( $slug ) {
        $mode = get_option( self::MODE_OPTION, '' );
        if ( in_array( $mode, self::MODES, true ) ) {
            return $mode;
        }
        // Sensible default: Elementor-driven sites are hybrid, classic sites are child-theme.
        return class_exists( '\Elementor\Plugin' ) ? 'hybrid' : 'child-theme';
    }

    private static function mode_guidance( $mode ) {
        $themes = array(
            'elementor' => array(
                'label'  => 'Elementor-first',
                'rules'  => array(
                    'Build page layout and global styles in Elementor (Kit + Theme Builder).',
                    'Use the child theme only for PHP hooks or small CSS fixes; avoid template overrides that fight Elementor.',
                    'Edit pages with pressagent_get_elementor_layout / pressagent_add_elementor_section.',
                ),
            ),
            'child-theme' => array(
                'label'  => 'Child theme-first',
                'rules'  => array(
                    'Use classic PHP templates and child theme CSS for layout, header, footer and archives.',
                    'Keep Elementor content edits minimal and scoped to post content.',
                    'Edit files only through pressagent_theme write_file so backups and PHP lint run.',
                ),
            ),
            'hybrid' => array(
                'label'  => 'Hybrid (recommended)',
                'rules'  => array(
                    'Use Elementor for pages, header and footer via Theme Builder.',
                    'Keep design tokens and global CSS in the child theme assets/css/pressagent.css.',
                    'Use functions.php hooks and template overrides only for behavior Elementor cannot express.',
                    'After theme edits run pressagent_purge_cache and capture desktop + mobile screenshots.',
                ),
            ),
        );
        return $themes[ $mode ] ?? $themes['hybrid'];
    }

    private static function recommendations( $is_child, $elementor_active, $mode ) {
        $items = array();
        if ( ! $is_child ) {
            $items[] = 'Active theme is not a child theme. Create one with action "create_child" before editing theme files.';
        }
        if ( 'elementor' === $mode && ! $elementor_active ) {
            $items[] = 'Appearance mode is "elementor" but Elementor is not active. Switch to "child-theme" or install Elementor.';
        }
        if ( 'child-theme' === $mode && $elementor_active ) {
            $items[] = 'Appearance mode is "child-theme" but Elementor is active. Confirm which layer owns header/footer to avoid duplication.';
        }
        return $items;
    }

    private static function current_stylesheet() {
        return get_stylesheet();
    }

    private static function editable_theme_dir() {
        $slug = self::current_stylesheet();
        $theme = wp_get_theme( $slug );
        if ( ! $theme->exists() ) {
            return new WP_Error( 'theme_missing', 'Active theme not found.', array( 'status' => 404 ) );
        }
        if ( ! $theme->parent() ) {
            return new WP_Error(
                'not_child_theme',
                'The active theme is not a child theme. Create and activate a child theme first (action "create_child").',
                array( 'status' => 409 )
            );
        }
        $path = realpath( get_stylesheet_directory() );
        if ( ! $path ) {
            return new WP_Error( 'theme_dir_missing', 'Could not resolve the active child theme directory.', array( 'status' => 500 ) );
        }
        return array( 'slug' => $slug, 'path' => wp_normalize_path( $path ) );
    }

    /**
     * Resolve a relative path inside the theme directory and block traversal.
     *
     * @param string $base         Theme root.
     * @param string $relative     Relative path.
     * @param bool   $allow_create Allow non-existent target (still inside base).
     * @return string|WP_Error
     */
    private static function resolve_path( $base, $relative, $allow_create = false ) {
        $relative = (string) $relative;
        if ( '' === $relative || false !== strpos( $relative, "\0" ) ) {
            return new WP_Error( 'invalid_path', 'A valid relative file path is required.', array( 'status' => 400 ) );
        }
        if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $relative ) || preg_match( '#^([a-zA-Z]:|/|\\\\)#', $relative ) ) {
            return new WP_Error( 'invalid_path', 'Path traversal is not allowed.', array( 'status' => 400 ) );
        }

        $relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
        $base     = trailingslashit( wp_normalize_path( realpath( $base ) ) );
        $target   = wp_normalize_path( $base . $relative );

        if ( ! $allow_create ) {
            $real = realpath( $target );
            if ( ! $real ) {
                return new WP_Error( 'not_found', 'File not found: ' . $relative, array( 'status' => 404 ) );
            }
            $target = wp_normalize_path( $real );
        } else {
            $parent = realpath( dirname( $target ) );
            if ( $parent ) {
                $target = wp_normalize_path( $parent . '/' . basename( $target ) );
            }
        }

        if ( 0 !== strpos( $target, $base ) ) {
            return new WP_Error( 'invalid_path', 'Target is outside the child theme directory.', array( 'status' => 403 ) );
        }
        if ( is_link( $target ) ) {
            return new WP_Error( 'invalid_path', 'Symlinks are not allowed.', array( 'status' => 403 ) );
        }
        return $target;
    }

    private static function scan_dir( $root, $dir, &$files, $depth, $limit ) {
        if ( $depth < 0 || count( $files ) >= $limit || ! is_dir( $dir ) ) {
            return;
        }
        $entries = scandir( $dir );
        if ( false === $entries ) {
            return;
        }
        foreach ( $entries as $entry ) {
            if ( '.' === $entry || '..' === $entry || '.git' === $entry || 'node_modules' === $entry || 'vendor' === $entry ) {
                continue;
            }
            $abs = $dir . '/' . $entry;
            if ( is_link( $abs ) ) {
                continue;
            }
            if ( is_dir( $abs ) ) {
                self::scan_dir( $root, $abs, $files, $depth - 1, $limit );
                continue;
            }
            $ext = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, self::READABLE_EXTENSIONS, true ) ) {
                continue;
            }
            if ( filesize( $abs ) > self::MAX_READ_BYTES ) {
                continue;
            }
            $files[] = array(
                'path'     => ltrim( wp_normalize_path( substr( $abs, strlen( $root ) ) ), '/' ),
                'bytes'    => (int) filesize( $abs ),
                'modified' => gmdate( 'c', (int) filemtime( $abs ) ),
            );
            if ( count( $files ) >= $limit ) {
                return;
            }
        }
    }

    private static function backups_root( $slug ) {
        $uploads = wp_upload_dir();
        return trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . self::BACKUP_DIR . '/' . $slug;
    }

    private static function backup_file( $slug, $theme_dir, $absolute, $relative ) {
        $id   = gmdate( 'YmdHis' ) . '-' . substr( md5( $relative . microtime( true ) ), 0, 8 );
        $dest = self::backups_root( $slug ) . '/' . $id;
        if ( ! wp_mkdir_p( $dest ) ) {
            return new WP_Error( 'backup_failed', 'Could not create the backup directory.', array( 'status' => 500 ) );
        }
        if ( ! copy( $absolute, $dest . '/file' ) ) {
            return new WP_Error( 'backup_failed', 'Could not copy the file into the backup directory.', array( 'status' => 500 ) );
        }
        $meta = array(
            'backup_id' => $id,
            'path'      => $relative,
            'bytes'     => (int) filesize( $absolute ),
            'created_at'=> gmdate( 'c' ),
        );
        file_put_contents( $dest . '/meta.json', wp_json_encode( $meta ) );
        return $id;
    }

    private static function enqueue_block() {
        return "// PressAgent child enqueue\nadd_action( 'wp_enqueue_scripts', function () {\n    \$parent = get_template();\n    \$parent_version = wp_get_theme( \$parent )->get( 'Version' );\n    wp_enqueue_style( 'pa-parent', get_template_directory_uri() . '/style.css', array(), \$parent_version );\n    wp_enqueue_style( 'pa-child', get_stylesheet_uri(), array( 'pa-parent' ), wp_get_theme()->get( 'Version' ) );\n    \$global = get_stylesheet_directory() . '/assets/css/pressagent.css';\n    if ( file_exists( \$global ) ) {\n        wp_enqueue_style( 'pa-global', get_stylesheet_directory_uri() . '/assets/css/pressagent.css', array( 'pa-child' ), (string) filemtime( \$global ) );\n    }\n}, 20 );\n";
    }

    private static function flush_caches() {
        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }
        if ( class_exists( 'PressAgent_Generic_Settings' ) ) {
            PressAgent_Generic_Settings::purge_cache( 'all' );
        }
    }
}
