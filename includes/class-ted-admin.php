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

class TED_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TED_PLUGIN_FILE ), array( __CLASS__, 'settings_link' ) );
    }

    /**
     * Brand font, loaded the WordPress way on this page only (never a raw
     * <link> inside the page body).
     */
    public static function enqueue( $hook ) {
        if ( 'tools_page_' . TED_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style( 'ted-ubuntu-sans', 'https://fonts.googleapis.com/css2?family=Ubuntu+Sans:wght@400;500;600;700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
    }

    public static function register_page() {
        add_management_page(
            __( 'Threshold Easy Downloader', 'threshold-easy-downloader' ),
            __( 'Easy Downloader', 'threshold-easy-downloader' ),
            'install_plugins',
            TED_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    public static function settings_link( $links ) {
        array_unshift(
            $links,
            '<a href="' . esc_url( admin_url( 'tools.php?page=' . TED_SLUG ) ) . '">' . esc_html__( 'Open Downloader', 'threshold-easy-downloader' ) . '</a>'
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
        <div class="wrap ted-wrap">
            <style>
                .ted-wrap{--ink:#222222;--gold:#ffc966;--gold-dk:#ffb733;--band:#f5f5f5;--line:#222222;max-width:1060px;font-family:"Ubuntu Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.5;color:var(--ink);}
                .ted-wrap p,.ted-wrap th,.ted-wrap td,.ted-wrap label,.ted-wrap h1,.ted-wrap h2,.ted-wrap h3,.ted-wrap .button,.ted-wrap input,.ted-wrap select,.ted-wrap span,.ted-wrap a{font-family:inherit;color:var(--ink);}
                .ted-wrap p,.ted-wrap td,.ted-wrap th,.ted-wrap label,.ted-wrap input,.ted-wrap .button{font-size:16px;}
                .ted-wrap a{text-decoration:underline;}
                .ted-wrap a:hover,.ted-wrap a:focus{background:var(--gold);color:var(--ink);}
                .ted-wrap .ted-header{display:flex;align-items:center;flex-wrap:wrap;gap:18px 24px;background:#fff;border:2px solid var(--ink);border-radius:0;padding:22px 26px;margin:18px 0 8px;box-shadow:8px 8px 0 0 var(--gold);}
                .ted-wrap .ted-logo{height:46px;width:auto;display:block;flex:0 0 auto;}
                .ted-wrap .ted-head-main{display:flex;flex-direction:column;gap:6px;flex:1 1 auto;min-width:220px;}
                .ted-wrap .ted-title-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
                .ted-wrap .ted-header h1{margin:0;padding:0;color:var(--ink);font-size:24px;font-weight:700;line-height:1.15;letter-spacing:-.3px;}
                .ted-wrap .ted-badge{display:inline-block;background:var(--gold);color:var(--ink);font-size:16px;font-weight:700;line-height:1;padding:6px 13px;border:2px solid var(--ink);border-radius:90px;}
                .ted-wrap .ted-brand{font-size:16px;color:var(--ink);font-weight:500;}
                .ted-wrap .ted-brand strong{color:var(--ink);font-weight:700;}
                .ted-wrap .description{max-width:860px;margin:14px 2px 4px;color:var(--ink);font-size:16px;line-height:1.6;}
                .ted-wrap .ted-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:20px 0 4px;}
                .ted-wrap .ted-toolbar label{font-weight:600;}
                .ted-wrap .ted-toolbar input[type=search]{border:2px solid var(--ink);border-radius:90px;padding:5px 16px;min-width:260px;font-size:16px;color:var(--ink);}
                .ted-wrap .ted-toolbar input[type=search]:focus{border-color:var(--ink);box-shadow:0 0 0 3px var(--gold);outline:none;}
                .ted-wrap .ted-count{font-size:16px;color:var(--ink);font-weight:600;}
                .ted-wrap h2.ted-section{font-size:22px;font-weight:700;color:var(--ink);margin:30px 0 10px;display:flex;align-items:center;gap:10px;}
                .ted-wrap h2.ted-section .ted-chip{background:var(--band);color:var(--ink);border:1px solid var(--ink);font-size:16px;font-weight:700;padding:3px 10px;border-radius:90px;}
                .ted-wrap .widefat{border:1px solid var(--ink);border-radius:8px;overflow:hidden;box-shadow:none;}
                .ted-wrap .widefat caption{position:absolute;left:-9999px;}
                .ted-wrap .widefat th,.ted-wrap .widefat td{font-size:16px;}
                .ted-wrap .widefat thead th{background:var(--ink);color:#fff;font-weight:700;}
                .ted-wrap .widefat td{vertical-align:middle;}
                .ted-wrap .widefat .ted-col-action{text-align:right;}
                .ted-wrap .ted-name{font-weight:700;color:var(--ink);}
                .ted-wrap .ted-slug{display:block;font-size:.8em;color:var(--ink);font-weight:400;font-family:Consolas,Monaco,monospace;}
                .ted-wrap .ted-state{display:inline-block;font-size:.8em;font-weight:700;line-height:1;padding:5px 10px;border-radius:90px;border:1px solid var(--ink);color:var(--ink);}
                .ted-wrap .ted-state.is-on{background:var(--gold);}
                .ted-wrap .ted-state.is-off{background:#fff;}
                .ted-wrap .ted-dl{background:var(--gold);border:2px solid var(--ink);color:var(--ink);border-radius:90px;font-weight:700;padding:4px 18px;text-decoration:none;display:inline-block;line-height:1.6;font-size:16px;transition:background .12s,transform .04s;white-space:nowrap;}
                .ted-wrap .ted-dl:hover,.ted-wrap .ted-dl:focus{background:var(--gold-dk);color:var(--ink);}
                .ted-wrap .ted-dl:focus{box-shadow:0 0 0 2px #fff,0 0 0 4px var(--ink);outline:none;}
                .ted-wrap .ted-dl:active{transform:translateY(1px);}
                .ted-wrap .button:not(.button-primary){background:#fff;border:2px solid var(--ink);color:var(--ink);border-radius:90px;font-weight:600;padding:2px 20px;height:auto;line-height:2;transition:background .12s;}
                .ted-wrap .button:not(.button-primary):hover,.ted-wrap .button:not(.button-primary):focus{background:var(--gold);color:var(--ink);border-color:var(--ink);}
                .ted-wrap .ted-result{margin-top:12px;padding:12px 14px;border-radius:4px;border:1px solid var(--ink);background:#fff;font-size:16px;line-height:1.7;display:none;color:var(--ink);}
                .ted-wrap .ted-result.is-success{border-left:6px solid var(--gold);}
                .ted-wrap .ted-result.is-error{border-left:6px solid var(--ink);background:var(--band);}
                .ted-wrap .ted-footer{display:flex;align-items:center;flex-wrap:wrap;gap:10px 14px;margin:32px 0 10px;padding:16px 22px;background:var(--ink);border-radius:8px;color:#fff;font-size:16px;line-height:1.5;}
                .ted-wrap .ted-footer span,.ted-wrap .ted-footer strong{color:#fff;}
                .ted-wrap .ted-footer a{color:var(--gold);font-weight:700;text-decoration:underline;}
                .ted-wrap .ted-footer a:hover,.ted-wrap .ted-footer a:focus{background:var(--gold);color:var(--ink);}
                .ted-wrap .ted-footer .sep{opacity:.5;}
                @media (max-width:782px){
                  .ted-wrap .ted-header{padding:18px;box-shadow:5px 5px 0 0 var(--gold);gap:14px;}
                  .ted-wrap .ted-logo{height:38px;}
                  .ted-wrap .ted-toolbar input[type=search]{min-width:0;flex:1 1 auto;}
                  .ted-wrap .widefat .ted-col-size{display:none;}
                }
                @media (max-width:480px){
                  .ted-wrap .widefat .ted-col-version{display:none;}
                  .ted-wrap .widefat .ted-col-status{display:none;}
                  .ted-wrap .widefat td,.ted-wrap .widefat th{padding-left:8px;padding-right:8px;}
                  .ted-wrap .widefat td:first-child{word-break:break-word;}
                  .ted-wrap .ted-slug{word-break:break-all;}
                  .ted-wrap .ted-dl{padding:4px 12px;}
                }
            </style>

            <div class="ted-header">
                <img class="ted-logo" src="<?php echo esc_url( plugins_url( 'assets/logo.svg', TED_PLUGIN_FILE ) ); ?>" alt="<?php esc_attr_e( 'Threshold Solutions', 'threshold-easy-downloader' ); ?>" width="170" height="46">
                <div class="ted-head-main">
                    <div class="ted-title-row">
                        <h1><?php esc_html_e( 'Easy Plugin & Theme Downloader', 'threshold-easy-downloader' ); ?></h1>
                        <span class="ted-badge">v<?php echo esc_html( TED_VERSION ); ?></span>
                    </div>
                    <span class="ted-brand"><strong>Threshold Solutions, LLC</strong> &middot; <a href="<?php echo esc_url( TED_GUIDE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'User guide', 'threshold-easy-downloader' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a></span>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e( 'Download a ZIP of any installed plugin or theme, for backups, migrations, or moving a build between sites. Download links are also on the Plugins list. A child theme ZIP contains only the child; download its parent separately. Symbolic links inside a folder are left out.', 'threshold-easy-downloader' ); ?>
            </p>

            <div class="ted-toolbar">
                <label for="ted_filter"><?php esc_html_e( 'Filter', 'threshold-easy-downloader' ); ?></label>
                <input type="search" id="ted_filter" placeholder="<?php esc_attr_e( 'Type part of a plugin or theme name', 'threshold-easy-downloader' ); ?>">
                <span class="ted-count" id="ted_count" aria-live="polite"></span>
            </div>

            <h2 class="ted-section" id="ted_h_plugins"><?php esc_html_e( 'Plugins', 'threshold-easy-downloader' ); ?> <span class="ted-chip"><?php echo esc_html( count( $plugins ) ); ?></span></h2>
            <table class="widefat striped" id="ted_plugins" aria-labelledby="ted_h_plugins">
                <caption><?php esc_html_e( 'Installed plugins', 'threshold-easy-downloader' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Plugin', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-version"><?php esc_html_e( 'Version', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-status"><?php esc_html_e( 'Status', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-size"><?php esc_html_e( 'Size', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Download', 'threshold-easy-downloader' ); ?></span></th>
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
                            <span class="ted-name"><?php echo esc_html( $name ); ?></span>
                            <span class="ted-slug"><?php echo esc_html( $slug ); ?></span>
                        </td>
                        <td class="ted-col-version"><?php echo esc_html( ! empty( $data['Version'] ) ? $data['Version'] : '—' ); ?></td>
                        <td class="ted-col-status">
                            <span class="ted-state <?php echo $is_active ? 'is-on' : 'is-off'; ?>">
                                <?php $is_active ? esc_html_e( 'Active', 'threshold-easy-downloader' ) : esc_html_e( 'Inactive', 'threshold-easy-downloader' ); ?>
                            </span>
                        </td>
                        <td class="ted-col-size"><?php echo esc_html( TED_Zipper::dir_size( $source ) ); ?></td>
                        <td class="ted-col-action">
                            <a class="ted-dl" href="<?php echo esc_url( TED_Zipper::download_url( 'plugin', $file ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name */ __( 'Download ZIP of %s', 'threshold-easy-downloader' ), $name ) ); ?>">&#8681; <?php esc_html_e( 'Download ZIP', 'threshold-easy-downloader' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="ted-section" id="ted_h_themes"><?php esc_html_e( 'Themes', 'threshold-easy-downloader' ); ?> <span class="ted-chip"><?php echo esc_html( count( $themes ) ); ?></span></h2>
            <table class="widefat striped" id="ted_themes" aria-labelledby="ted_h_themes">
                <caption><?php esc_html_e( 'Installed themes', 'threshold-easy-downloader' ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Theme', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-version"><?php esc_html_e( 'Version', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-status"><?php esc_html_e( 'Status', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-size"><?php esc_html_e( 'Size', 'threshold-easy-downloader' ); ?></th>
                        <th scope="col" class="ted-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Download', 'threshold-easy-downloader' ); ?></span></th>
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
                            <span class="ted-name"><?php echo esc_html( $tname ); ?></span>
                            <span class="ted-slug"><?php echo esc_html( $stylesheet ); ?><?php if ( $parent ) : ?> &middot; <?php echo esc_html( sprintf( /* translators: %s: parent theme name */ __( 'child of %s', 'threshold-easy-downloader' ), $parent->get( 'Name' ) ) ); ?><?php endif; ?></span>
                        </td>
                        <td class="ted-col-version"><?php echo esc_html( $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '—' ); ?></td>
                        <td class="ted-col-status">
                            <span class="ted-state <?php echo $is_current ? 'is-on' : 'is-off'; ?>">
                                <?php $is_current ? esc_html_e( 'Active', 'threshold-easy-downloader' ) : esc_html_e( 'Installed', 'threshold-easy-downloader' ); ?>
                            </span>
                        </td>
                        <td class="ted-col-size"><?php echo esc_html( TED_Zipper::dir_size( $theme->get_stylesheet_directory() ) ); ?></td>
                        <td class="ted-col-action">
                            <a class="ted-dl" href="<?php echo esc_url( TED_Zipper::download_url( 'theme', $stylesheet ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: theme name */ __( 'Download ZIP of %s', 'threshold-easy-downloader' ), $tname ) ); ?>">&#8681; <?php esc_html_e( 'Download ZIP', 'threshold-easy-downloader' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="ted-footer">
                <span><strong><?php esc_html_e( 'Threshold Easy Downloader', 'threshold-easy-downloader' ); ?></strong> &middot; <?php esc_html_e( 'by Spencer Taylor, Threshold Solutions, LLC', 'threshold-easy-downloader' ); ?></span>
                <span class="sep" aria-hidden="true">|</span>
                <a href="<?php echo esc_url( TED_GUIDE_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'User guide', 'threshold-easy-downloader' ); ?> <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a>
                <span class="sep" aria-hidden="true">|</span>
                <a href="<?php echo esc_url( 'https://spencer-taylor.com/' ); ?>" target="_blank" rel="noopener">spencer-taylor.com <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'threshold-easy-downloader' ); ?></span></a>
            </div>
        </div>

        <script>
        (function($){
            // Live filter across both tables.
            var $rows  = $('#ted_plugins tbody tr, #ted_themes tbody tr');
            var $count = $('#ted_count');
            var total  = $rows.length;

            function updateCount(visible){
                $count.text( visible === total ? '' : visible + ' / ' + total + ' shown' );
            }
            updateCount(total);

            $('#ted_filter').on('input', function(){
                var q = $(this).val().toLowerCase().trim();
                var visible = 0;
                $rows.each(function(){
                    var hit = !q || $(this).text().toLowerCase().indexOf(q) !== -1;
                    $(this).toggle(hit);
                    if (hit) visible++;
                });
                updateCount(visible);
            });
        }(jQuery));
        </script>
        <?php
    }
}
