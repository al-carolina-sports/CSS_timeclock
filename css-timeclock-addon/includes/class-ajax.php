<?php
/**
 * Public kiosk AJAX and admin PIN AJAX.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers privileged and nopriv endpoints. Kiosk auth is PIN, not WP login.
 */
class Css_Tc_Ajax {

	const PUBLIC_NONCE = 'css_tc_kiosk';
	const ADMIN_NONCE  = 'css_tc_admin';

	/**
	 * @return void
	 */
	public static function register() {
		$self = new self();

		add_action( 'wp_ajax_css_tc_resolve_pin', array( $self, 'resolve_pin' ) );
		add_action( 'wp_ajax_nopriv_css_tc_resolve_pin', array( $self, 'resolve_pin' ) );
		add_action( 'wp_ajax_css_tc_punch', array( $self, 'punch' ) );
		add_action( 'wp_ajax_nopriv_css_tc_punch', array( $self, 'punch' ) );
		add_action( 'wp_ajax_css_tc_employees', array( $self, 'employees' ) );
		add_action( 'wp_ajax_nopriv_css_tc_employees', array( $self, 'employees' ) );
		add_action( 'wp_ajax_css_tc_roster', array( $self, 'roster' ) );
		add_action( 'wp_ajax_nopriv_css_tc_roster', array( $self, 'roster' ) );

		add_action( 'wp_ajax_css_tc_save_settings', array( $self, 'save_settings' ) );
		add_action( 'wp_ajax_css_tc_save_pin', array( $self, 'save_pin' ) );
		add_action( 'wp_ajax_css_tc_clear_pin', array( $self, 'clear_pin' ) );
		add_action( 'wp_ajax_css_tc_create_pages', array( $self, 'create_pages' ) );
	}

	/**
	 * @return void
	 */
	public function resolve_pin() {
		$this->verify_public_nonce();

		$settings = css_tc_addon()->get_settings();
		$mode     = isset( $_POST['kiosk'] ) ? sanitize_key( wp_unslash( $_POST['kiosk'] ) ) : 'pin';

		if ( 'name' === $mode && empty( $settings['name_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The name-list kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}
		if ( 'name' !== $mode && empty( $settings['pin_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The PIN kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}

		$limited = css_tc_addon()->pins->assert_not_rate_limited();
		if ( is_wp_error( $limited ) ) {
			wp_send_json_error( array( 'message' => $limited->get_error_message() ), 429 );
		}

		$pin     = css_tc_addon()->pins->normalize( isset( $_POST['pin'] ) ? wp_unslash( $_POST['pin'] ) : '' );
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( '' === $pin ) {
			css_tc_addon()->pins->record_failure();
			wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
		}

		if ( $user_id > 0 ) {
			if ( ! css_tc_addon()->employees->is_kiosk_employee( $user_id ) ) {
				css_tc_addon()->pins->record_failure();
				wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
			}
			$ok = css_tc_addon()->pins->verify_for_user( $user_id, $pin );
			if ( ! $ok ) {
				css_tc_addon()->pins->record_failure();
				wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
			}
		} else {
			$user_id = css_tc_addon()->pins->find_user_id_by_pin( $pin );
			if ( $user_id < 1 || ! css_tc_addon()->employees->is_employee( $user_id ) ) {
				css_tc_addon()->pins->record_failure();
				wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
			}
		}

		css_tc_addon()->pins->record_success();

		$open = css_tc_addon()->punches->open_shift_for( $user_id );

		wp_send_json_success(
			array(
				'user_id'       => $user_id,
				'name'          => css_tc_addon()->employees->greeting_name( $user_id ),
				'department'    => css_tc_addon()->employees->department( $user_id ),
				'is_clocked_in' => $open['is_clocked_in'],
				'clock_in_time' => $open['clock_in_time'],
				'next_action'   => $open['is_clocked_in'] ? 'clock_out' : 'clock_in',
			)
		);
	}

	/**
	 * @return void
	 */
	public function punch() {
		$this->verify_public_nonce();

		$settings = css_tc_addon()->get_settings();
		$source   = isset( $_POST['kiosk'] ) ? sanitize_key( wp_unslash( $_POST['kiosk'] ) ) : 'pin_kiosk';
		if ( 'name' === $source ) {
			$source = 'name_kiosk';
		} elseif ( 'pin' === $source ) {
			$source = 'pin_kiosk';
		}

		if ( 'name_kiosk' === $source && empty( $settings['name_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The name-list kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}
		if ( 'name_kiosk' !== $source && empty( $settings['pin_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The PIN kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}

		$limited = css_tc_addon()->pins->assert_not_rate_limited();
		if ( is_wp_error( $limited ) ) {
			wp_send_json_error( array( 'message' => $limited->get_error_message() ), 429 );
		}

		$pin        = css_tc_addon()->pins->normalize( isset( $_POST['pin'] ) ? wp_unslash( $_POST['pin'] ) : '' );
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$clock_act  = isset( $_POST['clock_action'] ) ? sanitize_key( wp_unslash( $_POST['clock_action'] ) ) : '';

		if ( ! in_array( $clock_act, array( 'clock_in', 'clock_out' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown clock action.', 'css-timeclock-addon' ) ), 400 );
		}

		if ( $user_id > 0 ) {
			if ( ! css_tc_addon()->pins->verify_for_user( $user_id, $pin ) ) {
				css_tc_addon()->pins->record_failure();
				wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
			}
		} else {
			$user_id = css_tc_addon()->pins->find_user_id_by_pin( $pin );
			if ( $user_id < 1 ) {
				css_tc_addon()->pins->record_failure();
				wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
			}
		}

		if ( ! css_tc_addon()->employees->is_employee( $user_id ) ) {
			css_tc_addon()->pins->record_failure();
			wp_send_json_error( array( 'message' => __( 'That PIN was not recognized.', 'css-timeclock-addon' ) ), 403 );
		}

		css_tc_addon()->pins->record_success();

		if ( 'clock_in' === $clock_act ) {
			$result = css_tc_addon()->punches->clock_in( $user_id, $source );
		} else {
			$result = css_tc_addon()->punches->clock_out( $user_id, $source );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
		}

		$result['name'] = css_tc_addon()->employees->greeting_name( $user_id );
		wp_send_json_success( $result );
	}

	/**
	 * Alphabetical employee list for the name kiosk.
	 *
	 * @return void
	 */
	public function employees() {
		$this->verify_public_nonce();

		$settings = css_tc_addon()->get_settings();
		if ( empty( $settings['name_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The name-list kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}

		$list = css_tc_addon()->employees->list_for_kiosk( true );

		wp_send_json_success(
			array(
				'employees' => $list,
				'count'     => count( $list ),
			)
		);
	}

	/**
	 * Public who's-working board for logged-out kiosk tablets.
	 *
	 * @return void
	 */
	public function roster() {
		$this->verify_public_nonce();

		$settings = css_tc_addon()->get_settings();
		if ( empty( $settings['pin_kiosk_enabled'] ) && empty( $settings['name_kiosk_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The kiosk is disabled.', 'css-timeclock-addon' ) ), 403 );
		}

		$limited = $this->assert_roster_not_rate_limited();
		if ( is_wp_error( $limited ) ) {
			wp_send_json_error( array( 'message' => $limited->get_error_message() ), 429 );
		}

		wp_send_json_success( css_tc_addon()->punches->public_board() );
	}

	/**
	 * @return void
	 */
	public function save_settings() {
		$this->verify_admin();

		$settings = css_tc_addon()->get_settings();
		$settings['pin_kiosk_enabled']  = empty( $_POST['pin_kiosk_enabled'] ) ? 0 : 1;
		$settings['name_kiosk_enabled'] = empty( $_POST['name_kiosk_enabled'] ) ? 0 : 1;
		$settings['pin_min_length']     = min( 8, max( 4, isset( $_POST['pin_min_length'] ) ? absint( $_POST['pin_min_length'] ) : 4 ) );
		$settings['pin_max_length']     = min( 12, max( $settings['pin_min_length'], isset( $_POST['pin_max_length'] ) ? absint( $_POST['pin_max_length'] ) : 8 ) );
		$settings['rate_limit_max']     = min( 20, max( 3, isset( $_POST['rate_limit_max'] ) ? absint( $_POST['rate_limit_max'] ) : 5 ) );
		$settings['rate_limit_window']  = min( 3600, max( 60, isset( $_POST['rate_limit_window'] ) ? absint( $_POST['rate_limit_window'] ) : 900 ) );
		$settings['idle_reset_ms']      = min( 30000, max( 3000, isset( $_POST['idle_reset_ms'] ) ? absint( $_POST['idle_reset_ms'] ) : 8000 ) );

		css_tc_addon()->update_settings( $settings );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings saved.', 'css-timeclock-addon' ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * @return void
	 */
	public function save_pin() {
		$this->verify_admin();

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$pin     = isset( $_POST['pin'] ) ? wp_unslash( $_POST['pin'] ) : '';

		$result = css_tc_addon()->pins->set_pin( $user_id, $pin );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'PIN saved. The digits are stored as a hash only.', 'css-timeclock-addon' ),
				'user_id'  => $user_id,
				'has_pin'  => true,
				'set_at'   => wp_date( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'g:i a' ) ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function clear_pin() {
		$this->verify_admin();

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( $user_id < 1 || ! css_tc_addon()->employees->is_employee( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid employee.', 'css-timeclock-addon' ) ), 400 );
		}

		css_tc_addon()->pins->clear_pin( $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'PIN removed.', 'css-timeclock-addon' ),
				'user_id' => $user_id,
				'has_pin' => false,
			)
		);
	}

	/**
	 * @return void
	 */
	public function create_pages() {
		$this->verify_admin();

		$ids = Css_Tc_Shortcodes::create_kiosk_pages();

		wp_send_json_success(
			array(
				'message' => __( 'Kiosk pages are ready.', 'css-timeclock-addon' ),
				'pages'   => $ids,
			)
		);
	}

	/**
	 * Soft IP throttle for the public roster poll (separate from the PIN lock).
	 *
	 * @return true|WP_Error
	 */
	private function assert_roster_not_rate_limited() {
		$key   = css_tc_addon()->pins->client_key() . '_roster';
		$count = (int) get_transient( $key );
		$max   = 40;

		if ( $count >= $max ) {
			return new WP_Error(
				'css_tc_roster_limited',
				__( 'Please wait a moment and try again.', 'css-timeclock-addon' )
			);
		}

		set_transient( $key, $count + 1, 60 );

		return true;
	}

	/**
	 * @return void
	 */
	private function verify_public_nonce() {
		if ( ! check_ajax_referer( self::PUBLIC_NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Refresh the kiosk page.', 'css-timeclock-addon' ) ), 403 );
		}
	}

	/**
	 * @return void
	 */
	private function verify_admin() {
		if ( ! check_ajax_referer( self::ADMIN_NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Refresh the page.', 'css-timeclock-addon' ) ), 403 );
		}
		if ( ! Css_Tc_Plugin::user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage kiosk settings.', 'css-timeclock-addon' ) ), 403 );
		}
	}
}
