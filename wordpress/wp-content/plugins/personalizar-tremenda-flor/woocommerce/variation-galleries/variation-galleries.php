<?php
/** Galerías por estampado para WooCommerce y Elementor/Vamtam. */

namespace PersonalizarTremendaFlor\WooCommerce\VariationGalleries;

defined('ABSPATH') || exit;

function gallery_payload($ids) {
    static $cache = [];
    $ids = array_values(array_filter(array_map('absint', $ids)));
    $key = implode(',', $ids);
    if (isset($cache[$key])) return $cache[$key];
    $html = '';
    foreach ($ids as $index => $id) {
        $html .= wc_get_gallery_image_html($id, $index === 0);
    }
    return $cache[$key] = ['key' => $key, 'html' => $html];
}
add_filter('woocommerce_available_variation', static function ($data, $parent, $variation) {
    $extra = $variation->get_meta('_tf_variation_gallery_ids', true);
    if (is_array($extra) && count($extra) === 3) {
        $data['tf_gallery'] = gallery_payload(array_merge([$variation->get_image_id()], $extra));
    }
    return $data;
}, 10, 3);
add_action('wp_enqueue_scripts', static function () {
    if (!function_exists('is_product') || !is_product()) return;
    $product = wc_get_product(get_queried_object_id());
    if (!$product || !$product->get_meta('_tf_catalog_gallery_key')) return;
    wp_enqueue_script('tf-variation-galleries', plugins_url('galleries.js', __FILE__), ['jquery', 'wc-add-to-cart-variation', 'wc-single-product'], (string) filemtime(__DIR__ . '/galleries.js'), true);
    wp_localize_script('tf-variation-galleries', 'tfVariationGallery', gallery_payload(array_merge([$product->get_image_id()], $product->get_gallery_image_ids())));
});
