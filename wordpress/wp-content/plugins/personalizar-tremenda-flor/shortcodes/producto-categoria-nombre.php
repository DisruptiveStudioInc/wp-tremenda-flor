<?php
/**
 * Nombre de las categorías del producto consultado.
 */

namespace PersonalizarTremendaFlor\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Registra el shortcode de categorías del producto.
 */
function register_producto_categoria_nombre() {
    add_shortcode( 'producto_categoria_nombre', __NAMESPACE__ . '\\producto_categoria_nombre' );
}

add_action( 'init', __NAMESPACE__ . '\\register_producto_categoria_nombre' );

/**
 * Devuelve los nombres de las categorías en una página individual de producto.
 *
 * @return string Nombres separados por comas, escapados para HTML.
 */
function producto_categoria_nombre() {
    if ( ! is_singular( 'product' ) ) {
        return '';
    }

    $producto_id = get_queried_object_id();

    $tf_categories = get_the_terms( $producto_id, 'product_cat' );

    if ( empty( $tf_categories ) || is_wp_error( $tf_categories ) ) {
        return '';
    }

    $tf_names = wp_list_pluck( $tf_categories, 'name' );

    return esc_html( implode( ', ', $tf_names ) );
}
