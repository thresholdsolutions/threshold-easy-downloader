<?php
/**
 * Self-hosted update checker for Threshold Easy Plugin and Theme Downloader.
 *
 * Mirrors the Threshold Login Guard / PHP Mailer updater: fetches a JSON
 * manifest from the Threshold update server, tells WordPress when a new
 * version is available, and provides the "View Details" modal content.
 * No WordPress.org repo involved.
 *
 * Manifest format (info.json):
 * {
 *   "version":      "1.0.0",
 *   "download_url": "https://updates.webdevsandbox.com/threshold-easy-downloader/threshold-easy-downloader-1.0.0.zip",
 *   "tested":       "6.8",
 *   "requires":     "6.0",
 *   "requires_php": "7.4",
 *   "last_updated": "2026-07-26 12:00:00",
 *   "author":       "<a href=\"https://spencer-taylor.com/\">Threshold Solutions</a>",
 *   "homepage":     "https://spencer-taylor.com/",
 *   "sections":     { "description": "...", "changelog": "..." }
 * }
 *
 * @package ThresholdEasyDownloader
 */

defined( 'ABSPATH' ) || exit;

class TED_Updater {

    const MANIFEST_URL = 'https://updates.webdevsandbox.com/threshold-easy-downloader/info.json';
    const PACKAGE_BASE = 'https://updates.webdevsandbox.com/threshold-easy-downloader/';
    const CACHE_KEY    = 'ted_update_manifest';
    const CACHE_TTL    = HOUR_IN_SECONDS;
    const FAIL_TTL     = 300; // 5 min back-off when manifest fetch fails
    const SLUG         = 'threshold-easy-downloader';

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_update' ), 10, 2 );
    }

    /**
     * Fetch the manifest with caching + failure back-off.
     */
    public static function fetch_manifest( $force = false ) {
        if ( ! $force ) {
            $cached = get_site_transient( self::CACHE_KEY );
            if ( false !== $cached ) {
                return is_array( $cached ) ? $cached : array();
            }
        }

        $response = wp_remote_get(
            self::MANIFEST_URL,
            array(
                'timeout'    => 10,
                'user-agent' => 'TED-Updater/' . TED_VERSION . '; ' . home_url(),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_site_transient( self::CACHE_KEY, array(), self::FAIL_TTL );
            return array();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! self::manifest_is_sane( $data ) ) {
            set_site_transient( self::CACHE_KEY, array(), self::FAIL_TTL );
            return array();
        }

        set_site_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * Shape-check the manifest before anything trusts it.
     *
     * version and download_url must be non-empty strings (version_compare()
     * throws a TypeError on PHP 8 if handed an array, which would take down
     * every admin page), and the package must come from the update server over
     * HTTPS so a tampered manifest cannot point WordPress at another host.
     */
    private static function manifest_is_sane( $data ) {
        if ( ! is_array( $data ) ) {
            return false;
        }
        if ( empty( $data['version'] ) || ! is_scalar( $data['version'] ) || ! preg_match( '/^[0-9][0-9A-Za-z.\-]*$/', (string) $data['version'] ) ) {
            return false;
        }
        if ( empty( $data['download_url'] ) || ! is_string( $data['download_url'] ) || 0 !== strpos( $data['download_url'], self::PACKAGE_BASE ) ) {
            return false;
        }
        return true;
    }

    /**
     * Inject our update info into the update_plugins transient that WP uses
     * to drive the "Update available" UI.
     */
    public static function inject_update( $transient ) {
        if ( empty( $transient ) || ! is_object( $transient ) ) {
            return $transient;
        }
        // Bail if WP hasn't done its own check yet.
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $manifest = self::fetch_manifest();
        if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
            return $transient;
        }

        $plugin_basename = plugin_basename( TED_PLUGIN_FILE );

        if ( version_compare( (string) $manifest['version'], TED_VERSION, '>' ) ) {
            // An update IS due: make sure a previous "no update" marker cannot linger.
            unset( $transient->no_update[ $plugin_basename ] );
            $transient->response[ $plugin_basename ] = (object) array(
                'id'           => self::SLUG,
                'slug'         => self::SLUG,
                'plugin'       => $plugin_basename,
                'new_version'  => (string) $manifest['version'],
                'package'      => $manifest['download_url'],
                'url'          => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
                'tested'       => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
                'requires'     => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
                'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
                'icons'        => isset( $manifest['icons'] ) ? $manifest['icons'] : array(),
            );
        } else {
            // No update is due. CRITICAL: drop any stale offer still sitting in
            // ->response. WordPress treats ANY entry there as an available update,
            // so a leftover same-version entry makes it reinstall this plugin over
            // itself and email "(from version X to X)". Writing ->no_update alone is
            // not enough - the two arrays must never both hold this plugin.
            unset( $transient->response[ $plugin_basename ] );
            $transient->no_update[ $plugin_basename ] = (object) array(
                'id'          => self::SLUG,
                'slug'        => self::SLUG,
                'plugin'      => $plugin_basename,
                'new_version' => TED_VERSION,
                'url'         => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
                'package'     => '',
            );
        }

        return $transient;
    }

    /**
     * Provide plugin metadata for the "View Details" modal.
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $manifest = self::fetch_manifest();
        if ( empty( $manifest ) ) {
            return $result;
        }

        return (object) array(
            'name'          => 'Threshold Easy Plugin and Theme Downloader',
            'slug'          => self::SLUG,
            'version'       => isset( $manifest['version'] ) ? (string) $manifest['version'] : TED_VERSION,
            'author'        => $manifest['author'] ?? '',
            'homepage'      => $manifest['homepage'] ?? '',
            'requires'      => $manifest['requires'] ?? '6.0',
            'tested'        => $manifest['tested'] ?? '',
            'requires_php'  => $manifest['requires_php'] ?? '7.4',
            'last_updated'  => $manifest['last_updated'] ?? '',
            'sections'      => isset( $manifest['sections'] ) && is_array( $manifest['sections'] )
                ? $manifest['sections']
                : array( 'description' => __( 'Download any installed plugin or theme as a ZIP file.', 'threshold-easy-downloader' ) ),
            'download_link' => $manifest['download_url'] ?? '',
            'icons'         => ( isset( $manifest['icons'] ) && is_array( $manifest['icons'] ) ) ? $manifest['icons'] : array(),
        );
    }

    /**
     * Clear the manifest cache after a plugin update completes, so the
     * next check pulls fresh data.
     */
    public static function clear_cache_after_update( $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return;
        }
        if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
            return;
        }
        $self = plugin_basename( TED_PLUGIN_FILE );
        if ( in_array( $self, $hook_extra['plugins'], true ) ) {
            delete_site_transient( self::CACHE_KEY );
        }
    }

    /**
     * Force a manifest re-fetch. Called by the admin "Check Now" button.
     *
     * Returns one of:
     *   'update_available' — manifest version > installed version
     *   'up_to_date'       — manifest version <= installed version
     *   'fetch_failed'     — couldn't reach the manifest URL or it was malformed
     */
    public static function force_check() {
        delete_site_transient( self::CACHE_KEY );
        $manifest = self::fetch_manifest( true );

        // Refresh WordPress's own plugin-update data so the Plugins screen
        // agrees with this answer straight away, without throwing away every
        // other plugin's cached check.
        if ( ! function_exists( 'wp_update_plugins' ) ) {
            require_once ABSPATH . 'wp-includes/update.php';
        }
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( true );
        }
        wp_update_plugins();

        if ( empty( $manifest ) || empty( $manifest['version'] ) ) {
            return 'fetch_failed';
        }
        if ( version_compare( (string) $manifest['version'], TED_VERSION, '>' ) ) {
            return 'update_available';
        }
        return 'up_to_date';
    }
}
