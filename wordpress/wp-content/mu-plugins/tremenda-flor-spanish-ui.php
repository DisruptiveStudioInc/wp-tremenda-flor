<?php
/**
 * Translate the remaining English labels in the Tremenda Flor storefront.
 */

add_filter(
	'gettext',
	static function ( $translation, $text, $domain ) {
		if ( 'innovecouture' === $domain ) {
			$translations = [
				'Sort by:'            => 'Ordenar por:',
				'Default'             => 'Predeterminado',
				'Popularity'          => 'Popularidad',
				'Average rating'      => 'Valoración media',
				'Newest'              => 'Más recientes',
				'Price (low to high)' => 'Precio: de menor a mayor',
				'Price (high to low)' => 'Precio: de mayor a menor',
				'Coupon code'         => 'Código de cupón',
				'Look for the heart to save favorites while you shop.' => 'Busca el corazón para guardar tus favoritos mientras compras.',
				'Start Shopping'      => 'Empezar a comprar',
			];

			return $translations[ $text ] ?? $translation;
		}

		if ( 'elementor-pro' === $domain ) {
			$translations = [
				'Cart'                                          => 'Carrito',
				'Coupon code'                                  => 'Código de cupón',
				'If you have a coupon code, please apply it below.' => 'Si tienes un código de cupón, aplícalo a continuación.',
				'Apply'                                        => 'Aplicar',
			];

			return $translations[ $text ] ?? $translation;
		}

		if ( 'woocommerce' === $domain && 'Coupon code' === $text ) {
			return 'Código de cupón';
		}

		if ( 'vamtam-elementor-integration' === $domain && 'Cart' === $text ) {
			return 'Carrito';
		}

		return $translation;
	},
	20,
	3
);
