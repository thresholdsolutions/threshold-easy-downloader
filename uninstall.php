<?php
/**
 * Uninstall cleanup for Threshold Easy Downloader.
 *
 * Runs when the plugin is DELETED from the Plugins screen. The plugin stores no
 * settings, so everything it ever wrote is removed here, on every site of a
 * multisite network:
 *   - the directory-size cache (transient)
 *   - leftovers from versions before 1.2.1 (the old size-cache name) and from the
 *     self-hosted updater used before 1.2.0 (update manifest, backfill flag)
 *
 * @package ThresholdEasyDownloader
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove this plugin's data from the current site.
 */
function threshold_ed_uninstall_site() {
	delete_transient( 'threshold_ed_dir_sizes' );
	delete_transient( 'ted_dir_sizes' ); // Name used before 1.2.1.
	delete_transient( 'ted_update_manifest' ); // Pre-1.1.0 installs cached this per site.
	delete_option( 'ted_autoupdate_backfilled' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $threshold_ed_site_id ) {
		switch_to_blog( $threshold_ed_site_id );
		threshold_ed_uninstall_site();
		restore_current_blog();
	}
} else {
	threshold_ed_uninstall_site();
}

// Network-wide leftovers from the self-hosted updater (before 1.2.0).
delete_site_transient( 'ted_update_manifest' );
delete_site_option( 'ted_autoupdate_backfilled' );
