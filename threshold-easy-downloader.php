<?php
/**
 * Plugin Name: Threshold Easy Downloader
 * Plugin URI:  https://spencer-taylor.com/threshold-easy-downloader/
 * Description: Download any installed plugin or theme as a ZIP file, from the Plugins list or the Tools page. Built by Threshold Solutions, LLC.
 * Version:     1.2.1
 * Author:      Threshold Solutions, LLC
 * Author URI:  https://spencer-taylor.com
 * License:     GPL-2.0-or-later
 * Text Domain: threshold-easy-downloader
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'THRESHOLD_ED_VERSION',     '1.2.1' );
define( 'THRESHOLD_ED_SLUG',        'threshold-easy-downloader' );
define( 'THRESHOLD_ED_PLUGIN_FILE', __FILE__ );
define( 'THRESHOLD_ED_GUIDE_URL',   'https://spencer-taylor.com/threshold-easy-downloader/#guide' );

require_once __DIR__ . '/includes/class-threshold-ed-zipper.php';
require_once __DIR__ . '/includes/class-threshold-ed-admin.php';

Threshold_ED_Admin::init();

// ─── Download handler (admin-post.php?action=threshold_ed_download) ──────────────────

add_action( 'admin_post_threshold_ed_download', array( 'Threshold_ED_Zipper', 'handle_download' ) );

// ─── "Download ZIP" row action on the Plugins list ───────────────────────────

add_filter( 'plugin_action_links', 'threshold_ed_plugin_row_action', 10, 4 );
add_filter( 'network_admin_plugin_action_links', 'threshold_ed_plugin_row_action', 10, 4 );

/**
 * @param string[] $actions
 * @param string   $plugin_file
 * @param array    $plugin_data
 * @param string   $context  'all', 'active', ... or 'mustuse' / 'dropins'.
 */
function threshold_ed_plugin_row_action( $actions, $plugin_file, $plugin_data = array(), $context = '' ) {
    // Must-use plugins and drop-ins are not in get_plugins(), so the download
    // would fail validation; do not offer it there.
    if ( in_array( $context, array( 'mustuse', 'dropins' ), true ) ) {
        return $actions;
    }
    if ( ! current_user_can( 'install_plugins' ) ) {
        return $actions;
    }
    $actions['threshold_ed_download'] = sprintf(
        '<a href="%s" class="threshold-ed-row-action">%s</a>',
        esc_url( Threshold_ED_Zipper::download_url( 'plugin', $plugin_file ) ),
        esc_html__( 'Download ZIP', 'threshold-easy-downloader' )
    );
    return $actions;
}

// ─── "Download ZIP" row action on the network Themes list (multisite) ───────

add_filter( 'theme_action_links', 'threshold_ed_theme_row_action', 10, 2 );

function threshold_ed_theme_row_action( $actions, $theme ) {
    if ( ! current_user_can( 'install_themes' ) || ! ( $theme instanceof WP_Theme ) ) {
        return $actions;
    }
    $actions['threshold_ed_download'] = sprintf(
        '<a href="%s" class="threshold-ed-row-action">%s</a>',
        esc_url( Threshold_ED_Zipper::download_url( 'theme', $theme->get_stylesheet() ) ),
        esc_html__( 'Download ZIP', 'threshold-easy-downloader' )
    );
    return $actions;
}

// Row-action styling (brand charcoal, underlined like every other Threshold link),
// loaded only on the Plugins and Themes screens.
add_action( 'admin_enqueue_scripts', 'threshold_ed_row_action_css' );
function threshold_ed_row_action_css( $hook ) {
    if ( ! in_array( $hook, array( 'plugins.php', 'themes.php' ), true ) ) {
        return;
    }
    wp_enqueue_style( 'threshold-ed-row-actions', plugins_url( 'assets/css/row-actions.css', THRESHOLD_ED_PLUGIN_FILE ), array(), THRESHOLD_ED_VERSION );
}
