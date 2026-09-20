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
 * [css_tc_pin_kiosk] and [css_tc_name_kiosk].
 */
class Css_Tc_Shortcodes {

	/**
	 * @var bool
	 */
	private static $assets_queued = false;

	/**
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'css_tc_pin_kiosk', array( __CLASS__, 'pin_kiosk' ) );
		add_shortcode( 'css_tc_name_kiosk', array( __CLASS__, 'name_kiosk' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Create (or reuse) public pages that host the kiosk shortcodes.
	 *
	 * @return array{pin_kiosk_page_id:int,name_kiosk_page_id:int}
	 */
	public static function create_kiosk_pages() {
		$plugin   = css_tc_addon();
		$settings = $plugin->get_settings();

		$pages = array(
			'pin_kiosk_page_id'  => array(
				'title'   => __( 'PIN Time Clock', 'css-timeclock-addon' ),
				'slug'    => 'pin-time-clock',
				'content' => '[css_tc_pin_kiosk]',
			),
			'name_kiosk_page_id' => array(
				'title'   => __( 'Name Time Clock', 'css-timeclock-addon' ),
				'slug'    => 'name-time-clock',
				'content' => '[css_tc_name_kiosk]',
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
			'pin_kiosk_page_id'  => (int) $settings['pin_kiosk_page_id'],
			'name_kiosk_page_id' => (int) $settings['name_kiosk_page_id'],
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
				),
			)
		);
	}
}
