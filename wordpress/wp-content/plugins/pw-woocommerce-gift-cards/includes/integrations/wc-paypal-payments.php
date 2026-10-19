<?php

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory' ) ) {
	return;
}

final class PWGC_WC_PayPal_Payments {

	/**
	 * WooCommerce PayPal Payments 4.1.0+ derives cart PayPal totals from breakdown
	 * components and exposes extra-discount filters for plugins like PW Gift Cards.
	 * Versions below 4.1.0 use cart get_total(), which already works with PWGC.
	 */
	const EXTRA_DISCOUNT_MIN_VERSION = '4.1.0';

	public function __construct() {
		if ( ! $this->supports_extra_discount_filters() ) {
			return;
		}

		add_filter( 'woocommerce_paypal_payments_cart_extra_discount', array( $this, 'cart_extra_discount' ), 10, 2 );
		add_filter( 'woocommerce_paypal_payments_store_api_cart_extra_discount', array( $this, 'store_api_cart_extra_discount' ), 10, 2 );
		add_filter( 'woocommerce_paypal_payments_order_extra_discount', array( $this, 'order_extra_discount' ), 10, 2 );
	}

	/**
	 * @param float    $extra
	 * @param WC_Cart  $cart
	 * @return float
	 */
	public function cart_extra_discount( $extra, $cart ) {
		return $extra + $this->get_gift_card_total_for_cart( $cart );
	}

	/**
	 * @param int   $extra
	 * @param mixed $cart_totals PayPal Store API CartTotals object.
	 * @return int
	 */
	public function store_api_cart_extra_discount( $extra, $cart_totals ) {
		$gift_card_total = $this->get_gift_card_total_for_cart();
		if ( $gift_card_total <= 0 ) {
			return $extra;
		}

		$minor_unit = wc_get_price_decimals();
		if ( is_object( $cart_totals ) && method_exists( $cart_totals, 'total_price' ) ) {
			$total_price = $cart_totals->total_price();
			if ( is_object( $total_price ) && method_exists( $total_price, 'currency_minor_unit' ) ) {
				$minor_unit = (int) $total_price->currency_minor_unit();
			}
		}

		return $extra + (int) round( $gift_card_total * ( 10 ** $minor_unit ) );
	}

	/**
	 * @param float    $extra
	 * @param WC_Order $order
	 * @return float
	 */
	public function order_extra_discount( $extra, $order ) {
		return $extra + $this->get_gift_card_total_for_order( $order );
	}

	/**
	 * @return bool
	 */
	private function supports_extra_discount_filters() {
		static $supported = null;

		if ( null !== $supported ) {
			return $supported;
		}

		$supported = version_compare( $this->get_paypal_payments_version(), self::EXTRA_DISCOUNT_MIN_VERSION, '>=' );

		return $supported;
	}

	/**
	 * @return string
	 */
	private function get_paypal_payments_version() {
		static $version = null;

		if ( null !== $version ) {
			return $version;
		}

		$version = '';

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$slug    = 'woocommerce-paypal-payments/woocommerce-paypal-payments.php';

		if ( isset( $plugins[ $slug ]['Version'] ) ) {
			$version = (string) $plugins[ $slug ]['Version'];
		}

		return $version;
	}

	/**
	 * @param WC_Cart|null $cart
	 * @return float
	 */
	private function get_gift_card_total_for_cart( $cart = null ) {
		$gift_card_total = 0.0;
		$cart            = $cart ? $cart : ( isset( WC()->cart ) ? WC()->cart : null );

		if ( $cart && isset( $cart->pwgc_total_gift_cards_redeemed ) && $cart->pwgc_total_gift_cards_redeemed > 0 ) {
			return (float) $cart->pwgc_total_gift_cards_redeemed;
		}

		if ( WC()->session ) {
			$session_data = (array) WC()->session->get( PWGC_SESSION_KEY );
			if ( ! empty( $session_data['gift_cards'] ) && is_array( $session_data['gift_cards'] ) ) {
				foreach ( $session_data['gift_cards'] as $amount ) {
					$gift_card_total += (float) $amount;
				}
			}
		}

		return $gift_card_total;
	}

	/**
	 * @param WC_Order $order
	 * @return float
	 */
	private function get_gift_card_total_for_order( $order ) {
		$gift_card_total = 0.0;

		foreach ( $order->get_items( 'pw_gift_card' ) as $line ) {
			$gift_card_total += (float) apply_filters( 'pwgc_to_order_currency', $line->get_amount(), $order );
		}

		return $gift_card_total;
	}
}

new PWGC_WC_PayPal_Payments();
