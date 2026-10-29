<?php
/**
 * Plugin Name: Threshold Easy Plugin and Theme Downloader
 * Plugin URI:  https://spencer-taylor.com/threshold-easy-downloader/
 * Description: Download any installed plugin or theme as a ZIP file, from the Plugins list or the Tools page. Built by Threshold Solutions, LLC.
 * Version:     1.1.0
 * Author:      Threshold Solutions, LLC
 * Author URI:  https://spencer-taylor.com
 * License:     GPL-2.0-or-later
 * Text Domain: threshold-easy-downloader
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'TED_VERSION',     '1.1.0' );
define( 'TED_SLUG',        'threshold-easy-downloader' );
define( 'TED_PLUGIN_FILE', __FILE__ );
define( 'TED_GUIDE_URL',   'https://spencer-taylor.com/threshold-easy-downloader/#guide' );

require_once __DIR__ . '/includes/class-ted-zipper.php';
require_once __DIR__ . '/includes/class-ted-admin.php';
require_once __DIR__ . '/includes/class-ted-updater.php';

TED_Admin::init();
TED_Updater::init();

// ─── Download handler (admin-post.php?action=ted_download) ──────────────────

add_action( 'admin_post_ted_download', array( 'TED_Zipper', 'handle_download' ) );

// ─── "Download ZIP" row action on the Plugins list ───────────────────────────

add_filter( 'plugin_action_links', 'ted_plugin_row_action', 10, 4 );
add_filter( 'network_admin_plugin_action_links', 'ted_plugin_row_action', 10, 4 );

/**
 * @param string[] $actions
 * @param string   $plugin_file
 * @param array    $plugin_data
 * @param string   $context  'all', 'active', ... or 'mustuse' / 'dropins'.
 */
function ted_plugin_row_action( $actions, $plugin_file, $plugin_data = array(), $context = '' ) {
    // Must-use plugins and drop-ins are not in get_plugins(), so the download
    // would fail validation; do not offer it there.
    if ( in_array( $context, array( 'mustuse', 'dropins' ), true ) ) {
        return $actions;
    }
    if ( ! current_user_can( 'install_plugins' ) ) {
        return $actions;
    }
    $actions['ted_download'] = sprintf(
        '<a href="%s" class="ted-row-action">%s</a>',
        esc_url( TED_Zipper::download_url( 'plugin', $plugin_file ) ),
        esc_html__( 'Download ZIP', 'threshold-easy-downloader' )
    );
    return $actions;
}

// ─── "Download ZIP" row action on the network Themes list (multisite) ───────

add_filter( 'theme_action_links', 'ted_theme_row_action', 10, 2 );

function ted_theme_row_action( $actions, $theme ) {
    if ( ! current_user_can( 'install_themes' ) || ! ( $theme instanceof WP_Theme ) ) {
        return $actions;
    }
    $actions['ted_download'] = sprintf(
        '<a href="%s" class="ted-row-action">%s</a>',
        esc_url( TED_Zipper::download_url( 'theme', $theme->get_stylesheet() ) ),
        esc_html__( 'Download ZIP', 'threshold-easy-downloader' )
    );
    return $actions;
}

// Row-action styling (brand charcoal, underlined like every other Threshold link).
add_action( 'admin_head-plugins.php', 'ted_row_action_css' );
add_action( 'admin_head-themes.php', 'ted_row_action_css' );
add_action( 'admin_head-plugins-network.php', 'ted_row_action_css' );
add_action( 'admin_head-themes-network.php', 'ted_row_action_css' );
function ted_row_action_css() {
    echo '<style>.row-actions .ted_download a.ted-row-action{color:#222222;font-weight:600;text-decoration:underline;}.row-actions .ted_download a.ted-row-action:hover,.row-actions .ted_download a.ted-row-action:focus{color:#222222;background:#ffc966;}</style>';
}

// ─── AJAX: check for plugin updates (Threshold update server) ────────────────

add_action( 'wp_ajax_ted_check_update', function() {
    check_ajax_referer( 'ted_ajax', 'nonce' );
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( __( 'You are not allowed to check for updates.', 'threshold-easy-downloader' ), 403 );
    }

    switch ( TED_Updater::force_check() ) {
        case 'update_available':
            wp_send_json_success( __( 'Update available. Visit Plugins to install the new version.', 'threshold-easy-downloader' ) );
            break;
        case 'up_to_date':
            /* translators: %s: version number */
            wp_send_json_success( sprintf( __( 'You are running the latest version (v%s).', 'threshold-easy-downloader' ), TED_VERSION ) );
            break;
        default:
            wp_send_json_error( __( 'Could not reach the update server. Try again in a few minutes.', 'threshold-easy-downloader' ) );
    }
} );

// --- Auto-updates -----------------------------------------------------------
//
// Threshold plugins ship their fixes through the self-hosted update channel, so
// they MUST be on WordPress's per-plugin auto-update list. WordPress defaults a
// newly installed plugin to auto-update OFF, which silently strands a site on an
// old release. This turns it on at activation and backfills existing installs
// once.
//
// The backfill is guarded by a flag so it runs a single time per network (the
// auto-update list is a network-wide site option, so the flag is one too): if
// the plugin is later switched off deliberately on the Plugins screen, that
// choice is respected rather than fought on every page load.

function ted_enable_auto_updates() {
	if ( ! defined( 'TED_PLUGIN_FILE' ) ) {
		return;
	}
	$basename = plugin_basename( TED_PLUGIN_FILE );
	$list     = get_site_option( 'auto_update_plugins', array() );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	if ( ! in_array( $basename, $list, true ) ) {
		$list[] = $basename;
		update_site_option( 'auto_update_plugins', $list );
	}
}
register_activation_hook( __FILE__, 'ted_enable_auto_updates' );

add_action(
	'plugins_loaded',
	function () {
		if ( get_site_option( 'ted_autoupdate_backfilled' ) ) {
			return;
		}
		ted_enable_auto_updates();
		update_site_option( 'ted_autoupdate_backfilled', 1 );
	},
	20
);
