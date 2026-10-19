<?php
/*
Plugin Name: WPC Product Videos for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Videos helps you add many videos for a product and linked to the feature image or product gallery images.
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-product-videos
Domain Path: /languages/
Requires Plugins: woocommerce
Version: 1.2.3
Requires at least: 5.9
WC requires at least: 3.0
WC tested up to: 11.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCPV_VERSION' ) && define( 'WPCPV_VERSION', '1.2.3' );
! defined( 'WPCPV_LITE' ) && define( 'WPCPV_LITE', __FILE__ );
! defined( 'WPCPV_FILE' ) && define( 'WPCPV_FILE', __FILE__ );
! defined( 'WPCPV_URI' ) && define( 'WPCPV_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCPV_DIR' ) && define( 'WPCPV_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCPV_REVIEWS' ) && define( 'WPCPV_REVIEWS', 'https://wordpress.org/support/plugin/wpc-product-videos/reviews/' );
! defined( 'WPCPV_CHANGELOG' ) && define( 'WPCPV_CHANGELOG', 'https://wordpress.org/plugins/wpc-product-videos/#developers' );
! defined( 'WPCPV_DISCUSSION' ) && define( 'WPCPV_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-product-videos' );

// WPC Core
require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
wpc_core_register( [
	'file'    => __FILE__,
	'version' => WPCPV_VERSION,
	'prefix'  => 'wpcpv',
] );

if ( ! function_exists( 'wpcpv_init' ) ) {
    add_action( 'plugins_loaded', 'wpcpv_init', 11 );

    function wpcpv_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'wpcpv_notice_wc' );

            return null;
        }

        if ( ! class_exists( 'WPCleverWpcpv' ) ) {
            class WPCleverWpcpv {
                protected static $instance = null;

                public static function instance() {
                    if ( is_null( self::$instance ) ) {
                        self::$instance = new self();
                    }

                    return self::$instance;
                }

                function __construct() {

                    // enqueue scripts
                    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
                    add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

                    // settings page
                    add_action( 'admin_menu', [ $this, 'admin_menu' ] );

                    // settings link
                    add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
                    add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

                    // meta data
                    add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_field_video' ], 10, 2 );
                    add_filter( 'attachment_fields_to_save', [ $this, 'attachment_field_video_save' ], 10, 2 );

                    // show videos
                    add_filter( 'woocommerce_single_product_image_thumbnail_html', [ $this, 'thumbnail_html' ], 10, 2 );
                }
                function enqueue_scripts() {
                    // light gallery
                    wp_enqueue_script( 'lightgallery', WPCPV_URI . 'assets/libs/lightgallery/js/lightgallery-all.min.js', [ 'jquery' ], WPCPV_VERSION, true );
                    wp_enqueue_style( 'lightgallery', WPCPV_URI . 'assets/libs/lightgallery/css/lightgallery.min.css', [], WPCPV_VERSION );

                    // feather
                    wp_enqueue_style( 'wpcpv-feather', WPCPV_URI . 'assets/libs/feather/feather.css', [], WPCPV_VERSION );

                    // main
                    wp_enqueue_style( 'wpcpv-frontend', WPCPV_URI . 'assets/css/frontend.css', [], WPCPV_VERSION );
                    wp_enqueue_script( 'wpcpv-frontend', WPCPV_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCPV_VERSION, true );
                }

                function admin_enqueue_scripts() {
                    // backend
                    wp_enqueue_style( 'wpcpv-backend', WPCPV_URI . 'assets/css/backend.css', [], WPCPV_VERSION );
                    wp_enqueue_script( 'wpcpv-backend', WPCPV_URI . 'assets/js/backend.js', [ 'jquery' ], WPCPV_VERSION, true );
                    wp_localize_script( 'wpcpv-backend', 'wpcpv_backend_vars', [
                        'saved_text' => esc_html__( 'Video URL saved successfully.', 'wpc-product-videos' )
                    ] );
                }

                function admin_menu() {
                    add_submenu_page( 'wpclever', esc_html__( 'WPC Product Videos', 'wpc-product-videos' ), esc_html__( 'Product Videos', 'wpc-product-videos' ), 'manage_options', 'wpclever-wpcpv', [
                            $this,
                            'admin_menu_content'
                    ] );
                }

                function admin_menu_content() {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'how' ) );
                    ?>
                    <div class="wpclever_settings_page wrap">
                        <div class="wpclever_settings_page_header">
                            <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                               target="_blank" title="Visit wpclever.net"></a>
                            <div class="wpclever_settings_page_header_text">
                                <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Videos', 'wpc-product-videos' ) . ' ' . esc_html( WPCPV_VERSION ); ?></div>
                                <div class="wpclever_settings_page_desc about-text">
                                    <p>
                                        <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-product-videos' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                        <br/>
                                        <a href="<?php echo esc_url( WPCPV_REVIEWS ); ?>"
                                           target="_blank"><?php esc_html_e( 'Reviews', 'wpc-product-videos' ); ?></a> |
                                        <a href="<?php echo esc_url( WPCPV_CHANGELOG ); ?>"
                                           target="_blank"><?php esc_html_e( 'Changelog', 'wpc-product-videos' ); ?></a>
                                        |
                                        <a href="<?php echo esc_url( WPCPV_DISCUSSION ); ?>"
                                           target="_blank"><?php esc_html_e( 'Discussion', 'wpc-product-videos' ); ?></a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <h2></h2>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpv&tab=how' ) ); ?>"
                                   class="<?php echo esc_attr( $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                                    <?php esc_html_e( 'How to use?', 'wpc-product-videos' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                                   class="nav-tab">
                                    <?php esc_html_e( 'Essential Kit', 'wpc-product-videos' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
                            <?php if ( $active_tab === 'how' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
                                        <?php esc_html_e( 'When set product image or add product gallery images you can add the video URL for each image. You also can do it when editing an image via Media Library.', 'wpc-product-videos' ); ?>
                                    </p>
                                    <p><img src="<?php echo esc_url( WPCPV_URI ); ?>assets/images/how-01.jpg" alt=""/></p>
                                    <p>
                                        <?php esc_html_e( 'Then the video will be linked to the image on the product page.', 'wpc-product-videos' ); ?>
                                    </p>
                                    <p><img src="<?php echo esc_url( WPCPV_URI ); ?>assets/images/how-02.jpg" alt=""/></p>
                                </div>
                            <?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please
                                    install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                        Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                        Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                        Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }

                function action_links( $links, $file ) {
                    static $plugin;

                    if ( ! isset( $plugin ) ) {
                        $plugin = plugin_basename( __FILE__ );
                    }

                    if ( $plugin === $file ) {
                        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpv&tab=how' ) ) . '">' . esc_html__( 'How to use?', 'wpc-product-videos' ) . '</a>';
                        array_unshift( $links, $settings_link );
                    }

                    return (array) $links;
                }

                function row_meta( $links, $file ) {
                    static $plugin;

                    if ( ! isset( $plugin ) ) {
                        $plugin = plugin_basename( __FILE__ );
                    }

                    if ( $plugin === $file ) {
                        $row_meta = [
                                'support' => '<a href="' . esc_url( WPCPV_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-product-videos' ) . '</a>',
                        ];

                        return array_merge( $links, $row_meta );
                    }

                    return (array) $links;
                }

                function attachment_field_video( $form_fields, $post ) {
                    $form_fields['wpcpv-video-url'] = [
                            'label' => esc_html__( 'WPC Product Videos', 'wpc-product-videos' ),
                            'input' => 'text',
                            'value' => get_post_meta( $post->ID, 'wpcpv_video_url', true ),
                            'helps' => esc_html__( 'Youtube/Vimeo URL.', 'wpc-product-videos' )
                    ];

                    return $form_fields;
                }

                function attachment_field_video_save( $post, $attachment ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    if ( current_user_can( 'edit_post', $post['ID'] ) && isset( $attachment['wpcpv-video-url'] ) ) {
                        update_post_meta( $post['ID'], 'wpcpv_video_url', esc_url( $attachment['wpcpv-video-url'] ) );
                    }

                    return $post;
                }

                function thumbnail_html( $html, $attachment_id ) {
                    $thumbnail_src = wp_get_attachment_image_src( $attachment_id, 'woocommerce_gallery_thumbnail_size' );

                    if ( $video = get_post_meta( $attachment_id, 'wpcpv_video_url', true ) ) {
                        $html = str_replace( '</div>', '<span class="wpcpv-item wpcpv-item-video" data-src="' . esc_url( $video ) . '"><img src="' . esc_url( $thumbnail_src[0] ) . '" alt=""/></span></div>', $html );
                    } else {
                        $html = str_replace( '</div>', '<span class="wpcpv-item wpcpv-item-image" data-src="' . wp_get_attachment_url( $attachment_id ) . '"><img src="' . esc_url( $thumbnail_src[0] ) . '" alt=""/></span></div>', $html );
                    }

                    return strip_tags( $html, '<div><img><span>' );
                }
            }

            return WPCleverWpcpv::instance();
        }

        return null;
    }

    function wpcpv_notice_wc() {
        ?>
        <div class="error">
            <p><?php esc_html_e( 'WPC Product Videos require WooCommerce version 3.0 or greater.', 'wpc-product-videos' ); ?></p>
        </div>
        <?php
    }
}
