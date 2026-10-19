<?php

defined( 'ABSPATH' ) or exit;

if ( ! function_exists( 'pwgc_set_table_names' ) ) {
    function pwgc_set_table_names() {
        global $wpdb;

        if ( true === PWGC_MULTISITE_SHARED_DATABASE ) {
            $wpdb->pimwick_gift_card = $wpdb->base_prefix . 'pimwick_gift_card';
            $wpdb->pimwick_gift_card_activity = $wpdb->base_prefix . 'pimwick_gift_card_activity';
        } else {
            $wpdb->pimwick_gift_card = $wpdb->prefix . 'pimwick_gift_card';
            $wpdb->pimwick_gift_card_activity = $wpdb->prefix . 'pimwick_gift_card_activity';
        }
    }
}

if ( ! function_exists( 'pwgc_get_designs' ) ) {
    function pwgc_get_designs() {
        global $pw_gift_cards;

        $designs = maybe_unserialize( get_option( 'pw_gift_card_designs_free', array() ) );
        if ( empty( $designs ) ) {
            return $pw_gift_cards->default_designs;
        }

        return $designs;
    }
}

if ( ! function_exists( 'pwgc_redeem_url' ) ) {
    function pwgc_redeem_url( $item_data ) {
        $shop_url = get_permalink( wc_get_page_id( 'shop' ) );
        if ( empty( $shop_url ) ) {
            $shop_url = site_url();
        }
        $redeem_url = add_query_arg( 'pw_gift_card_number', urlencode( $item_data->gift_card_number ), $shop_url );

        return apply_filters( 'pwgc_redeem_url', $redeem_url, $item_data );
    }
}

if ( ! function_exists( 'pwgc_color_picker_field' ) ) {
    function pwgc_color_picker_field( $design, $key, $label ) {
        global $pw_gift_cards;

        if ( !empty( $design[ $key ] ) ) {
            $color = $design[ $key ];
        } else {
            $color = get_option( 'woocommerce_email_text_color', '#3c3c3c' );
        }
        $id = 'pwgc-designer-' . str_replace( '_', '-', $key );

        $preview_element = $pw_gift_cards->design_colors[ $key ][0];
        $preview_element_css = $pw_gift_cards->design_colors[ $key ][1];

        ?>
        <p class="form-field">
            <label class="pwgc-designer-label"><?php echo $label; ?></label>
            <input type="text" name="<?php echo $key; ?>" id="<?php echo $id; ?>" value="<?php echo $color; ?>" style="color: <?php echo $color; ?>; background-color: <?php echo $color; ?>; max-width: 75px;">
        </p>
        <script>
            jQuery(function() {
                pwgcAssignColorPicker('#<?php echo $id; ?>', '<?php echo $preview_element; ?>', '<?php echo $preview_element_css; ?>');
            });
        </script>
        <?php
    }
}

if ( ! function_exists( 'pwgc_dashboard_helper' ) ) {
    // Optionally set the selected CSS for the appropriate section.
    function pwgc_dashboard_helper( $item, $output = 'pwgc-dashboard-item-selected' ) {
        $selected = false;
        if ( isset( $_REQUEST['section'] ) ) {
            $selected = ( $_REQUEST['section'] == $item );
        } else if ( $item == 'balances' ) {
            $selected = true;
        }

        echo ( $selected ) ? $output : '';
    }
}

if ( ! function_exists( 'pwgc_paypal_ipn_pdt_bug_exists' ) ) {
    function pwgc_paypal_ipn_pdt_bug_exists() {
        $bug_exists = false;
        $ipn_enabled = false;
        $pdt_enabled = false;
        $woocommerce_paypal_settings = get_option( 'woocommerce_paypal_settings' );

        if ( empty( $woocommerce_paypal_settings['ipn_notification'] ) || 'no' !== $woocommerce_paypal_settings['ipn_notification'] ) {
            $ipn_enabled = true;
        }

        if ( ! empty( $woocommerce_paypal_settings['identity_token'] ) ) {
            $pdt_enabled = true;
        }

        if ( $ipn_enabled && $pdt_enabled ) {
            $bug_exists = true;
        }

        return apply_filters( 'pwgc_paypal_ipn_pdt_bug_exists', $bug_exists );
    }
}

if ( ! function_exists( 'pwgc_get_gift_card_product' ) ) {
    function pwgc_get_gift_card_product() {
        $query = new WC_Product_Query( array(
            'type' => PWGC_PRODUCT_TYPE_SLUG,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => 'publish',
        ) );
        $products = $query->get_products();

        if ( !empty( $products ) ) {
            return $products[0];
        } else {
            return null;
        }
    }
}

if ( ! function_exists( 'pwgc_admin_page' ) ) {
    function pwgc_admin_page() {
        // If the Pimwick Plugins menu is hidden, we need to redirect to the WooCommerce menu instead.
        if ( get_option( 'hide_pimwick_menu', 'no' ) == 'no' ) {
            $page = 'pw-gift-cards';
        } else {
            $page = 'wc-pw-gift-cards';
        }

        return apply_filters( 'pwgc_admin_page', $page );
    }
}

if ( ! function_exists( 'pwgc_admin_url' ) ) {
    function pwgc_admin_url( $section = '', $args = false ) {

        $admin_url = admin_url( 'admin.php' );
        $admin_url = add_query_arg( 'page', pwgc_admin_page(), $admin_url );

        if ( !empty( $section ) ) {
            $admin_url = add_query_arg( 'section', $section, $admin_url );
        }

        if ( !empty( $args ) && is_array( $args ) ) {
            foreach ( $args as $key => $value ) {
                $admin_url = add_query_arg( $key, $value, $admin_url );
            }
        }

        return apply_filters( 'pwgc_admin_url', $admin_url, $section, $args );
    }
}

if ( !function_exists( 'pwgc_date' ) ) {
    function pwgc_date( $date_string, $gmt = false ) {
        if ( $gmt ) {
            $time = strtotime( get_date_from_gmt( $date_string ) );
        } else {
            $time = strtotime( $date_string );
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
    }
}

if ( ! function_exists( 'pwgc_get_minimum_payment_amount' ) ) {
    /**
     * Minimum order total that must remain after gift card redemption for payment gateway checkout.
     *
     * @return float Zero when the setting is disabled.
     */
    function pwgc_get_minimum_payment_amount() {
        if ( 'yes' !== get_option( 'pwgc_minimum_payment_amount_enabled', 'no' ) ) {
            return 0.0;
        }

        $minimum = get_option( 'pwgc_minimum_payment_amount', '' );
        if ( '' === $minimum || null === $minimum ) {
            return 0.0;
        }

        $minimum = (float) $minimum;

        return (float) apply_filters( 'pwgc_minimum_payment_amount', max( 0, $minimum ) );
    }
}

if ( ! function_exists( 'pwgc_cap_eligible_amount_for_minimum_payment' ) ) {
    /**
     * Limit gift card redemption so the payable total does not fall below the configured minimum.
     * Full-balance zero checkout bypasses the minimum.
     *
     * @param float    $eligible_amount Amount eligible for gift card redemption.
     * @param WC_Cart  $cart            Cart instance.
     * @param array    $gift_cards      PW_Gift_Card objects applied to the cart.
     * @return float
     */
    function pwgc_cap_eligible_amount_for_minimum_payment( $eligible_amount, $cart, $gift_cards ) {
        $minimum = pwgc_get_minimum_payment_amount();
        if ( $minimum <= 0 || ! is_a( $cart, 'WC_Cart' ) ) {
            return $eligible_amount;
        }

        $cart_total = (float) $cart->total;
        if ( $cart_total <= 0 || $eligible_amount <= 0 ) {
            return $eligible_amount;
        }

        $combined_balance = 0.0;
        foreach ( $gift_cards as $gift_card ) {
            if ( is_a( $gift_card, 'PW_Gift_Card' ) ) {
                $combined_balance += (float) apply_filters( 'pwgc_to_current_currency', $gift_card->get_balance() );
            }
        }

        // Full-balance zero checkout bypasses the minimum payment amount.
        $max_possible_redeem = min( $eligible_amount, $combined_balance );
        if ( $max_possible_redeem >= $cart_total ) {
            return $eligible_amount;
        }

        if ( $cart_total > $minimum ) {
            $max_redeemable = $cart_total - $minimum;
            return min( $eligible_amount, max( 0, $max_redeemable ) );
        }

        return $eligible_amount;
    }
}

if ( ! function_exists( 'pwgc_maybe_add_minimum_payment_redemption_notice' ) ) {
    /**
     * @param WC_Cart $cart
     */
    function pwgc_maybe_add_minimum_payment_redemption_notice( $cart ) {
        if ( ! is_a( $cart, 'WC_Cart' ) || empty( $cart->pwgc_redeem_capped_for_minimum_payment ) ) {
            return;
        }

        $minimum = pwgc_get_minimum_payment_amount();
        if ( $minimum <= 0 ) {
            return;
        }

        wc_add_notice(
            sprintf(
                // translators: %s is the minimum remaining order total formatted as a price.
                __( 'Gift card partially applied. At least %s must remain for payment processing.', 'pw-woocommerce-gift-cards' ),
                wc_price( $minimum )
            ),
            'notice'
        );
    }
}
