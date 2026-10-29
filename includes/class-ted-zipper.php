<?php
/**
 * ZIP builder + download streamer for Threshold Easy Downloader.
 *
 * Validates the requested item against the actual installed plugin/theme lists
 * (no path input from the user is ever used directly), builds a ZIP in the WP
 * temp dir, streams it, and cleans up.
 *
 * @package ThresholdEasyDownloader
 */

defined( 'ABSPATH' ) || exit;

class TED_Zipper {

    const SIZE_CACHE_KEY = 'ted_dir_sizes';

    /**
     * Build the nonce-protected admin-post download URL for an item.
     *
     * @param string $type 'plugin' or 'theme'.
     * @param string $item Plugin basename (dir/file.php) or theme stylesheet slug.
     */
    public static function download_url( $type, $item ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'ted_download',
                    'type'   => $type,
                    'item'   => rawurlencode( $item ),
                ),
                admin_url( 'admin-post.php' )
            ),
            'ted_download_' . $type . '_' . $item
        );
    }

    /**
     * admin_post_ted_download handler.
     */
    public static function handle_download() {
        $type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
        $item = isset( $_GET['item'] ) ? sanitize_text_field( wp_unslash( $_GET['item'] ) ) : '';

        if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) || '' === $item ) {
            wp_die( esc_html__( 'Invalid download request.', 'threshold-easy-downloader' ) );
        }

        // Capability first, then the nonce: a user without the right gets a
        // clear "not allowed" rather than a misleading "link expired".
        if ( ! current_user_can( 'plugin' === $type ? 'install_plugins' : 'install_themes' ) ) {
            wp_die( esc_html__( 'You are not allowed to download this.', 'threshold-easy-downloader' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( 'ted_download_' . $type . '_' . $item );

        if ( 'plugin' === $type ) {
            self::download_plugin( $item );
        } else {
            self::download_theme( $item );
        }
    }

    /**
     * Validate + download a plugin as ZIP.
     *
     * @param string $plugin_file Plugin basename, e.g. "my-plugin/my-plugin.php" or "hello.php".
     */
    private static function download_plugin( $plugin_file ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_die( esc_html__( 'You are not allowed to download plugins.', 'threshold-easy-downloader' ) );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();

        // The item must be a real installed plugin — this kills any path games.
        if ( ! isset( $plugins[ $plugin_file ] ) ) {
            wp_die( esc_html__( 'Unknown plugin.', 'threshold-easy-downloader' ) );
        }

        $data    = $plugins[ $plugin_file ];
        $version = ! empty( $data['Version'] ) ? $data['Version'] : '0';
        $dirname = dirname( $plugin_file );

        if ( '.' === $dirname ) {
            // Single-file plugin (e.g. hello.php).
            $slug   = sanitize_key( pathinfo( $plugin_file, PATHINFO_FILENAME ) );
            $source = WP_PLUGIN_DIR . '/' . $plugin_file;
        } else {
            $slug   = $dirname; // Already validated via get_plugins() key.
            $source = WP_PLUGIN_DIR . '/' . $dirname;
        }

        self::zip_and_stream( $source, $slug, self::zip_filename( $slug, $version ) );
    }

    /**
     * Validate + download a theme as ZIP.
     *
     * @param string $stylesheet Theme directory slug.
     */
    private static function download_theme( $stylesheet ) {
        if ( ! current_user_can( 'install_themes' ) ) {
            wp_die( esc_html__( 'You are not allowed to download themes.', 'threshold-easy-downloader' ) );
        }

        $themes = wp_get_themes();
        if ( ! isset( $themes[ $stylesheet ] ) ) {
            wp_die( esc_html__( 'Unknown theme.', 'threshold-easy-downloader' ) );
        }

        $theme   = $themes[ $stylesheet ];
        $version = $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '0';
        $source  = $theme->get_stylesheet_directory();

        self::zip_and_stream( $source, $stylesheet, self::zip_filename( $stylesheet, $version ) );
    }

    /**
     * "slug-1.2.3.zip", with the version cleaned for filesystem use.
     */
    private static function zip_filename( $slug, $version ) {
        $version = preg_replace( '/[^0-9A-Za-z._-]/', '', (string) $version );
        return $slug . ( '' !== $version ? '-' . $version : '' ) . '.zip';
    }

    /**
     * Build the ZIP in the temp dir and stream it to the browser.
     *
     * @param string $source   Absolute path to a directory or single file.
     * @param string $root     Folder name entries are placed under inside the zip.
     * @param string $filename Download filename.
     */
    private static function zip_and_stream( $source, $root, $filename ) {
        if ( ! file_exists( $source ) ) {
            wp_die( esc_html__( 'Source files not found on disk.', 'threshold-easy-downloader' ) );
        }

        $tmp = wp_tempnam( $filename );
        if ( ! $tmp ) {
            wp_die( esc_html__( 'Could not create a temporary file.', 'threshold-easy-downloader' ) );
        }

        // Big themes can take a while to pack; never leave a temp ZIP behind,
        // whether the request times out, fatals, or the user cancels mid-stream.
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        ignore_user_abort( true );
        register_shutdown_function(
            static function () use ( $tmp ) {
                if ( file_exists( $tmp ) ) {
                    wp_delete_file( $tmp );
                }
            }
        );

        $ok = class_exists( 'ZipArchive' )
            ? self::build_with_ziparchive( $source, $root, $tmp )
            : self::build_with_pclzip( $source, $root, $tmp );

        if ( ! $ok || ! filesize( $tmp ) ) {
            wp_delete_file( $tmp );
            wp_die( esc_html__( 'Failed to build the ZIP archive.', 'threshold-easy-downloader' ) );
        }

        // Output buffering or zlib compression would change the byte count the
        // browser receives, break Content-Length, and buffer the whole file in
        // memory. Turn both off before the first header.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        @ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        header( 'X-Content-Type-Options: nosniff' );

        // Stream in 1 MB chunks to keep memory flat on big themes. Direct PHP
        // file functions are used on purpose here: WP_Filesystem has no way to
        // read a file in pieces, and get_contents() would load a ZIP that can
        // run to tens of megabytes into memory just to echo it. The file is a
        // temp ZIP this plugin created a moment ago, never user-supplied.
        $fh = fopen( $tmp, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( $fh ) {
            while ( ! feof( $fh ) ) {
                echo fread( $fh, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions.file_system_operations_fread
                flush();
            }
            fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        wp_delete_file( $tmp );
        exit;
    }

    /**
     * Preferred builder: ZipArchive.
     */
    private static function build_with_ziparchive( $source, $root, $dest ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $dest, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return false;
        }

        if ( is_file( $source ) ) {
            $zip->addFile( $source, $root . '/' . basename( $source ) );
            return $zip->close();
        }

        $source = rtrim( $source, '/' );
        foreach ( self::list_entries( $source ) as $path => $is_dir ) {
            $relative = $root . '/' . ltrim( substr( $path, strlen( $source ) ), '/' );
            if ( $is_dir ) {
                $zip->addEmptyDir( $relative );
            } else {
                $zip->addFile( $path, $relative );
            }
        }

        return $zip->close();
    }

    /**
     * Every real file and directory under $source, symlinks excluded.
     *
     * A symlink inside a plugin or theme folder would otherwise be followed and
     * its target copied into the download (a file symlink by ZipArchive, a whole
     * directory tree by PclZip), which could hand out files from outside the
     * plugin, wp-config.php included. Links are simply left out.
     *
     * @return array<string,bool> path => is_dir
     */
    private static function list_entries( $source ) {
        $out = array();
        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch ( Exception $e ) {
            return $out;
        }
        foreach ( $iter as $file ) {
            if ( $file->isLink() ) {
                continue;
            }
            if ( $file->isDir() ) {
                $out[ $file->getPathname() ] = true;
            } elseif ( $file->isFile() ) {
                $out[ $file->getPathname() ] = false;
            }
        }
        return $out;
    }

    /**
     * Fallback builder: WP's bundled PclZip (no ZipArchive on the server).
     */
    private static function build_with_pclzip( $source, $root, $dest ) {
        if ( ! class_exists( 'PclZip' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        // PclZip refuses to write over the 0-byte tempnam file.
        wp_delete_file( $dest );

        $zip = new PclZip( $dest );

        if ( is_file( $source ) ) {
            // Single-file plugin: same layout as ZipArchive, root/file.php.
            $result = $zip->create( $source, PCLZIP_OPT_REMOVE_PATH, dirname( $source ), PCLZIP_OPT_ADD_PATH, $root );
            return 0 !== $result;
        }

        // Hand PclZip an explicit, symlink-free file list rather than the
        // directory: its own recursion follows symlinks (and can loop on them).
        $source = rtrim( $source, '/' );
        $list   = array();
        foreach ( self::list_entries( $source ) as $path => $is_dir ) {
            if ( ! $is_dir ) {
                $list[] = $path;
            }
        }
        if ( empty( $list ) ) {
            return false;
        }
        $result = $zip->create( $list, PCLZIP_OPT_REMOVE_PATH, $source, PCLZIP_OPT_ADD_PATH, $root );

        return 0 !== $result;
    }

    /**
     * Human-readable size of a plugin/theme directory (for the Tools page).
     */
    public static function dir_size( $source ) {
        if ( is_file( $source ) ) {
            return size_format( (float) filesize( $source ) );
        }
        if ( ! is_dir( $source ) ) {
            return '—';
        }

        // Walking every plugin and theme tree on each page view costs tens of
        // thousands of stat() calls on a page-builder site, so sizes are cached
        // for a day, keyed by the directory's mtime (which changes on install,
        // update, or delete of anything directly inside it).
        $cache = get_transient( self::SIZE_CACHE_KEY );
        $cache = is_array( $cache ) ? $cache : array();
        $key   = $source . '|' . (int) @filemtime( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if ( isset( $cache[ $key ] ) ) {
            return size_format( (float) $cache[ $key ] );
        }

        $bytes = 0;
        foreach ( self::list_entries( $source ) as $path => $is_dir ) {
            if ( ! $is_dir ) {
                $bytes += (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }

        // Keep the cache small: one entry per current directory.
        $cache[ $key ] = $bytes;
        if ( count( $cache ) > 400 ) {
            $cache = array_slice( $cache, -200, null, true );
        }
        set_transient( self::SIZE_CACHE_KEY, $cache, DAY_IN_SECONDS );

        return size_format( (float) $bytes );
    }
}
