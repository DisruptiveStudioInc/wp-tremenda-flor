<?php

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'PW_Gift_Cards_Blocks' ) ) :

final class PW_Gift_Cards_Blocks {
    function __construct() {
        require_once PWGC_PLUGIN_ROOT . '/includes/blocks/class-pw-gift-cards-blocks-endpoint.php';

        add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'woocommerce_store_api_checkout_order_processed' ) );
    }

    function site_uses_cart_or_checkout_blocks() {
        $page_ids = array(
            wc_get_page_id( 'cart' ),
            wc_get_page_id( 'checkout' ),
        );

        foreach ( $page_ids as $page_id ) {
            if ( empty( $page_id ) || $page_id <= 0 ) {
                continue;
            }

            $page_content = get_post_field( 'post_content', $page_id );
            if ( empty( $page_content ) ) {
                continue;
            }

            if ( has_block( 'woocommerce/cart', $page_content ) || has_block( 'woocommerce/checkout', $page_content ) ) {
                return true;
            }
        }

        return false;
    }

    function enqueue_block_assets() {
        if ( ! $this->site_uses_cart_or_checkout_blocks() ) {
            return;
        }

        global $pw_gift_cards;

        wp_register_script(
            'pwgc-wc-blocks',
            $pw_gift_cards->relative_url( '/assets/dist/blocks.js' ),
            array( 'wp-i18n', 'wp-plugins', 'wp-components', 'wp-data', 'wp-element', 'wc-blocks-checkout' ),
            PWGC_VERSION,
            true
        );

        wp_localize_script(
            'pwgc-wc-blocks',
            'pwgcBlocks',
            array(
                'hideRedeemForm' => ( 'yes' == get_option( 'pwgc_blocks_hide_redeem_form', 'no' ) ),
            ),
        );

        wp_set_script_translations(
            'pwgc-wc-blocks',
            'pw-woocommerce-gift-cards',
            plugin_dir_path( PWGC_PLUGIN_FILE ) . 'languages'
        );

        wp_enqueue_script( 'pwgc-wc-blocks' );

        wp_register_style(
            'pwgc-wc-blocks-style',
            $pw_gift_cards->relative_url( '/assets/css/blocks.css' ),
            array(),
            PWGC_VERSION
        );
        wp_enqueue_style( 'pwgc-wc-blocks-style' );
    }

    function woocommerce_store_api_checkout_order_processed( $order ) {
        global $pw_gift_cards_redeeming;

        if ( is_a( $pw_gift_cards_redeeming, 'PW_Gift_Cards_Redeeming' ) ) {
            $order_id = $order->get_id();

            $pw_gift_cards_redeeming->woocommerce_checkout_create_order( $order );
            $pw_gift_cards_redeeming->debit_gift_cards( $order_id, $order, "order_id: $order_id woocommerce_store_api_checkout_order_processed" );
        }
    }
}

global $pw_gift_cards_blocks;
$pw_gift_cards_blocks = new PW_Gift_Cards_Blocks;

endif;
