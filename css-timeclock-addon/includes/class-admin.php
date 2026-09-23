<?php
/**
 * Admin settings and per-employee PIN management.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Clock submenu when AIO is present; otherwise Settings.
 */
class Css_Tc_Admin {

	/**
	 * @return void
	 */
	public static function register() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'add_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue' ) );
	}

	/**
	 * @return void
	 */
	public function add_menu() {
		$page = 'css-tc-addon';

		add_options_page(
			__( 'Time Clock Kiosk', 'css-timeclock-addon' ),
			__( 'Time Clock Kiosk', 'css-timeclock-addon' ),
			'manage_options',
			$page,
			array( $this, 'render_page' )
		);

		if ( Css_Tc_Plugin::aio_is_active() ) {
			add_submenu_page(
				'aio-tc-lite',
				__( 'Kiosk & PINs', 'css-timeclock-addon' ),
				__( 'Kiosk & PINs', 'css-timeclock-addon' ),
				'edit_posts',
				$page,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$is_ours = ( false !== strpos( $hook, 'css-tc-addon' ) );
		if ( ! $is_ours ) {
			return;
		}

		wp_enqueue_style(
			'css-tc-admin',
			CSS_TC_ADDON_URL . 'admin/css/admin.css',
			array(),
			CSS_TC_ADDON_VERSION
		);

		wp_enqueue_script(
			'css-tc-admin',
			CSS_TC_ADDON_URL . 'admin/js/admin.js',
			array(),
			CSS_TC_ADDON_VERSION,
			true
		);

		wp_localize_script(
			'css-tc-admin',
			'cssTcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Css_Tc_Ajax::ADMIN_NONCE ),
				'strings' => array(
					'confirmClear' => __( 'Remove this employee PIN? They will not be able to use the kiosk until a new PIN is set.', 'css-timeclock-addon' ),
					'saving'       => __( 'Saving…', 'css-timeclock-addon' ),
					'saved'        => __( 'Saved.', 'css-timeclock-addon' ),
					'error'        => __( 'Something went wrong. Try again.', 'css-timeclock-addon' ),
					'notSet'       => __( 'Not set', 'css-timeclock-addon' ),
					'set'          => __( 'Set', 'css-timeclock-addon' ),
					'showPin'      => __( 'Show PIN', 'css-timeclock-addon' ),
					'hidePin'      => __( 'Hide PIN', 'css-timeclock-addon' ),
					'confirmReject'=> __( 'Reject this suggestion? Punches will stay unchanged.', 'css-timeclock-addon' ),
					'approved'     => __( 'Approved', 'css-timeclock-addon' ),
					'rejected'     => __( 'Rejected', 'css-timeclock-addon' ),
					'pending'      => __( 'Pending review', 'css-timeclock-addon' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_page() {
		if ( ! Css_Tc_Plugin::user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage the time clock kiosk.', 'css-timeclock-addon' ) );
		}

		$settings  = css_tc_addon()->get_settings();
		$employees = css_tc_addon()->employees->list_for_admin();
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'settings', 'pins', 'corrections' ), true ) ) {
			$tab = 'settings';
		}

		$pin_page   = ! empty( $settings['pin_kiosk_page_id'] ) ? get_permalink( (int) $settings['pin_kiosk_page_id'] ) : '';
		$name_page  = ! empty( $settings['name_kiosk_page_id'] ) ? get_permalink( (int) $settings['name_kiosk_page_id'] ) : '';
		$times_page = ! empty( $settings['employee_times_page_id'] ) ? get_permalink( (int) $settings['employee_times_page_id'] ) : '';
		$queue      = css_tc_addon()->corrections->admin_queue();
		$base_url = current_user_can( 'manage_options' )
			? admin_url( 'options-general.php?page=css-tc-addon' )
			: admin_url( 'admin.php?page=css-tc-addon' );

		include CSS_TC_ADDON_DIR . 'admin/views/settings-page.php';
	}
}
