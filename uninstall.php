<?php
/**
 * Uninstall cleanup for Threshold Easy Downloader.
 *
 * Runs when the plugin is DELETED from the Plugins screen. The plugin stores no
 * settings, so everything it ever wrote is removed here:
 *   - the directory-size cache (transient)
 *   - leftovers from the self-hosted updater used before 1.2.0: the cached
 *     update manifest and the auto-update backfill flag
 *
 * @package ThresholdEasyDownloader
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_site_transient( 'ted_update_manifest' );
delete_transient( 'ted_update_manifest' ); // pre-1.1.0 installs cached per site.
delete_transient( 'ted_dir_sizes' );
delete_site_option( 'ted_autoupdate_backfilled' );
delete_option( 'ted_autoupdate_backfilled' );
