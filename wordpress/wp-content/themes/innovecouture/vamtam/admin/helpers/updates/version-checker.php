<?php

/*
 * Vamtam CRM Integration, used to check for updates and aiding support queries
 */

class Version_Checker {
	public $remote;
	public $interval;
	public $notice;

	private $update_api_prefix = 'https://updates.vamtam.com/0/envato/';

	private $update_api_url;
	private $validate_api_url;

	private static $instance;

	public static $VALID_LICENSE   = 'VALIDATED';
	public static $INVALID_LICENSE = 'INVALID';

	/**
	 * A valid licence is cheap to leave alone. An invalid one is waiting to recover — a customer
	 * who has just unregistered a site must not sit behind a week-long interval after the ban has
	 * already lifted server-side.
	 */
	const RECHECK_VALID   = 604800; // 7 days
	const RECHECK_INVALID = 3600;   // 1 hour

	/**
	 * Back-off after an unreachable server. Failing open without this would mean a blocking HTTP
	 * request on every single admin_init for as long as the server is down.
	 */
	const RECHECK_BACKOFF = 3600;

	/**
	 * Whether the licence is due to be revalidated against the server.
	 */
	public static function license_check_due() {
		if ( get_transient( 'vamtam_license_recheck_backoff' ) ) {
			return false;
		}

		$state = VamtamFramework::license();

		if ( false === $state || '' === $state ) {
			return true;
		}

		$checked_at = (int) get_option( '_vamtam_license_checked_at', 0 );
		$interval   = ( self::$VALID_LICENSE === $state ) ? self::RECHECK_VALID : self::RECHECK_INVALID;

		return ( time() - $checked_at ) > $interval;
	}

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->remote   = 'https://api.vamtam.com/version';
		$this->interval = 24 * 3600;

		$this->update_api_url   = $this->update_api_prefix . 'check-theme';
		$this->validate_api_url = $this->update_api_prefix . 'validate-license';

		if( wp_doing_ajax() && isset( $_POST['action'] ) && $_POST['action'] === 'vcrm-check-version' ) {
			$this->check_version_crm();
			wp_die();
		}

		if ( ! isset( $_GET['import'] ) && ( ! isset( $_GET['step'] ) || (int) $_GET['step'] != 2 ) ) {
			add_action( 'admin_init', array( $this, 'check_version' ) );
		}

		add_action( 'wp_ajax_vamtam-check-license', array( $this, 'check_license' ) );
		add_action( 'vamtam_saved_options', array( $this, 'check_version' ) );

		if ( self::license_check_due() ) {
			$this->check_license( [ 'no_ajax' => true ] );
		}
		// Hooked unconditionally: check_banned_key() also owns the Re-check button, which is
		// precisely what a site with an invalid licence needs to get out of that state.
		add_action( 'admin_init', array( $this, 'check_banned_key' ) );

		// set_site_transient('update_themes', null);

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_update' ) );
		add_filter( 'site_transient_update_themes', [ $this, 'prevent_wp_org_conflicts' ] );
	}

	public function prevent_wp_org_conflicts( $updates ) {
		// prevent conflicts with themes hosted on wp.org
		$theme_name = wp_get_theme()->get_template();

		if (
			isset( $updates->response ) &&
			isset( $updates->response[ $theme_name ] ) &&
			isset( $updates->response[ $theme_name ]['package'] ) &&
			strpos( $updates->response[ $theme_name ]['package'], 'downloads.wordpress.org' ) !== false
		) {
			unset( $updates->response[ $theme_name ] );
		}

		return $updates;
	}

	public function check_update( $updates ) {
		$theme_name = wp_get_theme()->get_template();

		$updates = $this->prevent_wp_org_conflicts( $updates );

		$response = $this->update_api_request( $updates );

		if ( false === $response ) {
			// No update is available.
			$item = array(
				'theme'        => $theme_name,
				'new_version'  => VamtamFramework::get_version(),
				'url'          => '',
				'package'      => '',
				'requires'     => '',
				'requires_php' => '',
			);

			// Adding the "mock" item to the `no_update` property is required
			// for the enable/disable auto-updates links to correctly appear in UI.
			$updates->no_update[ $theme_name ] = $item;

			return $updates;
		}

		if ( ! isset( $updates->response ) ) {
			$updates->response = array();
		}

		// Prefix normalization so update notices display properly.
		$prefixed_theme_name = 'vamtam-' . $theme_name;
		if ( isset( $response[ $prefixed_theme_name ] ) && $theme_name !== $prefixed_theme_name ) {
			$response[ $theme_name ]            = $response[ $prefixed_theme_name ];
			$response[ $theme_name ][ 'theme' ] = $theme_name;
			unset( $response[ $prefixed_theme_name ] );
		}

		$updates->response = array_merge( $updates->response, $response );

		// Small trick to ensure the updates get shown in the network admin
		if ( is_multisite() && ! is_main_site() ) {
			global $current_site;

			switch_to_blog( $current_site->blog_id );
			set_site_transient( 'update_themes', $updates );
			restore_current_blog();
		}

		return $updates;
	}

	private function update_api_request( $update_cache ) {
		$theme_name = wp_get_theme()->get_template();

		$raw_response = wp_remote_post( $this->update_api_url, array(
			'body' => array(
				'theme_name'   => VAMTAM_THEME_NAME,
				'version'      => isset( $update_cache->checked[ $theme_name ] ) ? $update_cache->checked[ $theme_name ] : VamtamFramework::get_version(),
				'purchase_key' => self::is_valid_purchase_code() ? apply_filters( 'vamtam_purchase_code', '' ) : '',
			),
			'user-agent' => VamtamFramework::get_user_agent(),
		) );

		if ( is_wp_error( $raw_response ) || 200 !== wp_remote_retrieve_response_code( $raw_response ) ) {
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $raw_response ), true );

		return $response['themes'];
	}

	private function normalize_key_source( $key_source, $key ) {
		$key_source = sanitize_key( (string) $key_source );

		if ( in_array( $key_source, array( 'vamtam', 'market', 'elements' ), true ) ) {
			return $key_source;
		}

		return VamtamFramework::detect_key_source( $key );
	}

	private function get_validate_request_body( $key, $key_source ) {
		if ( $key_source === 'elements' ) {
			return array(
				'api' => '2',
				'token' => $key,
				'ext_id' => md5( get_site_url() ),
				'host' => get_home_url(),
				'theme' => wp_get_theme()->get( 'Name' ),
				'slug' => VAMTAM_THEME_SLUG,
			);
		}

		return array(
			'api' => '2',
			'purchase_key' => $key,
			'theme_name' => VAMTAM_THEME_NAME,
			'key_source' => $key_source,
			'host' => get_home_url(),
		);
	}


	public function check_license( $args ) {
		$no_ajax = isset( $args['no_ajax'] ) ? $args['no_ajax'] : false;

		//if the func is called from the server we dont need to check_ajax_referer.
		if ( ! $no_ajax ) {
			check_ajax_referer( 'vamtam-check-license', 'nonce' );
		}

		$should_unregister = isset( $_POST['unregister'] ) && vamtam_sanitize_bool( $_POST['unregister'] );
		$is_token          = isset( $_POST['is_token'] ) && vamtam_sanitize_bool( $_POST['is_token'] );
		$key_source        = isset( $_POST['key_source'] ) ? sanitize_key( wp_unslash( $_POST['key_source'] ) ) : '';

		if ( $should_unregister ) {
			$is_token = (bool) get_option( VamtamFramework::get_token_option_key() );
			$token    = VamtamFramework::get_purchase_code();

			if ( ! empty( $token ) ) {
				// Elements tokens also have a download row server-side; purchase codes only hold a
				// slot. Both have to tell the server, or the slot is never released and the
				// customer waits out the reuse window for a site they have already removed.
				$unregister_body = $is_token
					? array(
						'token'      => $token,
						'ext_id'     => md5( get_site_url() ),
						'host'       => get_home_url(),
						'theme'      => wp_get_theme()->get( 'Name' ),
						'unregister' => '1',
					)
					: array(
						'purchase_key' => $token,
						'host'         => get_home_url(),
						'theme_name'   => VAMTAM_THEME_NAME,
						'unregister'   => '1',
					);

				$raw_response = wp_remote_post( $this->validate_api_url, array(
					'body'       => $unregister_body,
					'user-agent' => VamtamFramework::get_user_agent(),
				) );

				if ( ! is_wp_error( $raw_response ) ) {
					if ( $raw_response['response']['code'] >= 200 && $raw_response['response']['code'] < 300 ) {
						VamtamFramework::token_unregistered();
					} else {
						VamtamFramework::token_unregister_fail();
						return false;
					}
				} else {
					if ( ! $no_ajax ) {
						die;
					}
					return false;
				}
			} else {
				// Unregistering an empty key is a local-only cleanup.
				VamtamFramework::token_unregistered();
			}

			delete_option( '_vamtam_license_reason' );
			delete_option( '_vamtam_license_hosts' );
			delete_option( '_vamtam_license_checked_at' );
			delete_option( VamtamFramework::get_token_option_key() );
			delete_option( VamtamFramework::get_purchase_code_option_key() );
			delete_option( '_vamtam_license_source' );
			VamtamFramework::license( self::$INVALID_LICENSE );

			if ( empty( $token ) ) {
				return;
			}
		}

		$key = $no_ajax && ! $should_unregister ? VamtamFramework::get_purchase_code() : $_POST['license-key'];
		$key_source = $no_ajax && ! $should_unregister ? get_option( '_vamtam_license_source', $key_source ) : $key_source;
		$key_source = $this->normalize_key_source( $key_source, $key );
		$is_token = ( $key_source === 'elements' );

		if ( ! empty( $key ) ) {
			// A cached VALIDATED is exactly what this stops trusting: a refunded, deleted or
			// over-cap key used to keep working forever because the site never asked again.
			{
				$args = $this->get_validate_request_body( $key, $key_source );

				$raw_response = wp_remote_post( $this->validate_api_url, array(
					'body' => $args,
					'user-agent' => VamtamFramework::get_user_agent(),
				) );

				if ( ! is_wp_error( $raw_response ) ) {
					$code = (int) $raw_response['response']['code'];

					if ( $code >= 200 && $code < 300 ) {
						if ( ! $no_ajax ) {
							VamtamFramework::license_valid();
						}
						VamtamFramework::license( self::$VALID_LICENSE );
						update_option( '_vamtam_license_checked_at', time() );
						delete_option( '_vamtam_license_reason' );
						delete_option( '_vamtam_license_hosts' );
						update_option( '_vamtam_license_source', $key_source );
						if ( $is_token ) {
							update_option( VamtamFramework::get_token_option_key(), '1' );
						} else {
							delete_option( VamtamFramework::get_token_option_key() );
						}
					} elseif ( $code >= 500 || 424 === $code ) {
						// Fail open: the server is having a bad day, the customer is not. State is
						// left exactly as it was, and the back-off keeps wp-admin responsive.
						//
						// 424 is included deliberately. The server now answers 5xx for its own
						// failures, but this theme ships days after that change and must not depend
						// on it: on an older server 424 still covers Envato rate limits and DB
						// errors, and reading those as "your licence is invalid" would banner
						// paying customers during an outage.
						set_transient( 'vamtam_license_recheck_backoff', 1, self::RECHECK_BACKOFF );

						// Failing open is right for a background check. Someone who just clicked
						// Check is waiting for an answer, and silence reads as a broken button —
						// so say which kind of failure it was.
						if ( ! $no_ajax ) {
							if ( 424 === $code ) {
								VamtamFramework::license_invalid();
							} else {
								VamtamFramework::license_failed();
							}
						}
					} else {
						$body   = json_decode( wp_remote_retrieve_body( $raw_response ), true );
						$reason = 'invalid';

						if ( 409 === $code ) {
							$reason = 'cap-reached';
							update_option( '_vamtam_license_hosts', isset( $body['hosts'] ) ? (array) $body['hosts'] : array() );
						} elseif ( 499 === $code ) {
							$reason = 'banned';
						} elseif ( isset( $body['reason'] ) ) {
							// The server names what went wrong. Anything it does not name stays
							// 'invalid', which is the only case where "check your code" is the
							// right advice.
							// 'not-found' (a well-formed VAMTAM code we do not hold) is deliberately
							// absent: it falls through to 'invalid', which is what it looks like to
							// the customer. The server still reports it separately in its own logs.
							$known = array( 'revoked', 'expired', 'invalid' );

							if ( in_array( $body['reason'], $known, true ) ) {
								$reason = $body['reason'];
							}
						}

						if ( ! $no_ajax ) {
							// 499 (the key is banned) and 490 (this Elements code is registered on
							// another domain) are the same situation as the gate's refusal: the code
							// is on more sites than it is allowed.
							if ( 499 === $code || ( $is_token && 490 === $code ) ) {
								VamtamFramework::license_cap_reached();
							} else {
								VamtamFramework::license_invalid();
							}
						}

						VamtamFramework::license( self::$INVALID_LICENSE );
						update_option( '_vamtam_license_checked_at', time() );
						update_option( '_vamtam_license_reason', $reason );
					}
				} else {
					// Transport failure — same fail-open rule, same back-off.
					set_transient( 'vamtam_license_recheck_backoff', 1, self::RECHECK_BACKOFF );

					if ( ! $no_ajax ) {
						VamtamFramework::license_failed();
					}
				}

				$this->check_version();
			}
		} else if ( $should_unregister ) {
			VamtamFramework::license_unregister();
		}

		if ( ! $no_ajax ) {
			die;
		}
	}

	public function check_banned_key( $args ) {
		$key = VamtamFramework::get_purchase_code();

		if ( ! empty( $key ) ) {
			$transient_key = 'vamtam_banned_key_check_' . md5( $key );

			if (
				isset( $_GET['vamtam-recheck-license'] ) &&
				current_user_can( 'manage_options' ) &&
				check_admin_referer( 'vamtam-recheck-license' )
			) {
				delete_transient( $transient_key );

				// Without these the licence state stands for up to an hour, so a customer who has
				// just freed a slot would still be looking at the banner after clicking Re-check.
				delete_transient( 'vamtam_license_recheck_backoff' );
				delete_option( '_vamtam_license_checked_at' );
				$this->check_license( array( 'no_ajax' => true ) );
			}

			$raw_response = get_transient( $transient_key );

			if ( $raw_response === false ) {
				$raw_response = wp_remote_post( 'https://updates.vamtam.com/api/check-banned-key', [
					'body' => [
						'purchase_key' => $key,
					],
					'user-agent' => VamtamFramework::get_user_agent(),
				] );

				set_transient( $transient_key, $raw_response, DAY_IN_SECONDS );
			}

			if ( ! is_wp_error( $raw_response ) ) {
				if ( $raw_response['response']['code'] === 499 ) {
					// Record the state rather than rendering here: notice_early() already owns
					// admin_notices and would otherwise print a second, identical banner now that
					// the heartbeat also marks a banned key INVALID. Going through
					// license_notice() also keeps D10 — a legacy Elements code is asked to
					// re-register rather than told it is over its cap.
					VamtamFramework::license( self::$INVALID_LICENSE );
					update_option( '_vamtam_license_reason', 'banned' );

					add_action('vamtam_theme_setup_notices', array('VamtamFramework', 'license_notice'));
				}
			}
		}
	}

	public static function is_valid_purchase_code() {
		if ( apply_filters( 'vamtam_purchase_code_import_override', false ) ) {
			return true;
		}

		return VamtamFramework::license() === self::$VALID_LICENSE;
	}

	public function check_version() {
		$local_version = VamtamFramework::get_version();
		$key           = VAMTAM_THEME_SLUG . '_' . $local_version;

		$last_license_key    = get_option( VamtamFramework::get_purchase_code_option_key() . '-old' );
		$current_license_key = VamtamFramework::get_purchase_code();

		$system_status_opt_out_old = get_option( 'vamtam-system-status-opt-in-old' );
		$system_status_opt_out     = get_option( 'vamtam-system-status-opt-in', true );

		if ( $last_license_key !== $current_license_key || $system_status_opt_out_old != $system_status_opt_out || false === get_transient( $key ) ) {
			$data = array(
				'user-agent' => VamtamFramework::get_user_agent(),
				'blocking'   => false,
				'body'       => array(
					'theme_version'  => $local_version,
					'php_version'    => phpversion(),
					'server'         => getenv('SERVER_SOFTWARE'),
					'theme_name'     => VAMTAM_THEME_NAME,
					'license_key'    => $current_license_key,
					'is_token'       => get_option( VamtamFramework::get_token_option_key() ),
					'active_plugins' => self::active_plugins(),
					'system_status'  => self::system_status(),
				),
			);

			if ( $last_license_key !== $current_license_key ) {
				update_option( VamtamFramework::get_purchase_code_option_key() . '-old', $current_license_key );
			}

			if ( $system_status_opt_out_old != $system_status_opt_out ) {
				update_option( 'vamtam-system-status-opt-in-old', $system_status_opt_out );
			}

			wp_remote_post( $this->remote, $data );

			set_transient( $key, true, $this->interval ); // cache
		}
	}

	public function check_version_crm() {
		$data = array(
			'user-agent' => VamtamFramework::get_user_agent(),
			'blocking'   => false,
			'body'       => array(
				'theme_version'  => VamtamFramework::get_version(),
				'php_version'    => phpversion(),
				'server'         => getenv('SERVER_SOFTWARE'),
				'theme_name'     => VAMTAM_THEME_NAME,
				'license_key'    => VamtamFramework::get_purchase_code(),
				'is_token'       => get_option( VamtamFramework::get_token_option_key() ),
				'active_plugins' => self::active_plugins(),
				'system_status'  => self::system_status(),
			),
		);

		wp_remote_post( $this->remote, $data );
	}

	public static function active_plugins() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return $active_plugins;
	}

	public static function system_status() {
		if ( ! get_option( 'vamtam-system-status-opt-in', true ) ) {
			return array(
				'disabled' => true,
			);
		}

		$result = array(
			'disabled'         => false,
			'wp_debug'         => WP_DEBUG,
			'wp_debug_display' => WP_DEBUG_DISPLAY,
			'wp_debug_log'     => WP_DEBUG_LOG,
			'active_plugins'   => array(),
			'writable'         => array(),
			'diagnostics'      => VamtamDiagnostics::tests( true ),
		);

		if ( function_exists( 'ini_get' ) ) {
			$result['max_input_vars']     = ini_get( 'max_input_vars' );
			$result['max_execution_time'] = ini_get( 'max_execution_time' );
		}

		$active_plugins = self::active_plugins();

		foreach ( $active_plugins as $plugin ) {
			$plugin_data = @get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

			$result['active_plugins'][ $plugin ] = array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'author'  => $plugin_data['AuthorName'],
			);
		}

		$result['wp_remote_post'] = 'Irrelevant';

		return $result;
	}
}

Version_Checker::get_instance();

