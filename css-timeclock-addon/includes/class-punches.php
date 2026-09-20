<?php
/**
 * AIO Time Clock Lite–compatible punch writes.
 *
 * AIO Lite's AJAX action `aio_time_clock_lite_js` requires a logged-in WP user
 * (`wp_ajax_` only, plus get_current_user_id()). Shared kiosks cannot call it.
 * This class writes the same `shift` posts and meta AIO's Real Time Monitoring
 * already queries: open shifts are those with employee_clock_in_time set and
 * employee_clock_out_time empty.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clock in / clock out against AIO's shift data model.
 */
class Css_Tc_Punches {

	const POST_TYPE        = 'shift';
	const ROSTER_CACHE_KEY = 'css_tc_roster_public';
	const ROSTER_CACHE_TTL = 8;

	/**
	 * @param int $user_id Employee user ID.
	 * @return array{open_shift_id:int,is_clocked_in:bool,clock_in_time:?string}
	 */
	public function open_shift_for( $user_id ) {
		$user_id = (int) $user_id;
		$query   = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 25,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$result = array(
			'open_shift_id' => 0,
			'is_clocked_in' => false,
			'clock_in_time' => null,
		);

		if ( ! $query->have_posts() ) {
			return $result;
		}

		foreach ( $query->posts as $post ) {
			$clock_in  = get_post_meta( $post->ID, 'employee_clock_in_time', true );
			$clock_out = get_post_meta( $post->ID, 'employee_clock_out_time', true );
			if ( ! empty( $clock_in ) && ( empty( $clock_out ) || '' === $clock_out ) ) {
				$result['open_shift_id'] = (int) $post->ID;
				$result['is_clocked_in'] = true;
				$result['clock_in_time'] = $this->format_time( (string) $clock_in );
				break;
			}
		}

		wp_reset_postdata();

		return $result;
	}

	/**
	 * Clock the employee in. Fails if they already have an open shift.
	 *
	 * @param int    $user_id Employee user ID.
	 * @param string $source  pin_kiosk|name_kiosk.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clock_in( $user_id, $source = 'pin_kiosk' ) {
		$user_id = (int) $user_id;
		$open    = $this->open_shift_for( $user_id );

		if ( $open['is_clocked_in'] ) {
			return new WP_Error( 'css_tc_already_in', __( 'You are already clocked in.', 'css-timeclock-addon' ) );
		}

		$now = $this->current_mysql_time();

		$shift_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => 'Employee Shift',
				'post_status' => 'publish',
				'post_author' => $user_id,
			),
			true
		);

		if ( is_wp_error( $shift_id ) ) {
			return $shift_id;
		}

		update_post_meta( $shift_id, 'employee_clock_in_time', $now );
		update_post_meta( $shift_id, 'employee_clock_out_time', null );

		$department = css_tc_addon()->employees->department( $user_id );
		if ( '' !== $department ) {
			add_post_meta( $shift_id, 'department', $department, true );
		}

		add_post_meta( $shift_id, 'ip_address_in', $this->client_ip(), true );
		add_post_meta( $shift_id, 'css_tc_kiosk_source', sanitize_key( $source ), true );

		/**
		 * Fires after a kiosk clock-in writes an AIO-compatible shift.
		 *
		 * @param int    $shift_id Shift post ID.
		 * @param int    $user_id  Employee user ID.
		 * @param string $source   Kiosk source.
		 */
		do_action( 'css_tc_after_clock_in', $shift_id, $user_id, $source );

		$this->bust_roster_cache();

		return array(
			'action'        => 'clock_in',
			'shift_id'      => (int) $shift_id,
			'is_clocked_in' => true,
			'clock_in_time' => $this->format_time( $now ),
			'time_total'    => '',
		);
	}

	/**
	 * Clock the employee out of their open shift.
	 *
	 * @param int    $user_id Employee user ID.
	 * @param string $source  pin_kiosk|name_kiosk.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clock_out( $user_id, $source = 'pin_kiosk' ) {
		$user_id = (int) $user_id;
		$open    = $this->open_shift_for( $user_id );

		if ( ! $open['is_clocked_in'] || $open['open_shift_id'] < 1 ) {
			return new WP_Error( 'css_tc_not_in', __( 'You are not clocked in.', 'css-timeclock-addon' ) );
		}

		$shift_id = (int) $open['open_shift_id'];
		$now      = $this->current_mysql_time();
		$clock_in = get_post_meta( $shift_id, 'employee_clock_in_time', true );

		update_post_meta( $shift_id, 'employee_clock_out_time', $now );
		add_post_meta( $shift_id, 'ip_address_out', $this->client_ip(), true );
		add_post_meta( $shift_id, 'css_tc_kiosk_source_out', sanitize_key( $source ), true );

		/**
		 * Fires after a kiosk clock-out closes an AIO-compatible shift.
		 *
		 * @param int    $shift_id Shift post ID.
		 * @param int    $user_id  Employee user ID.
		 * @param string $source   Kiosk source.
		 */
		do_action( 'css_tc_after_clock_out', $shift_id, $user_id, $source );

		$this->bust_roster_cache();

		return array(
			'action'         => 'clock_out',
			'shift_id'       => $shift_id,
			'is_clocked_in'  => false,
			'clock_in_time'  => $this->format_time( (string) $clock_in ),
			'clock_out_time' => $this->format_time( $now ),
			'time_total'     => $this->elapsed_label( (string) $clock_in, $now ),
		);
	}

	/**
	 * Open shifts keyed by employee user ID (AIO: clock-in set, clock-out empty).
	 *
	 * @return array<int,array{clock_in_time:string}>
	 */
	public function open_shifts_by_author() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'employee_clock_in_time',
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'employee_clock_out_time',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'employee_clock_out_time',
							'value'   => '',
							'compare' => '=',
						),
					),
				),
			)
		);

		$map = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$author = (int) $post->post_author;
				if ( $author < 1 || isset( $map[ $author ] ) ) {
					continue;
				}

				$clock_in  = get_post_meta( $post->ID, 'employee_clock_in_time', true );
				$clock_out = get_post_meta( $post->ID, 'employee_clock_out_time', true );
				if ( empty( $clock_in ) || ( ! empty( $clock_out ) && '' !== $clock_out ) ) {
					continue;
				}

				$map[ $author ] = array(
					'clock_in_time' => $this->format_board_time( (string) $clock_in ),
				);
			}
		}

		wp_reset_postdata();

		return $map;
	}

	/**
	 * Public kiosk board: display names + in/out (+ clock-in time). No IDs, emails, or PINs.
	 *
	 * @return array{working:array<int,array<string,string>>,out:array<int,array<string,string>>,working_count:int,out_count:int,generated_at:string}
	 */
	public function public_board() {
		$cached = get_transient( self::ROSTER_CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['working'], $cached['out'] ) ) {
			return $cached;
		}

		$employees = css_tc_addon()->employees->list_for_board();
		$open      = $this->open_shifts_by_author();
		$seen      = array();

		foreach ( $employees as $emp ) {
			$seen[ (int) $emp['id'] ] = true;
		}

		foreach ( $open as $user_id => $_shift ) {
			if ( isset( $seen[ $user_id ] ) ) {
				continue;
			}
			if ( ! css_tc_addon()->employees->is_employee( $user_id ) ) {
				continue;
			}
			$employees[]        = array(
				'id'   => (int) $user_id,
				'name' => css_tc_addon()->employees->display_name( (int) $user_id ),
			);
			$seen[ $user_id ] = true;
		}

		usort(
			$employees,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		$working = array();
		$out     = array();

		foreach ( $employees as $emp ) {
			$id  = (int) $emp['id'];
			$row = array(
				'name' => (string) $emp['name'],
			);
			if ( isset( $open[ $id ] ) ) {
				$row['clock_in_time'] = $open[ $id ]['clock_in_time'];
				$working[]            = $row;
			} else {
				$out[] = $row;
			}
		}

		$payload = array(
			'working'       => $working,
			'out'           => $out,
			'working_count' => count( $working ),
			'out_count'     => count( $out ),
			'generated_at'  => $this->format_board_now(),
		);

		/**
		 * Filter the public kiosk status board payload (names and in/out only).
		 *
		 * @param array<string,mixed> $payload Board payload.
		 */
		$payload = apply_filters( 'css_tc_public_board', $payload );

		set_transient( self::ROSTER_CACHE_KEY, $payload, self::ROSTER_CACHE_TTL );

		return $payload;
	}

	/**
	 * Drop the short-lived public board cache after a punch.
	 *
	 * @return void
	 */
	public function bust_roster_cache() {
		delete_transient( self::ROSTER_CACHE_KEY );
	}

	/**
	 * Clock-in time for the kiosk board: time only when it is today.
	 *
	 * @param string $mysql_datetime Datetime string.
	 * @return string
	 */
	public function format_board_time( $mysql_datetime ) {
		$ts = strtotime( $mysql_datetime );
		if ( ! $ts ) {
			return $mysql_datetime;
		}

		$time_format = get_option( 'time_format', 'g:i a' );
		$date_format = get_option( 'date_format', 'Y-m-d' );
		if ( function_exists( 'wp_date' ) ) {
			$same_day = ( wp_date( 'Y-m-d', $ts ) === wp_date( 'Y-m-d' ) );
			$format   = $same_day ? $time_format : ( $date_format . ' ' . $time_format );
			return wp_date( $format, $ts );
		}

		$same_day = ( date_i18n( 'Y-m-d', $ts ) === date_i18n( 'Y-m-d' ) );
		$format   = $same_day ? $time_format : ( $date_format . ' ' . $time_format );
		return date_i18n( $format, $ts );
	}

	/**
	 * @return string
	 */
	private function format_board_now() {
		$format = get_option( 'time_format', 'g:i a' );
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format );
		}
		return date_i18n( $format );
	}

	/**
	 * WordPress-timezone now, same format AIO Lite 2.1 uses (wp_date Y-m-d H:i:s).
	 *
	 * @return string
	 */
	public function current_mysql_time() {
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'Y-m-d H:i:s' );
		}
		return current_time( 'mysql' );
	}

	/**
	 * @param string $mysql_datetime Datetime string.
	 * @return string
	 */
	public function format_time( $mysql_datetime ) {
		$ts = strtotime( $mysql_datetime );
		if ( ! $ts ) {
			return $mysql_datetime;
		}

		$format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'g:i a' );
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $ts );
		}
		return date_i18n( $format, $ts );
	}

	/**
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return string
	 */
	private function elapsed_label( $start, $end ) {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		if ( ! $start_ts || ! $end_ts || $end_ts < $start_ts ) {
			return '00:00';
		}
		$seconds = $end_ts - $start_ts;
		$hours   = (int) floor( $seconds / 3600 );
		$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
		return sprintf( '%02d:%02d', $hours, $minutes );
	}

	/**
	 * @return string
	 */
	private function client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
}
