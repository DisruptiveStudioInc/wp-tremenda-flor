<?php

defined( 'ABSPATH' ) or exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

if ( ! class_exists( 'PW_Gift_Cards_Blocks_Endpoint' ) ) :

final class PW_Gift_Cards_Blocks_Endpoint {
    public $namespace = 'pw-gift-cards';

    function __construct() {
        if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
            return;
        }

        // 1) Handle apply / remove via cart/extensions
        woocommerce_store_api_register_update_callback( array(
            'namespace' => $this->namespace,
            'callback'  => array( $this, 'handle_callback' ),
        ) );

        // 2) Expose applied gift cards in the cart response
        if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            woocommerce_store_api_register_endpoint_data( array(
                'endpoint'        => CartSchema::IDENTIFIER,
                'namespace'       => $this->namespace,
                'data_callback'   => array( $this, 'get_cart_gift_cards_data' ),
                'schema_callback' => array( $this, 'get_cart_gift_cards_schema' ),
                'schema_type'     => ARRAY_A,
            ) );
        }
    }

    function handle_callback( $data ) {
        global $pw_gift_cards_redeeming;

        $data = is_array( $data ) ? $data : array();

        // You can support both "apply" and "remove" using flags.
        $card_number = isset( $data['code'] ) ? $data['code'] : '';
        $card_number = wc_clean( wp_unslash( $card_number ) );

        $remove_card = ! empty( $data['remove'] );

        if ( '' === $card_number ) {
            return new \WP_Error(
                'pwgc_number_missing',
                __( 'Gift card number is required.', 'pw-woocommerce-gift-cards' ),
                array( 'status' => 400 )
            );
        }

        if ( $remove_card ) {
            $result = $pw_gift_cards_redeeming->remove_gift_card_from_session( $card_number );
        } else {
            $result = $pw_gift_cards_redeeming->add_gift_card_to_session( $card_number );
        }

        if ( true === $result ) {
            // Return a simple flag; the cart will be refetched and
            // our endpoint_data callback will supply the list.
            return array( 'success' => true );
        }

        return new \WP_Error( 'pwgc_apply_error', $result, array( 'status' => 400 ) );
    }

    /**
     * Provide applied gift card data in wc/store/cart response.
     */
    function get_cart_gift_cards_data() {
        global $pw_gift_cards_redeeming;

        $cards = array();

        $session_data = (array) WC()->session->get( PWGC_SESSION_KEY );
        if ( isset( $session_data['gift_cards'] ) ) {
            foreach ( $session_data['gift_cards'] as $card_number => $discount_amount ) {
                $pw_gift_card = new PW_Gift_Card( $card_number );
                if ( $pw_gift_card->get_id() ) {
                    $balance = apply_filters( 'pwgc_to_current_currency', $pw_gift_card->get_balance() ) - $discount_amount;
                    $balance = apply_filters( 'pwgc_remaining_balance_cart', $balance, $pw_gift_card );

                    $cards[] = array(
                        'number'       => $pw_gift_card->get_number(),
                        'amount'       => wc_format_decimal( $discount_amount * -1, wc_get_price_decimals() ),
                        'balance'      => wc_format_decimal( $balance, wc_get_price_decimals() ),
                    );
                }
            }
        }

        return array(
            'gift_cards' => $cards,
        );
    }

    /**
     * Schema for the data above.
     */
    function get_cart_gift_cards_schema() {
        return array(
            'gift_cards' => array(
                'description' => __( 'Applied PW Gift Cards.', 'pw-woocommerce-gift-cards' ),
                'type'        => 'array',
                'readonly'    => true,
                'items'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'number'  => array(
                            'description' => __( 'Gift card number.', 'pw-woocommerce-gift-cards' ),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'amount'  => array(
                            'description' => __( 'Amount applied to this cart.', 'pw-woocommerce-gift-cards' ),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                        'balance' => array(
                            'description' => __( 'Remaining gift card balance.', 'pw-woocommerce-gift-cards' ),
                            'type'        => 'string',
                            'readonly'    => true,
                        ),
                    ),
                ),
            ),
        );
    }
}

global $pw_gift_cards_blocks_endpoint;
$pw_gift_cards_blocks_endpoint = new PW_Gift_Cards_Blocks_Endpoint;

endif;