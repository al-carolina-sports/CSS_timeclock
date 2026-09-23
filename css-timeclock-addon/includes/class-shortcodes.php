<?php
/**
 * Kiosk shortcodes and page creation.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [css_tc_pin_kiosk], [css_tc_name_kiosk], and [css_tc_my_times].
 */
class Css_Tc_Shortcodes {

	/**
	 * @var bool
	 */
	private static $assets_queued = false;

	/**
	 * @var bool
	 */
	private static $times_assets_queued = false;

	/**
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'css_tc_pin_kiosk', array( __CLASS__, 'pin_kiosk' ) );
		add_shortcode( 'css_tc_name_kiosk', array( __CLASS__, 'name_kiosk' ) );
		add_shortcode( 'css_tc_my_times', array( __CLASS__, 'my_times' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * @return array<string,int>
	 */
	public static function create_kiosk_pages() {
		return self::create_public_pages();
	}

	/**
	 * Create (or reuse) public pages that host the kiosk and employee shortcodes.
	 *
	 * @return array{pin_kiosk_page_id:int,name_kiosk_page_id:int,employee_times_page_id:int}
	 */
	public static function create_public_pages() {
		$plugin   = css_tc_addon();
		$settings = $plugin->get_settings();

		$pages = array(
			'pin_kiosk_page_id'      => array(
				'title'   => __( 'PIN Time Clock', 'css-timeclock-addon' ),
				'slug'    => 'pin-time-clock',
				'content' => '[css_tc_pin_kiosk]',
			),
			'name_kiosk_page_id'     => array(
				'title'   => __( 'Name Time Clock', 'css-timeclock-addon' ),
				'slug'    => 'name-time-clock',
				'content' => '[css_tc_name_kiosk]',
			),
			'employee_times_page_id' => array(
				'title'   => __( 'My Time Clock', 'css-timeclock-addon' ),
				'slug'    => 'my-time-clock',
				'content' => '[css_tc_my_times]',
			),
		);

		foreach ( $pages as $option_key => $spec ) {
			$existing_id = isset( $settings[ $option_key ] ) ? (int) $settings[ $option_key ] : 0;
			if ( $existing_id && get_post_status( $existing_id ) ) {
				$status = get_post_status( $existing_id );
				if ( 'trash' !== $status ) {
					continue;
				}
			}

			$found = get_page_by_path( $spec['slug'] );
			if ( $found && 'trash' !== $found->post_status ) {
				$settings[ $option_key ] = (int) $found->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => $spec['title'],
					'post_name'    => $spec['slug'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $spec['content'],
				),
				true
			);

			if ( ! is_wp_error( $page_id ) ) {
				$settings[ $option_key ] = (int) $page_id;
			}
		}

		$plugin->update_settings( $settings );

		return array(
			'pin_kiosk_page_id'      => (int) $settings['pin_kiosk_page_id'],
			'name_kiosk_page_id'     => (int) $settings['name_kiosk_page_id'],
			'employee_times_page_id' => (int) $settings['employee_times_page_id'],
		);
	}

	/**
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public static function body_class( $classes ) {
		if ( ! is_singular() ) {
			return $classes;
		}
		$post = get_post();
		if ( ! $post ) {
			return $classes;
		}
		if ( has_shortcode( $post->post_content, 'css_tc_pin_kiosk' ) || has_shortcode( $post->post_content, 'css_tc_name_kiosk' ) ) {
			$classes[] = 'css-tc-kiosk-page';
		}
		if ( has_shortcode( $post->post_content, 'css_tc_my_times' ) ) {
			$classes[] = 'css-tc-times-page';
		}
		return $classes;
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function pin_kiosk( $atts ) {
		unset( $atts );
		$settings = css_tc_addon()->get_settings();
		self::enqueue_assets();

		ob_start();
		$enabled = ! empty( $settings['pin_kiosk_enabled'] );
		$mode    = 'pin';
		include CSS_TC_ADDON_DIR . 'public/views/pin-kiosk.php';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function name_kiosk( $atts ) {
		unset( $atts );
		$settings = css_tc_addon()->get_settings();
		self::enqueue_assets();

		ob_start();
		$enabled = ! empty( $settings['name_kiosk_enabled'] );
		$mode    = 'name';
		include CSS_TC_ADDON_DIR . 'public/views/name-kiosk.php';
		return (string) ob_get_clean();
	}

	/**
	 * Logged-in employee times + suggest-edit dashboard.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function my_times( $atts ) {
		unset( $atts );
		self::enqueue_times_assets();

		$user_id   = get_current_user_id();
		$logged_in = $user_id > 0;
		$allowed   = $logged_in && css_tc_addon()->employees->can_view_own_times( $user_id );
		$login_url = wp_login_url( get_permalink() ? (string) get_permalink() : home_url( '/my-time-clock/' ) );

		ob_start();
		include CSS_TC_ADDON_DIR . 'public/views/my-times.php';
		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	private static function enqueue_times_assets() {
		if ( self::$times_assets_queued ) {
			return;
		}
		self::$times_assets_queued = true;

		wp_enqueue_style(
			'css-tc-times',
			CSS_TC_ADDON_URL . 'public/css/times.css',
			array(),
			CSS_TC_ADDON_VERSION
		);

		if ( ! is_user_logged_in() || ! css_tc_addon()->employees->can_view_own_times( get_current_user_id() ) ) {
			return;
		}

		wp_enqueue_script(
			'css-tc-times',
			CSS_TC_ADDON_URL . 'public/js/times.js',
			array(),
			CSS_TC_ADDON_VERSION,
			true
		);

		wp_localize_script(
			'css-tc-times',
			'cssTcTimes',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Css_Tc_Corrections::EMPLOYEE_NONCE ),
				'strings' => array(
					'loadError'    => __( 'Could not load your times. Refresh the page.', 'css-timeclock-addon' ),
					'noShifts'     => __( 'No punches this day.', 'css-timeclock-addon' ),
					'openShift'    => __( 'Still clocked in', 'css-timeclock-addon' ),
					'suggest'      => __( 'Suggest edit', 'css-timeclock-addon' ),
					'pending'      => __( 'Pending review', 'css-timeclock-addon' ),
					'approved'     => __( 'Approved', 'css-timeclock-addon' ),
					'rejected'     => __( 'Rejected', 'css-timeclock-addon' ),
					'reasonLabel'  => __( 'Reason', 'css-timeclock-addon' ),
					'clockIn'      => __( 'Clock in', 'css-timeclock-addon' ),
					'clockOut'     => __( 'Clock out', 'css-timeclock-addon' ),
					'nextDay'      => __( 'Clock-out is the next day', 'css-timeclock-addon' ),
					'missing'      => __( 'I missed a punch / this day is incomplete', 'css-timeclock-addon' ),
					'submit'       => __( 'Send suggestion', 'css-timeclock-addon' ),
					'cancel'       => __( 'Cancel', 'css-timeclock-addon' ),
					'sent'         => __( 'Suggestion sent. A supervisor will review it.', 'css-timeclock-addon' ),
					'needReason'   => __( 'Please add a short reason (at least 8 characters).', 'css-timeclock-addon' ),
					'today'        => __( 'Today', 'css-timeclock-addon' ),
					'shiftTotal'   => __( 'Shift time', 'css-timeclock-addon' ),
					'updatePending'=> __( 'Update pending suggestion', 'css-timeclock-addon' ),
					'reviewNote'   => __( 'Supervisor note', 'css-timeclock-addon' ),
					'addMissing'   => __( 'Add a missing shift', 'css-timeclock-addon' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	private static function enqueue_assets() {
		if ( self::$assets_queued ) {
			return;
		}
		self::$assets_queued = true;

		$settings = css_tc_addon()->get_settings();

		wp_enqueue_style(
			'css-tc-kiosk',
			CSS_TC_ADDON_URL . 'public/css/kiosk.css',
			array(),
			CSS_TC_ADDON_VERSION
		);

		wp_enqueue_script(
			'css-tc-kiosk',
			CSS_TC_ADDON_URL . 'public/js/kiosk.js',
			array(),
			CSS_TC_ADDON_VERSION,
			true
		);

		wp_localize_script(
			'css-tc-kiosk',
			'cssTcKiosk',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( Css_Tc_Ajax::PUBLIC_NONCE ),
				'pinMin'         => (int) $settings['pin_min_length'],
				'pinMax'         => (int) $settings['pin_max_length'],
				'idleResetMs'    => (int) $settings['idle_reset_ms'],
				'pinEnabled'     => ! empty( $settings['pin_kiosk_enabled'] ),
				'nameEnabled'    => ! empty( $settings['name_kiosk_enabled'] ),
				'askPlace'       => css_tc_addon()->places->prompt_enabled(),
				'facilities'     => css_tc_addon()->places->facilities(),
				'locations'      => css_tc_addon()->places->locations(),
				'boardRefreshMs' => 20000,
				'strings'        => array(
					'enterPin'       => __( 'Enter your PIN', 'css-timeclock-addon' ),
					'confirmPin'     => __( 'Confirm with your PIN', 'css-timeclock-addon' ),
					'clockIn'        => __( 'Clock in', 'css-timeclock-addon' ),
					'clockOut'       => __( 'Clock out', 'css-timeclock-addon' ),
					'workingSince'   => __( 'Clocked in since', 'css-timeclock-addon' ),
					'hello'          => __( 'Hello', 'css-timeclock-addon' ),
					'successIn'      => __( 'You are clocked in.', 'css-timeclock-addon' ),
					'successOut'     => __( 'You are clocked out.', 'css-timeclock-addon' ),
					'shiftTotal'     => __( 'Shift time', 'css-timeclock-addon' ),
					'badPin'         => __( 'That PIN was not recognized.', 'css-timeclock-addon' ),
					'network'        => __( 'Could not reach the time clock. Try again.', 'css-timeclock-addon' ),
					'disabled'       => __( 'This kiosk is turned off.', 'css-timeclock-addon' ),
					'noEmployees'    => __( 'No employees have a PIN yet. A supervisor can set PINs under Time Clock → Kiosk & PINs.', 'css-timeclock-addon' ),
					'search'         => __( 'Search names', 'css-timeclock-addon' ),
					'cancel'         => __( 'Cancel', 'css-timeclock-addon' ),
					'clear'          => __( 'Clear', 'css-timeclock-addon' ),
					'back'           => __( 'Back', 'css-timeclock-addon' ),
					'workingNow'     => __( 'Working now', 'css-timeclock-addon' ),
					'notClockedIn'   => __( 'Not clocked in', 'css-timeclock-addon' ),
					'nobodyIn'       => __( 'Nobody is clocked in.', 'css-timeclock-addon' ),
					'everyoneIn'     => __( 'Everyone is clocked in.', 'css-timeclock-addon' ),
					'updatedAt'      => __( 'Updated', 'css-timeclock-addon' ),
					'boardError'     => __( 'Could not load who is working.', 'css-timeclock-addon' ),
					'chooseFacility' => __( 'Choose a facility to continue.', 'css-timeclock-addon' ),
					'chooseLocation' => __( 'Choose a location to continue.', 'css-timeclock-addon' ),
				),
			)
		);
	}
}
