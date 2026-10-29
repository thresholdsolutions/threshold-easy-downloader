<?php
/**
 * Uninstall cleanup for Threshold Easy Plugin and Theme Downloader.
 *
 * Runs when the plugin is DELETED from the Plugins screen. The plugin stores no
 * settings, so everything it ever wrote is removed here:
 *   - the cached update manifest and the directory-size cache (site transients)
 *   - the one-time auto-update backfill flag
 *   - its own entry in WordPress's auto_update_plugins list (core does not
 *     remove deleted plugins from that list)
 *
 * @package ThresholdEasyDownloader
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_site_transient( 'ted_update_manifest' );
delete_transient( 'ted_update_manifest' ); // pre-1.1.0 installs cached per site.
delete_transient( 'ted_dir_sizes' );
delete_site_option( 'ted_autoupdate_backfilled' );
delete_option( 'ted_autoupdate_backfilled' );

$ted_basename = 'threshold-easy-downloader/threshold-easy-downloader.php';
$ted_list     = get_site_option( 'auto_update_plugins', array() );
if ( is_array( $ted_list ) && in_array( $ted_basename, $ted_list, true ) ) {
    update_site_option( 'auto_update_plugins', array_values( array_diff( $ted_list, array( $ted_basename ) ) ) );
}
