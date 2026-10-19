<?php
namespace VamtamElementor\Widgets\LoopGrid;

use \ElementorPro\Modules\LoopBuilder\Widgets\Loop_Grid as Elementor_Loop_Grid;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Is Pro Widget.
if ( ! \VamtamElementorIntregration::is_elementor_pro_active() ) {
	return;
}

// Theme preferences.
if ( ! \Vamtam_Elementor_Utils::is_widget_mod_active( 'loop-grid' ) ) {
	return;
}

if ( vamtam_theme_supports( 'loop-grid--swiper-dep' ) ) {
	function force_enqueue_swiper( $element ) {
		if ( 'loop-grid' === $element->get_name() ) {
			$element->add_script_depends( 'swiper' );
			$element->add_style_depends( 'e-swiper' );

			wp_enqueue_script( 'swiper' );
			wp_enqueue_style( 'e-swiper' );
		}
	}
	add_action( 'elementor/frontend/before_render', __NAMESPACE__ . '\force_enqueue_swiper' );
}
