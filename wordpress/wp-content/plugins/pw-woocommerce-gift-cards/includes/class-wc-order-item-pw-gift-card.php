<?php

/*
Copyright (C) Pimwick, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or exit;

function pwgc_order_item_woocommerce_get_items_key( $key, $item ) {
    if ( is_a( $item, 'WC_Order_Item_PW_Gift_Card' ) ) {
        return 'pw_gift_card_lines';
    }

    return $key;
}
add_filter( 'woocommerce_get_items_key', 'pwgc_order_item_woocommerce_get_items_key', 10, 2 );


function pwgc_woocommerce_order_type_to_group( $groups ) {
    $groups['pw_gift_card'] = 'pw_gift_card_lines';
    return $groups;
}
add_filter( 'woocommerce_order_type_to_group', 'pwgc_woocommerce_order_type_to_group', 10, 2 );


function pwgc_woocommerce_get_order_item_classname( $classname, $item_type, $id ) {
    if ( $item_type == 'pw_gift_card' ) {
        return 'WC_Order_Item_PW_Gift_Card';
    }
    return $classname;
}
add_filter( 'woocommerce_get_order_item_classname', 'pwgc_woocommerce_get_order_item_classname', 10, 3 );


class WC_Order_Item_PW_Gift_Card extends WC_Order_Item {

    protected $extra_data = array(
        'card_number'   => '',
        'amount'        => 0,
    );

    public function set_name( $value ) {
        return $this->set_card_number( $value );
    }

    public function set_card_number( $value ) {
        $this->set_prop( 'card_number', wc_clean( $value ) );
    }

    public function set_amount( $value ) {
        $this->set_prop( 'amount', wc_format_decimal( $value ) );
    }

    public function get_name( $context = 'view' ) {
        return $this->get_card_number( $context );
    }

    public function get_type() {
        return 'pw_gift_card';
    }

    public function get_card_number( $context = 'view' ) {
        return $this->get_prop( 'card_number', $context );
    }

    public function get_amount( $context = 'view' ) {
        return $this->get_prop( 'amount', $context );
    }

    /**
     * Stub for payment gateways (e.g. Payment Plugins for PayPal) that expect product-like order items.
     * Redemption items are not products; return 0 so gateways do not call .indexOf() on undefined.
     *
     * @return int
     */
    public function get_product_id() {
        return 0;
    }

    /**
     * Stub for plugins (e.g. XA WooCommerce Subscriptions) that call get_variation_id() on all order items.
     * Gift card items have no variation; return 0 so hf_get_canonical_product_id() and similar code do not fatal.
     *
     * @return int
     */
    public function get_variation_id() {
        return 0;
    }

    /**
     * Stub for payment gateways that expect product-like order items.
     * Return empty string so gateways do not call .indexOf() on undefined (e.g. create_order_error).
     *
     * @return string
     */
    public function get_sku() {
        return '';
    }

    /**
     * Stub for payment gateways that expect product-like order items.
     * Redemption items have no associated product.
     *
     * @return null
     */
    public function get_product() {
        return null;
    }

    /**
     * Stub for payment gateways that expect WC_Order_Item_Product-style quantity.
     *
     * @return int
     */
    public function get_quantity() {
        return 1;
    }

    /**
     * Include product-like keys in data array so gateways (e.g. PayPal) that build
     * payloads from get_data() or array access never see undefined sku/product_id.
     *
     * @return array
     */
    public function get_data() {
        $data = parent::get_data();
        $data['product_id']  = $this->get_product_id();
        $data['variation_id'] = 0;
        $data['quantity']   = $this->get_quantity();
        $data['sku']        = $this->get_sku();
        return $data;
    }

    /**
     * ArrayAccess: return stub values for product-like keys so JS never sees undefined.
     *
     * @param string $offset Key (e.g. sku, product_id).
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet( $offset ) {
        if ( 'sku' === $offset ) {
            return $this->get_sku();
        }
        if ( 'product_id' === $offset ) {
            return $this->get_product_id();
        }
        if ( 'variation_id' === $offset ) {
            return 0;
        }
        if ( 'quantity' === $offset ) {
            return $this->get_quantity();
        }
        return parent::offsetGet( $offset );
    }

    /**
     * ArrayAccess: report that product-like keys exist so they are included in serialization.
     *
     * @param string $offset Key (e.g. sku, product_id).
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists( $offset ) {
        if ( in_array( $offset, array( 'sku', 'product_id', 'variation_id', 'quantity' ), true ) ) {
            return true;
        }
        return parent::offsetExists( $offset );
    }
}
