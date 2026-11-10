<?php
/**
 * Branded Tools page for Threshold Easy Downloader.
 *
 * Lists every installed plugin and theme with a one-click "Download ZIP"
 * button. Styling mirrors the Threshold Login Guard admin UI (ink/gold
 * brand, Ubuntu Sans, header card with gold offset shadow).
 *
 * @package ThresholdEasyDownloader
 */

defined( 'ABSPATH' ) || exit;

class Threshold_ED_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( THRESHOLD_ED_PLUGIN_FILE ), array( __CLASS__, 'settings_link' ) );
    }

    /**
     * Tools page assets (font, styles, filter script), loaded only on that page.
     */
    public static function enqueue( $hook ) {
        if ( 'tools_page_' . THRESHOLD_ED_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'threshold-ed-ubuntu-sans', plugins_url( 'assets/fonts/ubuntu-sans.css', THRESHOLD_ED_PLUGIN_FILE ), array(), THRESHOLD_ED_VERSION );
        wp_enqueue_style( 'threshold-ed-admin', plugins_url( 'assets/css/admin.css', THRESHOLD_ED_PLUGIN_FILE ), array( 'threshold-ed-ubuntu-sans' ), THRESHOLD_ED_VERSION );
        wp_enqueue_script( 'threshold-ed-admin', plugins_url( 'assets/js/admin.js', THRESHOLD_ED_PLUGIN_FILE ), array( 'jquery' ), THRESHOLD_ED_VERSION, true );
        wp_localize_script(
            'threshold-ed-admin',
            'thresholdEdAdmin',
            array(
                /* translators: 1: number of rows shown, 2: total rows. */
                'shown' => __( '%1$d / %2$d shown', 'threshold-easy-downloader' ),
            )
        );
    }

    public static function register_page() {
        add_management_page(
            __( 'Threshold Easy Downloader', 'threshold-easy-downloader' ),
            __( 'Easy Downloader', 'threshold-easy-downloader' ),
            'install_plugins',
            THRESHOLD_ED_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    public static function settings_link( $links ) {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'tools.php?page=' . THRESHOLD_ED_SLUG ) ) . '">' . esc_html__( 'Open Downloader', 'threshold-easy-downloader' ) . '</a>'
        );
        return $links;
    }

    public static function render_page() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins        = get_plugins();
        $active_plugins = (array) get_option( 'active_plugins', array() );
        $themes         = wp_get_themes();
        $current_theme  = get_stylesheet();
        ?>
        <div class="wrap threshold-ed-wrap">
            <div class="threshold-ed-header">
                <img class="threshold-ed-logo" src="<?php echo esc_url( plugins_url( 'assets/logo.svg', THRESHOLD_ED_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'Threshold Solutions', 'threshold-easy-downloader' ); ?>" width="170" height="46">
                <div class="threshold-ed-head-main">
                    <div class="threshold-ed-title-row">
                        <h1><?php esc_html_e( 'Easy Plugin & Theme Downloader', 'threshold-easy-downloader' ); ?></h1>
                        <span class="threshold-ed-badge">v<?php echo esc_html( THRESHOLD_ED_VERSION ); ?></span>
                    </div>
                    <span class="threshold-ed-brand"><strong>Threshold Solutions, LLC</strong> &middot; <a href="<?php echo esc_url( THRESHOLD_ED_GUIDE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'User guide', 'threshold-easy-downloader' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a></span>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e( 'Download a ZIP of any installed plugin or theme, for backups, migrations, or moving a build between sites. Download links are also on the Plugins list. A child theme ZIP contains only the child; download its parent separately. Symbolic links inside a folder are left out.', 'threshold-easy-downloader' ); ?>
            </p>

            <div class="threshold-ed-toolbar">
                <label for="threshold_ed_filter"><?php esc_html_e( 'Filter', 'threshold-easy-downloader' ); ?></label>
                <input type="search" id="threshold_ed_filter" placeholder="<?php esc_attr_e( 'Type part of a plugin or theme name', 'threshold-easy-downloader' ); ?>">
                <span class="threshold-ed-count" id="threshold_ed_count" aria-live="polite"></span>
            </div>

            <h2 class="threshold-ed-section" id="threshold_ed_h_plugins"><?php esc_html_e( 'Plugins', 'threshold-easy-downloader' ); ?> <span class="threshold-ed-chip"><?php echo esc_html( count( $plugins ) ); ?></span></h2>
            <table class="widefat striped" id="threshold_ed_plugins" aria-labelledby="threshold_ed_h_plugins">
                <caption><?php esc_html_e( 'Installed plugins', 'threshold-easy-downloader' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Plugin', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-version"><?php esc_html_e( 'Version', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-status"><?php esc_html_e( 'Status', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-size"><?php esc_html_e( 'Size', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Download', 'threshold-easy-downloader' ); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $plugins as $file => $data ) :
                    $dirname   = dirname( $file );
                    $slug      = ( '.' === $dirname ) ? $file : $dirname;
                    $source    = ( '.' === $dirname ) ? WP_PLUGIN_DIR . '/' . $file : WP_PLUGIN_DIR . '/' . $dirname;
                    $is_active = in_array( $file, $active_plugins, true ) || ( is_multisite() && is_plugin_active_for_network( $file ) );
                    $name      = isset( $data['Name'] ) && '' !== $data['Name'] ? $data['Name'] : $slug;
                    ?>
                    <tr>
                        <td>
                            <span class="threshold-ed-name"><?php echo esc_html( $name ); ?></span>
                            <span class="threshold-ed-slug"><?php echo esc_html( $slug ); ?></span>
                        </td>
                        <td class="threshold-ed-col-version"><?php echo esc_html( ! empty( $data['Version'] ) ? $data['Version'] : '—' ); ?></td>
                        <td class="threshold-ed-col-status">
                            <span class="threshold-ed-state <?php echo $is_active ? 'is-on' : 'is-off'; ?>">
                                <?php $is_active ? esc_html_e( 'Active', 'threshold-easy-downloader' ) : esc_html_e( 'Inactive', 'threshold-easy-downloader' ); ?>
                            </span>
                        </td>
                        <td class="threshold-ed-col-size"><?php echo esc_html( Threshold_ED_Zipper::dir_size( $source ) ); ?></td>
                        <td class="threshold-ed-col-action">
                            <a class="threshold-ed-dl" href="<?php echo esc_url( Threshold_ED_Zipper::download_url( 'plugin', $file ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name */ __( 'Download ZIP of %s', 'threshold-easy-downloader' ), $name ) ); ?>">&#8681; <?php esc_html_e( 'Download ZIP', 'threshold-easy-downloader' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( current_user_can( 'install_themes' ) ) : ?>
            <h2 class="threshold-ed-section" id="threshold_ed_h_themes"><?php esc_html_e( 'Themes', 'threshold-easy-downloader' ); ?> <span class="threshold-ed-chip"><?php echo esc_html( count( $themes ) ); ?></span></h2>
            <table class="widefat striped" id="threshold_ed_themes" aria-labelledby="threshold_ed_h_themes">
                <caption><?php esc_html_e( 'Installed themes', 'threshold-easy-downloader' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Theme', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-version"><?php esc_html_e( 'Version', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-status"><?php esc_html_e( 'Status', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-size"><?php esc_html_e( 'Size', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="threshold-ed-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Download', 'threshold-easy-downloader' ); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $themes as $stylesheet => $theme ) :
                    $is_current = ( $stylesheet === $current_theme );
                    $parent     = $theme->parent();
                    $tname      = $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $stylesheet;
                    ?>
                    <tr>
                        <td>
                            <span class="threshold-ed-name"><?php echo esc_html( $tname ); ?></span>
                            <span class="threshold-ed-slug"><?php echo esc_html( $stylesheet ); ?><?php if ( $parent ) : ?> &middot; <?php echo esc_html( sprintf( /* translators: %s: parent theme name */ __( 'child of %s', 'threshold-easy-downloader' ), $parent->get( 'Name' ) ) ); ?><?php endif; ?></span>
                        </td>
                        <td class="threshold-ed-col-version"><?php echo esc_html( $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '—' ); ?></td>
                        <td class="threshold-ed-col-status">
                            <span class="threshold-ed-state <?php echo $is_current ? 'is-on' : 'is-off'; ?>">
                                <?php $is_current ? esc_html_e( 'Active', 'threshold-easy-downloader' ) : esc_html_e( 'Installed', 'threshold-easy-downloader' ); ?>
                            </span>
                        </td>
                        <td class="threshold-ed-col-size"><?php echo esc_html( Threshold_ED_Zipper::dir_size( $theme->get_stylesheet_directory() ) ); ?></td>
                        <td class="threshold-ed-col-action">
                            <a class="threshold-ed-dl" href="<?php echo esc_url( Threshold_ED_Zipper::download_url( 'theme', $stylesheet ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: theme name */ __( 'Download ZIP of %s', 'threshold-easy-downloader' ), $tname ) ); ?>">&#8681; <?php esc_html_e( 'Download ZIP', 'threshold-easy-downloader' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="threshold-ed-footer">
                <span><strong><?php esc_html_e( 'Threshold Easy Downloader', 'threshold-easy-downloader' ); ?></strong> &middot; <?php esc_html_e( 'by Spencer Taylor, Threshold Solutions, LLC', 'threshold-easy-downloader' ); ?></span>
                <span class="sep" aria-hidden="true">|</span>
                <a href="<?php echo esc_url( THRESHOLD_ED_GUIDE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'User guide', 'threshold-easy-downloader' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a>
                <span class="sep" aria-hidden="true">|</span>
                <a href="<?php echo esc_url( 'https://spencer-taylor.com/' ); ?>" target="_blank" rel="noopener">spencer-taylor.com <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a>
            </div>
        </div>

        <?php
    }
}
