<?php

/**
 * Vamtam Theme Framework base class
 *
 * @author Nikolay Yordanov <me@nyordanov.com>
 * @package vamtam/innovecouture
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * This is the first loaded framework file
 *
 * VamtamFramework does the following ( in this order ):
 *  - sets constants for the frequently used paths
 *  - loads translations
 *  - loads the plugins bundled with the theme
 *  - loads some functions and helpers used in various places
 *  - sets the custom post types
 *  - if this is wp-admin, load admin files
 *
 * This class also loads the custom widgets and sets what the theme supports ( + custom menus )
 */

class VamtamFramework {

	/**
	 * Cache the result of some operations in memory
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Post types with double sidebars
	 */
	public static $complex_layout = array( 'page', 'post', 'product' );

	/**
	 * Initialize the Vamtam framework
	 * @param array $options framework options
	 */
	public function __construct( $options ) {
		// Autoload classes on demand
		if ( function_exists( '__autoload' ) )
			spl_autoload_register( '__autoload' );
		spl_autoload_register( array( $this, 'autoload' ) );

		self::$complex_layout = apply_filters( 'vamtam_complex_layout', self::$complex_layout );

		$this->set_constants( $options );
		$this->load_functions();
		$this->load_admin();

		require_once VAMTAM_DIR . 'classes/class-tgm-plugin-activation.php';
		require_once VAMTAM_SAMPLES_DIR . 'dependencies.php';

		if ( file_exists( VAMTAM_ADMIN_DIR . 'classes/token-restoration.php' ) ) {
			require_once VAMTAM_ADMIN_DIR . 'classes/token-restoration.php';
		}

		add_action( 'after_setup_theme', [ __CLASS__, 'setup_options' ], 5 );
		add_action( 'after_setup_theme', array( __CLASS__, 'theme_supports' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'load_languages' ) );
		add_action( 'widgets_init', array( __CLASS__, 'widgets_init' ) );
		add_filter( 'vamtam_purchase_code', array( __CLASS__, 'get_purchase_code' ) );
		add_filter( 'wpv_purchase_code', array( __CLASS__, 'get_purchase_code' ) );

		VamtamElementorBridge::get_instance();
	}

	/**
	 * Autoload classes when needed
	 *
	 * @param  string $class class name
	 */
	public function autoload( $class ) {
		$class = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', str_replace( '_', '', $class ) ) );

		if ( strpos( $class, 'vamtam-' ) === 0 ) {
			$path = trailingslashit( get_template_directory() ) . 'vamtam/classes/';
			$file = str_replace( 'vamtam-', '', $class ) . '.php';

			if ( is_readable( $path . $file ) ) {
				include_once( $path . $file );
				return;
			}

			if ( is_admin() ) {
				$admin_path = VAMTAM_ADMIN_DIR . 'classes/';

				if ( is_readable( $admin_path . $file ) ) {
					include_once( $admin_path . $file );
					return;
				}
			}
		}

	}

	/**
	 * Sets self::$cache[ $key ] = $value
	 *
	 * @param mixed $key
	 * @param mixed $value
	 */
	public static function set( $key, $value ) {
		self::$cache[ $key ] = $value;
	}

	/**
	 * Returns self::$cache[ $key ]
	 *
	 * @param  mixed $key
	 * @return mixed        value
	 */
	public static function get( $key, $default = false ) {
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : $default;
	}

	/**
	 * Get the theme version
	 *
	 * @return string theme version as defined in style.css
	 */
	public static function get_version() {
		if ( isset( self::$cache['version'] ) )
			return self::$cache['version'];

		$the_theme = wp_get_theme();
		if ( $the_theme->parent() ) {
			$the_theme = $the_theme->parent();
		}

		self::$cache['version'] = $the_theme->get( 'Version' );

		return self::$cache['version'];
	}

	/**
	 * Get a unique signature for this site,
	 * so that the update server can differentiate between different sites installed on the same domain
	 *
	 * @return string
	 */
	public static function get_signature() {
		$option = 'vamtam_site_signature';
		$signature = get_option( $option );

		if ( empty( $signature ) ) {
			$nonce_key  = defined( 'NONCE_KEY' ) ? NONCE_KEY : '';
			$nonce_salt = defined( 'NONCE_SALT' ) ? NONCE_SALT : '';

			if ( '' === $nonce_key && '' === $nonce_salt ) {
				wp_salt('nonce'); // create fallback values stored in the database

				$nonce_key  = get_site_option( 'nonce_key', '' );
				$nonce_salt = get_site_option( 'nonce_salt', '' );
			}

			// Derived, not random. On a site whose options don't persist, the update_option()
			// below is a silent no-op and we land here again on the very next request; a random
			// signature would make every ping look like a brand new install and inflate the
			// install stats without bound. Deriving it keeps such a site pinned to one value.
			// The theme dir is what separates several installs sharing a domain, which is the
			// reason the signature exists at all.
			$signature = 'v2-' . substr( hash( 'sha256', implode( '|', [
				VAMTAM_THEME_NAME,
				VAMTAM_THEME_URI,
				VAMTAM_THEME_DIR,
				$nonce_key,
				$nonce_salt,
			] ) ), 0, 40 ); // the CRM stores signature as varchar(60)

			update_option( $option, $signature );
		}

		return $signature;
	}

	public static function get_user_agent() {
		global $wp_version;

		return 'WordPress/' . $wp_version . '; ' . get_option( 'home' ) . '; ' . self::get_signature();
	}

	/**
	 * Defines constants used by the theme
	 *
	 * @param array $options framework options
	 */
	private function set_constants( $options ) {
		define( 'VAMTAM_THEME_NAME', $options['name'] );
		define( 'VAMTAM_THEME_SLUG', $options['slug'] );
		define( 'VAMTAM_THUMBNAIL_PREFIX', 'vamtam-' );

		// theme dir and uri
		define( 'VAMTAM_THEME_DIR', get_template_directory() . '/' );
		define( 'VAMTAM_THEME_URI', get_template_directory_uri() . '/' );

		// framework dir and uri
		define( 'VAMTAM_DIR', VAMTAM_THEME_DIR . 'vamtam/' );
		define( 'VAMTAM_URI', VAMTAM_THEME_URI . 'vamtam/' );

		// common assets dir and uri
		define( 'VAMTAM_ASSETS_DIR', VAMTAM_DIR . 'assets/' );
		define( 'VAMTAM_ASSETS_URI', VAMTAM_URI . 'assets/' );

		// common file paths
		define( 'VAMTAM_FONTS_URI',  VAMTAM_ASSETS_URI . 'fonts/' );
		define( 'VAMTAM_HELPERS',    VAMTAM_DIR . 'helpers/' );
		define( 'VAMTAM_JS',         VAMTAM_ASSETS_URI . 'js/' );
		define( 'VAMTAM_OPTIONS',    VAMTAM_DIR . 'options/' );
		define( 'VAMTAM_PLUGINS',    VAMTAM_DIR . 'plugins/' );
		define( 'VAMTAM_CSS',        VAMTAM_ASSETS_URI . 'css/' );
		define( 'VAMTAM_CSS_DIR',    VAMTAM_ASSETS_DIR . 'css/' );
		define( 'VAMTAM_FB_CSS_DIR', VAMTAM_ASSETS_DIR . 'css/src/fallback/' );
		define( 'VAMTAM_IMAGES',     VAMTAM_ASSETS_URI . 'images/' );
		define( 'VAMTAM_IMAGES_DIR', VAMTAM_ASSETS_DIR . 'images/' );

		// sample content
		define( 'VAMTAM_SAMPLES_DIR',   VAMTAM_THEME_DIR . 'samples/' );
		define( 'VAMTAM_SAMPLES_URI',   VAMTAM_THEME_URI . 'samples/' );

		// cache
		define( 'VAMTAM_CACHE_DIR', VAMTAM_THEME_DIR . 'cache/' );
		define( 'VAMTAM_CACHE_URI', VAMTAM_THEME_URI . 'cache/' );

		// admin
		define( 'VAMTAM_ADMIN_DIR', VAMTAM_DIR . 'admin/' );
		define( 'VAMTAM_ADMIN_URI', VAMTAM_URI . 'admin/' );

		define( 'VAMTAM_ADMIN_AJAX',       VAMTAM_ADMIN_URI . 'ajax/' );
		define( 'VAMTAM_ADMIN_AJAX_DIR',   VAMTAM_ADMIN_DIR . 'ajax/' );
		define( 'VAMTAM_ADMIN_ASSETS_URI', VAMTAM_ADMIN_URI . 'assets/' );
		define( 'VAMTAM_ADMIN_HELPERS',    VAMTAM_ADMIN_DIR . 'helpers/' );
		define( 'VAMTAM_ADMIN_METABOXES',  VAMTAM_ADMIN_DIR . 'metaboxes/' );
		define( 'VAMTAM_ADMIN_TEMPLATES',  VAMTAM_ADMIN_DIR . 'templates/' );
	}

	public static function setup_options() {
		global $vamtam_defaults;

		$opt_name = 'vamtam_theme';

		if ( ! isset( $GLOBALS[ $opt_name ] ) ) {
			$options = get_option( $opt_name );

			if ( false === $options ) {
				$options = [];
			}

			$GLOBALS[ $opt_name ] = wp_parse_args( $options, $vamtam_defaults );
		}
	}

	/**
	 * Register theme support for various features
	 */
	public static function theme_supports() {
		global $content_width;

		self::set( 'is_responsive', apply_filters( 'vamtam-theme-responsive-mode', true ) );

		/**
		 * the max content width the css is built for should equal the actual content width,
		 * for example, the width of the text of a page without sidebars
		 */
		if ( ! isset( $content_width ) ) $content_width = 1360;

		if ( VamtamElementorBridge::is_elementor_active() && VamtamElementorBridge::elementor_is_v3_or_greater() ) {
			$kits_manager = Elementor\Plugin::instance()->kits_manager;

			$kit_id = get_option( 'elementor_active_kit' );

			if ( $kit_id ) {
				$kit = get_post_meta( $kit_id, '_elementor_page_settings', true );

				if ( ! empty( $kit ) && isset( $kit[ 'container_width' ] ) ) {
					$content_width = (int)$kit[ 'container_width' ][ 'size' ];
				}
			}
		}

		add_theme_support( 'woocommerce', array(
			'thumbnail_image_width'         => $content_width / max( 1, (int)get_option( 'woocommerce_catalog_columns', 4 ) ),
			'single_image_width'            => $content_width / 2,
			'gallery_thumbnail_image_width' => $content_width / 4,
		) );

		// HiDPI WC image sizes
		if ( function_exists( 'wc_get_image_size' ) ) {
			$thumbnail         = wc_get_image_size( 'thumbnail' );
			$single            = wc_get_image_size( 'single' );

			add_image_size( 'vamtam_woocommerce_thumbnail_2x', (int)$thumbnail['width'] * 2, (int)$thumbnail['height'] * 2, $thumbnail['crop'] );
			add_image_size( 'vamtam_woocommerce_single_2x', (int)$single['width'] * 2, (int)$single['height'] * 2, $single['crop'] );
		}

		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
		add_theme_support( 'title-tag' );
		add_theme_support( 'custom-logo' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );

		add_theme_support( 'vamtam-ajax-siblings' );
		add_theme_support( 'vamtam-page-title-style' );
		add_theme_support( 'vamtam-tribe-events' );
		add_theme_support( 'vamtam-cart-dropdown' );

		add_post_type_support( 'page', 'excerpt' );

		add_theme_support( 'customize-selective-refresh-widgets' );

		add_theme_support( 'vamtam-elementor-widgets' );
		function vamtam_elementor_widgets_support( bool $supports, array $args, string $feature ) {
			if ( ! $args ) {
				// Empty array here is essentially a check for 'vamtam-elementor-widgets' (>=WP6.0).
				// Documented in /wp-includes/theme.php->current_theme_supports().
				return $supports;
			}

			$theme_features = [
				'woocommerce-product-images--disable-image-link',
				'woocommerce-menu-cart--close-cart-theme-icon',
				'form--updated-fields-style-section',
				'woocommerce-product-images--sale-flash-section',
				'woocommerce-product-images--no-border-controls',
				'woocommerce-product-data-tabs--mobile-hr-layout',
				'testimonial-carousel--slide-triggers-inner-anims',
				'woocommerce-product-images--full-sized-gallery',
				'popup--absolute-position',
				'nav-menu--theme-pointer',
				'woocommerce-product-data-tabs--hide-tabs-toggles',
				'woocommerce-product-images--new-badge-section',
				'woocommerce-menu-cart--theme-cart-icon',
				'nav-menu--disable-scroll-on-mobile',
				'nav-menu--toggle-sticky-hover-state-on-touch-fix',
				'woocommerce-product-images--dot-nav-on-mobile',
				'button--underline-anim',
				'popup--open-on-selector-hover',
				'container--vamtam-sticky-header-controls',
				'login--extra-login-controls',
				'call-to-action--underline-anim',
				'theme-post-featured-image--gallery-on-hover',
				'column--logical-spacings',
				'loop-grid--swiper-dep',
				'text-editor--selectors-include-links',
			];

			return in_array( $args[0], $theme_features );
		}
		add_filter( 'current_theme_supports-vamtam-elementor-widgets', 'vamtam_elementor_widgets_support', 10, 3 );

		add_theme_support( 'wc-product-gallery-slider' );

		if ( vamtam_get_option( 'wc-product-gallery-zoom' ) === 'enabled' ) {
			add_theme_support( 'wc-product-gallery-zoom' );
		}

		if ( ! vamtam_extra_features() ) {
			add_theme_support( 'wc-product-gallery-lightbox' );
			add_theme_support( 'wc-product-gallery-zoom' );
		}

		if ( function_exists( 'register_nav_menus' ) ) {
			register_nav_menus(
				array(
					'primary-menu'     => esc_html__( 'Menu Header', 'innovecouture' ),
				)
			);
		}
	}

	/**
	 * Load interface translations
	 */
	public static function load_languages() {
		load_theme_textdomain( 'innovecouture', VAMTAM_THEME_DIR . 'languages' );
	}

	/**
	 * Loads the main php files used by the framework
	 */
	private function load_functions() {
		global $vamtam_defaults, $vamtam_fonts;
		$vamtam_defaults = include VAMTAM_SAMPLES_DIR . 'default-options.php';
		$vamtam_fonts    = include VAMTAM_HELPERS . 'fonts.php';

		require_once VAMTAM_HELPERS . 'init.php';

		require_once VAMTAM_OPTIONS . 'core/core.php';

		$vamtam_custom_fonts = get_option( 'vamtam_custom_font_families', '' );
		if ( defined( 'ELEMENTOR_VERSION' ) && defined( 'ELEMENTOR_PRO_VERSION' ) && class_exists( 'ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager' ) ) {
			$elementor_custom_fonts = get_option( ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager::FONTS_OPTION_NAME, false );
		}

		// Custom Vamtam Fonts
		if ( ! empty( $vamtam_custom_fonts ) ) {
			$vamtam_custom_fonts = explode( "\n", $vamtam_custom_fonts );

			$vamtam_fonts['-- Custom fonts --'] = array(
				'family' => '',
			);

			foreach ( $vamtam_custom_fonts as $font ) {
				$font = preg_replace( '/["\']+/', '', trim( $font ) );

				$vamtam_fonts[ $font ] = array(
					'family' => '"' . $font . '"',
					'weights' => array( '300', '300 italic', 'normal', 'italic', '600', '600 italic', 'bold', 'bold italic', '800', '800 italic' ),
				);
			}
		}

		//Custom Elementor Fonts
		if ( ! empty( $elementor_custom_fonts ) ) {

			if ( empty( $vamtam_custom_fonts ) ) {
				$vamtam_fonts['-- Custom fonts --'] = array(
					'family' => '',
				);
			}

			foreach ( $elementor_custom_fonts as $font_name => $font ) {
				$font = preg_replace( '/["\']+/', '', trim( $font_name ) );

				$vamtam_fonts[ $font ] = array(
					'family' => '"' . $font_name . '"',
					'weights' => array( '300', '300 italic', 'normal', 'italic', '600', '600 italic', 'bold', 'bold italic', '800', '800 italic' ),
				);
			}
		}

		require_once VAMTAM_HELPERS . 'icons.php';

		require_once VAMTAM_HELPERS . 'base.php';
		require_once VAMTAM_HELPERS . 'template.php';
		require_once VAMTAM_HELPERS . 'css.php';

		require_once VAMTAM_HELPERS . 'woocommerce-integration.php';
		require_once VAMTAM_HELPERS . 'the-events-calendar-integration.php';

		// frontend wrappers
		require_once VAMTAM_HELPERS . 'frontend-wrappers.php';

		VamtamOverrides::filters();
		VamtamEnqueues::actions();

		if ( file_exists( VAMTAM_HELPERS . 'migrations.php' ) ) {
			require_once VAMTAM_HELPERS . 'migrations.php';
		}
	}

	/**
	 * Register sidebars
	 */
	public static function widgets_init() {

		if ( ! VamtamElementorBridge::is_elementor_pro_active() ) {
			$vamtam_sidebars = VamtamSidebars::get_instance();
			$vamtam_sidebars->register_sidebars();
		}
	}

	/**
	 * Loads the theme administration code
	 */
	private function load_admin() {
		// The mixed content test runs from wp-cron, which is not an admin request, so this
		// hook has to be registered outside the is_admin() gate below - and the class has to
		// be required by hand, because the autoloader only reaches admin/classes in admin.
		add_action( 'vamtam_mixed_content_cron', function() {
			require_once VAMTAM_ADMIN_DIR . 'classes/diagnostics.php';

			VamtamDiagnostics::mixed_content_cron();
		} );

		if ( ! is_admin() ) return;

		VamtamAdmin::actions();
	}

	/**
	 * Return the option_name used for the purchase code option
	 * Backwards-compatible with the old option_name used before July 2018
	 */
	public static function get_purchase_code_option_key() {
		return defined( 'VAMTAM_ENVATO_THEME_ID' ) ? 'envato_purchase_code_' . VAMTAM_ENVATO_THEME_ID : 'vamtam-envato-license-key';
	}

	public static function get_token_option_key() {
		if ( defined( 'VAMTAM_ENVATO_THEME_ID' ) ) {
			$migrated = get_option( 'vamtam_token_migration_completed', false );
			return $migrated ? '_vamtam_license_token_' . VAMTAM_ENVATO_THEME_ID : '_vamtam_license_token';
		} else {
			return '_vamtam_license_token';
		};
	}

	/**
	 * Return the purchase code, if set
	 * Also, automatically migrate the old option to use the new option_name
	 */
	public static function get_purchase_code() {
		$purchase_code_option_key = self::get_purchase_code_option_key();

		// if the old purchase code option is present and a THEME ID is set - migrate to the new purchase code option key
		if ( defined( 'VAMTAM_ENVATO_THEME_ID' ) && get_option( 'vamtam-envato-license-key', false ) !== false ) {
			update_option( $purchase_code_option_key, get_option( 'vamtam-envato-license-key' ) );
			delete_option( 'vamtam-envato-license-key' );
		}

		return get_option( $purchase_code_option_key );
	}

	public static function license( $state = '' ) {
		if ( ! empty( $state ) ) {
			// Set.
			update_option( '_vamtam_license', $state );
		} else {
			// Get.
			return get_option( '_vamtam_license' );
		}
	}

	public static function license_valid() {
		echo '<span id="success">';
		esc_html_e( 'Valid Purchase Key.', 'innovecouture' );
		echo '<p>';
		esc_html_e( 'Congratulations! You have succesfully registered your product.', 'innovecouture' );
		echo '</p>';
		echo '</span>';
	}

	public static function license_invalid() {
		echo '<span id="fail">';
		esc_html_e( 'Incorrect Purchase Key.', 'innovecouture' );
		echo '<p>';
		esc_html_e( 'Please check your purchase code and re-enter.', 'innovecouture' );
		echo '</p>';
		echo '</span>';
	}

	public static function license_failed() {
		echo '<span class="fail">';
		esc_html_e( 'Cannot validate Purchase Key. Please try again later. If the problem persists your server might not have the curl PHP extension enabled.', 'innovecouture' );
		echo '</span>';
	}

	/**
	 * One entry point for every "something is wrong with this licence" notice, so the admin notice
	 * and the theme-setup notice can never disagree about what to say.
	 */
	/**
	 * The chrome every licence notice shares: the outer wrappers and the logo rail. Four copies of
	 * this markup meant four places to keep in step every time the layout moved.
	 */
	public static function notice_open( $classes = '' ) {
		?>
			<div class="vamtam-ts-notice">
				<div class="vamtam-notice vamtam-license-notice notice <?php echo esc_attr( $classes ); ?>">
					<div class="vamtam-notice-aside">
						<div class="vamtam-notice-icon-wrapper">
							<img id="vamtam-logo" src="<?php echo esc_attr( VAMTAM_ADMIN_ASSETS_URI . 'images/vamtam-logo.png' ); ?>"></img>
						</div>
					</div>
					<div class="vamtam-notice-content">
		<?php
	}

	public static function notice_close() {
		?>
					</div>
				</div>
			</div>
		<?php
	}

	public static function license_notice() {
		$reason = get_option( '_vamtam_license_reason' );

		// A legacy Elements code cannot be validated at all any more, so the customer is asked to
		// re-register in the current format first. Showing the over-cap message at the same time
		// is noise: Elements installs never receive a download either way, and the situation
		// resolves itself once they re-register.
		if ( self::has_legacy_elements_code() ) {
			self::license_elements_legacy();
			return;
		}

		if ( in_array( $reason, array( 'cap-reached', 'banned' ), true ) ) {
			self::license_cap_reached();
			return;
		}

		// Any reason at all means a licence that failed a check, and license_inactive() falls back
		// to the "not valid" copy for anything it does not recognise. Only the absence of a reason
		// means this site has simply never registered — listing known reasons here meant a stored
		// value we later stopped recognising rendered a cheery "Welcome!" instead.
		if ( $reason ) {
			self::license_inactive( $reason );
			return;
		}

		self::license_register();
	}

	/**
	 * A code that was accepted once and no longer is. Falling through to the "Welcome!" notice
	 * here told a customer whose order was refunded to activate a licence they thought they had.
	 */
	public static function license_inactive( $reason ) {
		self::notice_open();
		?>
						<h3>Attention:</h3>
						<?php $code_label = self::license_code_label(); ?>
						<?php if ( 'revoked' === $reason ) : ?>
							<p>The <?php echo esc_html( $code_label ); ?> registered on this site is no
							longer active, so theme updates and support are unavailable here.</p>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %1$s-%2$s: link to the contact form. */
										__( 'This usually means the order was refunded. If you believe this is a mistake, please %1$sget in touch%2$s with your order details.', 'innovecouture' ),
										'<a href="' . esc_url( self::contact_url() ) . '" target="_blank">',
										'</a>'
									),
									'vamtam-a-span'
								);
								?>
							</p>
						<?php elseif ( 'expired' === $reason ) : ?>
							<p>The update period for the <?php echo esc_html( $code_label ); ?> registered
							on this site has ended, so theme updates are unavailable here.</p>
							<p>
								<?php
								// Not the multiple-sites offer: this customer has one site and a
								// lapsed period, which is a different purchase.
								echo wp_kses(
									sprintf(
										/* translators: %1$s-%2$s: link to this theme's page on vamtam.com. */
										__( '%1$sYou may buy a new purchase code%2$s to continue receiving updates.', 'innovecouture' ),
										'<a href="' . esc_url( self::extra_license_url() ) . '" target="_blank">',
										'</a>'
									),
									'vamtam-a-span'
								);
								?>
							</p>
						<?php else : ?>
							<p>The <?php echo esc_html( $code_label ); ?> registered on this site is not
							valid, so theme updates and support are unavailable here.</p>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %1$s-%2$s: link to the validation troubleshooting guide. */
										__( 'Please check the code and enter it again. If it still will not validate, %1$sthis guide%2$s covers the usual causes.', 'innovecouture' ),
										'<a href="' . esc_url( self::validation_help_url() ) . '" target="_blank">',
										'</a>'
									),
									'vamtam-a-span'
								);
								?>
							</p>
						<?php endif; ?>
						<p>
							<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
								<?php echo esc_html( sprintf( __( 'Enter a %s', 'innovecouture' ), $code_label ) ); ?>
							</a>
							<?php if ( ! wp_doing_ajax() ) : ?>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'vamtam-recheck-license', '1' ), 'vamtam-recheck-license' ) ); ?>">
									<?php esc_html_e( 'Re-check now', 'innovecouture' ); ?>
								</a>
							<?php endif; ?>
						</p>
		<?php
		self::notice_close();
		?>
		<?php
	}

	/**
	 * Which store this licence came from: 'vamtam', 'market' or 'elements'.
	 *
	 * There are three stores, and quoting the wrong one's licensing terms at a customer is worse
	 * than quoting none — a vamtam.com customer must never be sent to ThemeForest. The explicit
	 * source wins; installs registered before _vamtam_license_source existed fall back to the
	 * token flag and then to the shape of the key itself.
	 */
	public static function license_source() {
		$source = get_option( '_vamtam_license_source' );

		if ( in_array( $source, array( 'vamtam', 'market', 'elements' ), true ) ) {
			return $source;
		}

		if ( get_option( self::get_token_option_key() ) ) {
			return 'elements';
		}

		return self::detect_key_source( self::get_purchase_code() );
	}

	/**
	 * Work out which store a key came from by its shape alone. The canonical definition — the
	 * update checker used to carry a second, narrower copy, and two answers to "is this a
	 * vamtam.com key" is one too many.
	 */
	public static function detect_key_source( $key ) {
		$key = trim( (string) $key );

		if ( preg_match( '/^VAMTAM-[A-Z0-9]{5}(?:-[A-Z0-9]{5}){5}$/i', $key ) ) {
			return 'vamtam';
		}

		if ( preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key ) ) {
			return 'market';
		}

		if ( preg_match( '/^eyJ[A-Za-z0-9\-_\.]+$/', $key ) || ( strlen( $key ) >= 36 && strpos( $key, ' ' ) === false ) ) {
			return 'elements';
		}

		return 'market';
	}

	/**
	 * Whether this site is running on an Envato Elements licence.
	 */
	public static function is_elements_license() {
		return 'elements' === self::license_source();
	}

	/**
	 * Licensing terms per store: URL and the store's display name.
	 *
	 * Shared with the registration form so a wrong link can only ever be wrong in one place — the
	 * vamtam.com entry pointed at /terms-conditions/Licensing, which has 404'd since it was added.
	 */
	public static function license_terms() {
		return array(
			'vamtam'   => array( 'url' => 'https://vamtam.com/terms-conditions/',      'label' => 'vamtam.com' ),
			'market'   => array( 'url' => 'https://themeforest.net/licenses/standard', 'label' => 'Envato Market' ),
			'elements' => array( 'url' => 'https://elements.envato.com/license-terms', 'label' => 'Envato Elements' ),
		);
	}

	/**
	 * The licensing terms for the store this licence actually came from.
	 */
	public static function license_terms_url() {
		$terms  = self::license_terms();
		$source = self::license_source();

		return isset( $terms[ $source ] ) ? $terms[ $source ]['url'] : $terms['vamtam']['url'];
	}

	/**
	 * This theme's page on vamtam.com, where a customer who needs more sites buys another code.
	 * Tagged separately from the Elements upgrade link so the two intents stay distinguishable.
	 */
	public static function extra_license_url() {
		$slug = defined( 'VAMTAM_THEME_SLUG' ) ? VAMTAM_THEME_SLUG : wp_get_theme()->get_template();

		return 'https://vamtam.com/themes/' . rawurlencode( $slug ) . '/?utm_source=theme&utm_medium=reuse-notice&utm_campaign=extra-license';
	}

	/**
	 * Offered only to licences that actually entitle the customer to more sites. Elements gets the
	 * upgrade link instead, because a second Elements code still would not include updates.
	 */
	public static function extra_license_note() {
		if ( self::is_elements_license() ) {
			return;
		}
		?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %1$s-%2$s: link to this theme's page on vamtam.com. */
						__( '%1$sYou may buy additional purchase codes%2$s if you need to activate this theme on multiple sites.', 'innovecouture' ),
						'<a href="' . esc_url( self::extra_license_url() ) . '" target="_blank">',
						'</a>'
					),
					'vamtam-a-span'
				);
				?>
			</p>
		<?php
	}

	/**
	 * Whether the stored key is an Elements code in the superseded long-form format.
	 */
	public static function has_legacy_elements_code() {
		if ( ! self::is_elements_license() ) {
			return false;
		}

		$key = trim( (string) self::get_purchase_code() );

		return '' !== $key && ! preg_match( '/^[A-Z0-9]{10}$/i', $key );
	}

	public static function license_elements_legacy() {
		self::notice_open();
		?>
						<h3>Attention:</h3>
						<p>Envato Elements has changed its licence code format, and the code registered
						on this site is in the old format. Please register your current Elements licence
						code to keep receiving update notifications.</p>
						<p>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %1$s-%2$s: link to Envato's guide to creating a licence code. */
									__( 'You can find it in your Envato Elements account, or %1$screate a new licence code%2$s.', 'innovecouture' ),
									'<a href="' . esc_url( self::elements_new_code_url() ) . '" target="_blank">',
									'</a>'
								),
								'vamtam-a-span'
							);
							?>
						</p>
						<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
							<?php echo esc_html__( 'Register Now', 'innovecouture' ); ?>
						</a>
		<?php
		self::notice_close();
		?>
		<?php
	}

	/**
	 * Envato's own guide to creating another Elements licence code. Elements is one code per
	 * domain and codes are free to generate, so the fix for a second site is a new code — not
	 * unregistering the first one.
	 */
	public static function elements_new_code_url() {
		return 'https://help.elements.envato.com/hc/en-us/articles/360000621763-How-to-Create-a-New-License-on-Envato';
	}

	public static function contact_url() {
		return 'https://vamtam.com/contact-us/';
	}

	public static function validation_help_url() {
		return 'https://elementor.support.vamtam.com/support/solutions/articles/252289-cannot-validate-purchase-key-or-token';
	}

	/**
	 * What this store calls its code. Envato Elements issues a "licence code"; ThemeForest and
	 * vamtam.com issue a "purchase code". Using the wrong noun sends people hunting for something
	 * that does not exist in their account.
	 */
	public static function license_code_label( $plural = false ) {
		if ( self::is_elements_license() ) {
			return $plural ? 'licence codes' : 'licence code';
		}

		return $plural ? 'purchase codes' : 'purchase code';
	}

	/**
	 * Where an Elements customer goes for a licence that adds automatic updates and support. Same
	 * landing page the update placeholders use, with its own medium and campaign so the two can be
	 * told apart in analytics.
	 */
	public static function elements_upgrade_url() {
		return 'https://vamtam.com/promo-envato-elements/?utm_source=theme&utm_medium=reuse-notice&utm_campaign=elements-cap';
	}

	/**
	 * One message for every "this code is on more sites than it is allowed" situation: refused at
	 * the gate, banned by the recount, or blocked by the Elements one-domain rule. They differ
	 * only in which code path noticed and how many sites are affected — the cause and the remedy
	 * are identical, so three different notices only made it harder to act on.
	 *
	 * The host list is shown when we know it. The ban arrives as a bare 499 with no body, so the
	 * copy has to read correctly without it.
	 */
	public static function license_cap_reached() {
		$hosts       = array_filter( (array) get_option( '_vamtam_license_hosts', array() ) );
		$is_elements = self::is_elements_license();
		$count       = count( $hosts );
		self::notice_open();
		?>
						<h3>Attention:</h3>

						<?php
						// The ban arrives as a bare 499 with no host list, so the only thing that may
						// differ between the two is the count and the list itself. Everything after
						// this point reads identically either way.
						$where = $is_elements
							? ( $count ? sprintf( _n( '%s other site', '%s other sites', $count, 'innovecouture' ), number_format_i18n( $count ) ) : __( 'another site', 'innovecouture' ) )
							: ( $count ? sprintf( _n( '%s site', '%s sites', $count, 'innovecouture' ), number_format_i18n( $count ) ) : __( 'too many sites', 'innovecouture' ) );
						?>
						<?php if ( $is_elements ) : ?>
							<?php // Elements does not get Automatic Updates either way, so framing this
							// as a blocked update would be wrong. The one-domain limit still applies. ?>
							<p>This Envato Elements licence code is already registered on
							<?php echo esc_html( $where ); ?>. Elements licence codes cover one domain
							each.</p>
						<?php else : ?>
							<p>An update is available, but this purchase code is already registered on
							<?php echo esc_html( $where ); ?>, so it cannot be installed here.</p>
						<?php endif; ?>

						<?php if ( $hosts ) : ?>
							<?php // Not a <ul>: the theme's admin CSS sets list-style:none and zero
							// padding inside notices, which renders the hosts as unlabelled body text. ?>
							<p class="vamtam-license-hosts">
								<?php foreach ( $hosts as $host ) : ?>
									<code><?php echo esc_html( $host ); ?></code>&nbsp;
								<?php endforeach; ?>
							</p>
						<?php endif; ?>

						<?php if ( $is_elements ) : ?>
							<?php // Elements codes are one per domain and free to generate, so the fix
							// is a new code for this site — telling them to unregister the other one
							// sends them to remove a working site for no reason. ?>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %1$s-%2$s: link to Envato's guide to creating a new licence code. */
										__( 'You need a separate licence code for each domain. %1$sCreate a new licence code for this site%2$s.', 'innovecouture' ),
										'<a href="' . esc_url( self::elements_new_code_url() ) . '" target="_blank">',
										'</a>'
									),
									'vamtam-a-span'
								);
								?>
							</p>
						<?php elseif ( 1 === $count ) : ?>
							<p>Unregister the code on that site and it becomes available here.</p>
						<?php else : ?>
							<?php // "those sites" would be a dangling reference when there is no list. ?>
							<p>Unregister the code on one of the other sites and every site using it works again.</p>
						<?php endif; ?>

						<?php if ( $is_elements ) : ?>
							<p>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %1$s-%2$s: link to the vamtam.com licence page. */
										__( 'Envato Elements licences do not include Automatic Updates or support. To add those, %1$sget a licence from vamtam.com%2$s.', 'innovecouture' ),
										'<a href="' . esc_url( self::elements_upgrade_url() ) . '" target="_blank">',
										'</a>'
									),
									'vamtam-a-span'
								);
								?>
							</p>
						<?php else : ?>
							<?php self::extra_license_note(); ?>
						<?php endif; ?>

						<?php // the AJAX response has no page URL to link back to ?>
						<?php if ( ! wp_doing_ajax() ) : ?>
							<p>
								<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'vamtam-recheck-license', '1' ), 'vamtam-recheck-license' ) ); ?>">
									<?php esc_html_e( 'Re-check now', 'innovecouture' ); ?>
								</a>
							</p>
						<?php endif; ?>
		<?php
		self::notice_close();
		?>
		<?php
	}

	public static function token_unregistered() {
		echo '<span id="success">';
		esc_html_e('Unregistered License Code.', 'innovecouture');
		echo '</span>';
	}

	public static function token_unregister_fail() {
		echo '<span id="fail">';
		esc_html_e('Failed to unregister license code. Please try again later. If this issue persists please contact support.', 'innovecouture');
		echo '</span>';
	}

	public static function license_unregister() {
		echo '<span id="success">';
		esc_html_e( 'Unregistered Key.', 'innovecouture' );
		echo '</span>';
	}

	public static function license_register() {
		if ( get_transient( 'vamtam_dismissed_license_notice' ) ) {
			return;
		}

		$theme_name = ucfirst( wp_get_theme()->get_template() );

		self::notice_open( 'warning is-dismissible' );
		?>
						<h2>Welcome to <?php echo esc_html( $theme_name ); ?>!</h2>
						<p>Please activate your license to get theme updates, premium support, and access to demo content.</p>
						<a class="button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vamtam_theme_setup' ) ); ?>">
							<?php echo esc_html__( 'Register Now', 'innovecouture' ); ?>
						</a>
		<?php
		self::notice_close();
		?>
		<?php
	}
}


