<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PressAgent_Debug_Log_Parser {
    public static function read_log( $lines = 100, $level = 'all' ) {
        $log_file = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';

        // Only allow reading a real debug log under WP_CONTENT_DIR (or an explicitly
        // configured WP_DEBUG_LOG path). Reject anything else to avoid arbitrary file reads.
        $real = realpath( $log_file );
        $content_real = realpath( WP_CONTENT_DIR );
        if ( $real === false || $content_real === false || strpos( $real, $content_real ) !== 0 ) {
            return new WP_Error( 'invalid_log', 'Debug log path is outside the allowed content directory.', array( 'status' => 403 ) );
        }

        // Bound the number of lines to something sane.
        $lines = max( 1, min( (int) $lines, 5000 ) );

        if ( ! file_exists( $log_file ) ) {
            return new WP_Error( 'no_log', 'Debug log file not found.', array( 'status' => 404 ) );
        }
        
        $file_content = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( ! $file_content ) return array();
        
        $entries = array();
        $recent_lines = array_slice( $file_content, -$lines );
        
        foreach ( $recent_lines as $line ) {
            if ( preg_match( '/^\[(.*?)\] (PHP .*?): (.*?) in (.*?) on line (\d+)$/', $line, $matches ) ) {
                $log_level = stripos( $matches[2], 'error' ) !== false ? 'error' : ( stripos( $matches[2], 'warning' ) !== false ? 'warning' : 'notice' );
                
                if ( $level !== 'all' && $level !== $log_level ) continue;

                $entries[] = array(
                    'timestamp' => $matches[1],
                    'level' => $log_level,
                    'message' => $matches[3],
                    'file' => $matches[4],
                    'line' => (int) $matches[5]
                );
            } else {
                $entries[] = array( 'raw' => $line );
            }
        }
        
        return $entries;
    }

    public static function diagnose( $error_text ) {
        return array(
            'error' => sanitize_text_field( $error_text ),
            'suggested_steps' => array(
                'Check the referenced file and line number.',
                'Verify if a recent plugin update caused the issue.',
                'Search for known conflicts with active plugins.'
            )
        );
    }
}
