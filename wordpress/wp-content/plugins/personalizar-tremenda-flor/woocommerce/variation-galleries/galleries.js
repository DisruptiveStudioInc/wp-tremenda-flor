/* Keep WooCommerce's slider and lightbox synchronized with the selected print. */
(function ($) {
    'use strict';
    $(function () {
        if (typeof tfVariationGallery === 'undefined') return;
        $('form.variations_form').each(function () {
            const $form = $(this);
            const $product = $form.closest('.product');
            let generation = 0;
            function render(payload) {
                const ticket = ++generation;
                // WooCommerce first updates its primary image in the same event cycle.
                setTimeout(function () {
                    if (ticket !== generation) return;
                    const $old = $product.find('.woocommerce-product-gallery').first();
                    if (!$old.length) return;
                    const $widget = $old.closest('.elementor-widget-woocommerce-product-images');
                    const fullSized = $widget.hasClass('vamtam-has-full-sized-gallery') && !$widget.hasClass('vamtam-mobile-gallery');
                    const $new = $old.clone(false).empty().removeAttr('style');
                    const $wrapper = $('<div class="woocommerce-product-gallery__wrapper"></div>').html(payload.html);
                    $new.append($wrapper).attr('data-tf-gallery', payload.key);
                    const wcLightbox = $('body').hasClass('wc-product-gallery-lightbox-active');
                    $new.find('.woocommerce-product-gallery__image a').each(function () {
                        $(this).attr('data-elementor-open-lightbox', wcLightbox ? 'no' : 'yes')
                            .attr('data-elementor-lightbox-slideshow', 'tf-gallery-' + $product.attr('id'))
                            .attr('data-elementor-lightbox-title', $(this).find('img').attr('alt') || '');
                    });
                    $old.find('.woocommerce-product-gallery__image').trigger('zoom.destroy');
                    const slider = $old.data('flexslider');
                    if (slider && slider.animatedSlides) clearInterval(slider.animatedSlides);
                    // Keep initialized slide nodes/controllers on later selections. FlexSlider
                    // registers window listeners, so repeatedly rebuilding it would leak them.
                    const $slides = $old.find('.woocommerce-product-gallery__image');
                    const $incoming = $new.find('.woocommerce-product-gallery__image');
                    if ($old.attr('data-tf-gallery') && $slides.length === $incoming.length) {
                        $slides.each(function (index) {
                            const $replacement = $incoming.eq(index);
                            $(this).attr('data-thumb', $replacement.attr('data-thumb'))
                                .attr('data-thumb-alt', $replacement.attr('data-thumb-alt'))
                                .html($replacement.html());
                        });
                        $old.attr('data-tf-gallery', payload.key);
                        $old.find('.flex-control-thumbs img').each(function (index) {
                            $(this).attr('src', $incoming.eq(index).attr('data-thumb'))
                                .attr('alt', $incoming.eq(index).attr('data-thumb-alt'));
                        });
                        if (slider) $old.flexslider(0);
                        $old.trigger('woocommerce_gallery_init_zoom');
                        $old.find('img').one('load.tfGallery', function () { $(window).trigger('resize'); });
                        $(window).trigger('resize');
                        return;
                    }
                    $old.replaceWith($new);
                    $new.wc_product_gallery(fullSized ? {flexslider_enabled: false, zoom_enabled: false} : {});
                    $new.find('img').on('load.tfGallery', function () { $(window).trigger('resize'); });
                    $(window).trigger('resize');
                    $new.find('.flex-control-thumbs img').on('mouseenter.tfGallery', function () { $(this).trigger('click'); });
                }, 0);
            }
            $form.on('found_variation.tfGallery', function (event, variation) {
                render(variation.tf_gallery || tfVariationGallery);
            });
            $form.on('reset_data.tfGallery', function () { render(tfVariationGallery); });
            render(tfVariationGallery);
            if (window.elementorFrontend && elementorFrontend.hooks) {
                elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-product-images.default', function () {
                    if (!$form.find('input.variation_id').val()) render(tfVariationGallery);
                });
            }
        });
    });
})(jQuery);
