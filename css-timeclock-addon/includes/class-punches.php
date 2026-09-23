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
	const ROSTER_CACHE_TTL = 8; // Unused: public_board() skips the transient until a cache is proven necessary.

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
			if ( $this->is_open_shift_meta( $clock_in, $clock_out ) ) {
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
	 * Normalize a kiosk facility/location pair.
	 *
	 * Null means the place prompt is off and the caller should keep the legacy
	 * employee-department write. A WP_Error means the prompt is on and the
	 * submitted pair is missing or not in the allowed lists.
	 *
	 * @param array<string,mixed> $place Raw facility and location.
	 * @return array{facility:string,location:string}|null|WP_Error
	 */
	public function resolve_punch_place( $place ) {
		$places = css_tc_addon()->places;
		if ( ! $places->prompt_enabled() ) {
			return null;
		}

		$facility = $places->canonical_facility( isset( $place['facility'] ) ? $place['facility'] : '' );
		$location = $places->canonical_location( isset( $place['location'] ) ? $place['location'] : '' );
		if ( '' === $facility || '' === $location ) {
			return new WP_Error(
				'css_tc_place',
				__( 'Choose a facility and a location before clocking in or out.', 'css-timeclock-addon' )
			);
		}

		return array(
			'facility' => $facility,
			'location' => $location,
		);
	}

	/**
	 * @param int                               $shift_id Shift post ID.
	 * @param int                               $user_id  Employee user ID.
	 * @param array{facility:string,location:string} $place Canonical pair.
	 * @return void
	 */
	private function apply_punch_place( $shift_id, $user_id, $place ) {
		css_tc_addon()->places->write_shift( $shift_id, $place['facility'], $place['location'] );
		css_tc_addon()->places->remember( $user_id, $place['facility'], $place['location'] );
	}

	/**
	 * Clock the employee in. Fails if they already have an open shift.
	 *
	 * @param int                  $user_id Employee user ID.
	 * @param string               $source  pin_kiosk|name_kiosk.
	 * @param array<string,mixed>  $place   Facility and location when the prompt is on.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clock_in( $user_id, $source = 'pin_kiosk', $place = array() ) {
		$user_id   = (int) $user_id;
		$canonical = $this->resolve_punch_place( $place );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		$open = $this->open_shift_for( $user_id );

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
		// AIO Lite also writes null here. Prefer '' so new rows are a real empty
		// string. Existing SQL NULL rows must still count as open via PHP filter.
		update_post_meta( $shift_id, 'employee_clock_out_time', '' );

		$facility = '';
		$location = '';
		if ( is_array( $canonical ) ) {
			$this->apply_punch_place( $shift_id, $user_id, $canonical );
			$facility = $canonical['facility'];
			$location = $canonical['location'];
		} else {
			$department = css_tc_addon()->employees->department( $user_id );
			if ( '' !== $department ) {
				add_post_meta( $shift_id, 'department', $department, true );
			}
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
			'facility'      => $facility,
			'location'      => $location,
		);
	}

	/**
	 * Clock the employee out of their open shift.
	 *
	 * @param int                 $user_id Employee user ID.
	 * @param string              $source  pin_kiosk|name_kiosk.
	 * @param array<string,mixed> $place   Facility and location when the prompt is on.
	 * @return array<string,mixed>|WP_Error
	 */
	public function clock_out( $user_id, $source = 'pin_kiosk', $place = array() ) {
		$user_id   = (int) $user_id;
		$canonical = $this->resolve_punch_place( $place );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}

		$open = $this->open_shift_for( $user_id );

		if ( ! $open['is_clocked_in'] || $open['open_shift_id'] < 1 ) {
			return new WP_Error( 'css_tc_not_in', __( 'You are not clocked in.', 'css-timeclock-addon' ) );
		}

		$shift_id = (int) $open['open_shift_id'];
		$now      = $this->current_mysql_time();
		$clock_in = get_post_meta( $shift_id, 'employee_clock_in_time', true );

		update_post_meta( $shift_id, 'employee_clock_out_time', $now );
		add_post_meta( $shift_id, 'ip_address_out', $this->client_ip(), true );
		add_post_meta( $shift_id, 'css_tc_kiosk_source_out', sanitize_key( $source ), true );

		$facility = '';
		$location = '';
		if ( is_array( $canonical ) ) {
			$this->apply_punch_place( $shift_id, $user_id, $canonical );
			$facility = $canonical['facility'];
			$location = $canonical['location'];
		}

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
			'facility'       => $facility,
			'location'       => $location,
		);
	}

	/**
	 * Whether clock-in / clock-out meta describes an open shift.
	 *
	 * Same rule as AIO Real Time Monitoring (aio-monitoring.php) and
	 * open_shift_for(): clock-in is set and clock-out is null or ''. PHP
	 * empty() treats '', null, and missing get_post_meta values as empty.
	 * Do not replace this with a WP_Query empty-string meta_query — a row
	 * with SQL NULL exists, so NOT EXISTS fails and meta_value = '' fails.
	 *
	 * @param mixed $clock_in  employee_clock_in_time meta.
	 * @param mixed $clock_out employee_clock_out_time meta.
	 * @return bool
	 */
	public function is_open_shift_meta( $clock_in, $clock_out ) {
		return ( ! empty( $clock_in ) && ( empty( $clock_out ) || '' === $clock_out ) );
	}

	/**
	 * Open shifts keyed by employee user ID (AIO: clock-in set, clock-out empty).
	 *
	 * Loads recent shift posts (no clock-out meta_query), then filters in PHP
	 * with is_open_shift_meta() — same approach as open_shift_for() and AIO.
	 *
	 * @return array<int,array{clock_in_time:string,facility:string,location:string}>
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
				if ( ! $this->is_open_shift_meta( $clock_in, $clock_out ) ) {
					continue;
				}

				$place          = css_tc_addon()->places->read_shift( (int) $post->ID );
				$map[ $author ] = array(
					'clock_in_time' => $this->format_board_time( (string) $clock_in ),
					'facility'      => $place['facility'],
					'location'      => $place['location'],
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
		// No get_transient / set_transient until a cache is proven necessary.
		// WP Engine object cache can keep a stale empty css_tc_roster_public
		// after delete_transient(), which made punch + roster disagree.

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
				if ( '' !== $open[ $id ]['facility'] ) {
					$row['facility'] = $open[ $id ]['facility'];
				}
				if ( '' !== $open[ $id ]['location'] ) {
					$row['location'] = $open[ $id ]['location'];
				}
				$working[] = $row;
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

		return $payload;
	}

	/**
	 * Drop any leftover css_tc_roster_public transient from older versions.
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
	 * Recent calendar days for the employee dashboard, newest first.
	 *
	 * @param int $user_id Employee user ID.
	 * @param int $days    Inclusive lookback (today counts as day 1).
	 * @return array<int,array<string,mixed>>
	 */
	public function calendar_days_for_user( $user_id, $days = 21 ) {
		$user_id = (int) $user_id;
		$days    = min( 60, max( 7, (int) $days ) );
		$today   = $this->site_date();
		$start   = $this->shift_date( $today, 1 - $days );

		$shifts  = $this->shifts_since( $user_id, $start );
		$by_date = array();

		for ( $i = 0; $i < $days; $i++ ) {
			$date             = $this->shift_date( $today, -$i );
			$by_date[ $date ] = array();
		}

		foreach ( $shifts as $shift ) {
			$date = substr( (string) $shift['clock_in_raw'], 0, 10 );
			if ( ! isset( $by_date[ $date ] ) ) {
				continue;
			}
			$by_date[ $date ][] = $shift;
		}

		$result = array();
		foreach ( $by_date as $date => $list ) {
			$result[] = array(
				'date'       => $date,
				'date_label' => $this->format_day_label( $date ),
				'weekday'    => $this->format_weekday( $date ),
				'is_today'   => ( $date === $today ),
				'shifts'     => $list,
			);
		}

		return $result;
	}

	/**
	 * Shifts for one employee whose clock-in is on or after $start_date.
	 *
	 * @param int    $user_id    Employee user ID.
	 * @param string $start_date Y-m-d.
	 * @return array<int,array<string,mixed>>
	 */
	public function shifts_since( $user_id, $start_date ) {
		$user_id    = (int) $user_id;
		$start_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ? $start_date : $this->site_date();

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'author'         => $user_id,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => 'employee_clock_in_time',
						'value'   => $start_date . ' 00:00:00',
						'compare' => '>=',
					),
				),
			)
		);

		$shifts = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$row = $this->shift_row( $post );
				if ( $row ) {
					$shifts[] = $row;
				}
			}
		}

		wp_reset_postdata();

		return $shifts;
	}

	/**
	 * @param WP_Post $post Shift post.
	 * @return array<string,mixed>|null
	 */
	public function shift_row( $post ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$clock_in  = (string) get_post_meta( $post->ID, 'employee_clock_in_time', true );
		$clock_out = (string) get_post_meta( $post->ID, 'employee_clock_out_time', true );
		if ( '' === $clock_in ) {
			return null;
		}

		$is_open = ( '' === $clock_out );
		return array(
			'id'              => (int) $post->ID,
			'clock_in_raw'    => $clock_in,
			'clock_out_raw'   => $is_open ? '' : $clock_out,
			'clock_in'        => $this->format_time( $clock_in ),
			'clock_out'       => $is_open ? '' : $this->format_time( $clock_out ),
			'clock_in_hm'     => $this->format_hour_minute( $clock_in ),
			'clock_out_hm'    => $is_open ? '' : $this->format_hour_minute( $clock_out ),
			'out_next_day'    => ( ! $is_open && substr( $clock_out, 0, 10 ) !== substr( $clock_in, 0, 10 ) ),
			'time_total'      => $is_open ? '' : $this->elapsed_label( $clock_in, $clock_out ),
			'is_open'         => $is_open,
			'facility'        => sanitize_text_field( (string) get_post_meta( $post->ID, Css_Tc_Places::META_FACILITY, true ) ),
			'location'        => sanitize_text_field( (string) get_post_meta( $post->ID, Css_Tc_Places::META_LOCATION, true ) ),
		);
	}

	/**
	 * Apply approved times to an existing AIO-compatible shift. Stores first-original audit.
	 *
	 * @param int    $shift_id  Shift post ID.
	 * @param int    $user_id   Expected author.
	 * @param string $clock_in  Y-m-d H:i:s or empty to keep.
	 * @param string $clock_out Y-m-d H:i:s or empty to keep (or clear if $clear_out).
	 * @param bool   $clear_out Whether to empty clock-out.
	 * @param array<string,mixed> $audit Audit fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public function apply_times( $shift_id, $user_id, $clock_in, $clock_out, $clear_out = false, $audit = array() ) {
		$shift_id = (int) $shift_id;
		$user_id  = (int) $user_id;
		$post     = get_post( $shift_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type || (int) $post->post_author !== $user_id ) {
			return new WP_Error( 'css_tc_bad_shift', __( 'That shift could not be updated.', 'css-timeclock-addon' ) );
		}

		$old_in  = (string) get_post_meta( $shift_id, 'employee_clock_in_time', true );
		$old_out = (string) get_post_meta( $shift_id, 'employee_clock_out_time', true );

		if ( ! get_post_meta( $shift_id, 'css_tc_original_clock_in', true ) ) {
			update_post_meta( $shift_id, 'css_tc_original_clock_in', $old_in );
			update_post_meta( $shift_id, 'css_tc_original_clock_out', $old_out );
		}

		if ( '' !== $clock_in ) {
			update_post_meta( $shift_id, 'employee_clock_in_time', $clock_in );
		}
		if ( $clear_out ) {
			update_post_meta( $shift_id, 'employee_clock_out_time', '' );
		} elseif ( '' !== $clock_out ) {
			update_post_meta( $shift_id, 'employee_clock_out_time', $clock_out );
		}

		if ( ! empty( $audit['correction_id'] ) ) {
			update_post_meta( $shift_id, 'css_tc_last_correction_id', (int) $audit['correction_id'] );
		}
		if ( ! empty( $audit['suggested_by'] ) ) {
			update_post_meta( $shift_id, 'css_tc_corrected_by', (int) $audit['suggested_by'] );
		}
		if ( ! empty( $audit['approved_by'] ) ) {
			update_post_meta( $shift_id, 'css_tc_approved_by', (int) $audit['approved_by'] );
		}
		update_post_meta( $shift_id, 'css_tc_approved_at', $this->current_mysql_time() );

		$this->bust_roster_cache();

		$fresh = get_post( $shift_id );
		$row   = $this->shift_row( $fresh );
		return $row ? $row : new WP_Error( 'css_tc_bad_shift', __( 'That shift could not be updated.', 'css-timeclock-addon' ) );
	}

	/**
	 * Create a shift from an approved missing-punch suggestion.
	 *
	 * @param int    $user_id   Employee user ID.
	 * @param string $clock_in  Y-m-d H:i:s.
	 * @param string $clock_out Y-m-d H:i:s or empty.
	 * @param array<string,mixed> $audit Audit fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_corrected_shift( $user_id, $clock_in, $clock_out = '', $audit = array() ) {
		$user_id = (int) $user_id;
		if ( $user_id < 1 || '' === $clock_in ) {
			return new WP_Error( 'css_tc_bad_shift', __( 'A clock-in time is required to add a shift.', 'css-timeclock-addon' ) );
		}

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

		update_post_meta( $shift_id, 'employee_clock_in_time', $clock_in );
		update_post_meta( $shift_id, 'employee_clock_out_time', '' === $clock_out ? '' : $clock_out );
		update_post_meta( $shift_id, 'css_tc_original_clock_in', '' );
		update_post_meta( $shift_id, 'css_tc_original_clock_out', '' );
		add_post_meta( $shift_id, 'css_tc_kiosk_source', 'correction', true );
		add_post_meta( $shift_id, 'css_tc_created_from_correction', '1', true );

		$department = css_tc_addon()->employees->department( $user_id );
		if ( '' !== $department ) {
			add_post_meta( $shift_id, 'department', $department, true );
		}

		if ( ! empty( $audit['correction_id'] ) ) {
			update_post_meta( $shift_id, 'css_tc_last_correction_id', (int) $audit['correction_id'] );
		}
		if ( ! empty( $audit['suggested_by'] ) ) {
			update_post_meta( $shift_id, 'css_tc_corrected_by', (int) $audit['suggested_by'] );
		}
		if ( ! empty( $audit['approved_by'] ) ) {
			update_post_meta( $shift_id, 'css_tc_approved_by', (int) $audit['approved_by'] );
		}
		update_post_meta( $shift_id, 'css_tc_approved_at', $this->current_mysql_time() );

		$this->bust_roster_cache();

		$row = $this->shift_row( get_post( $shift_id ) );
		return $row ? $row : new WP_Error( 'css_tc_bad_shift', __( 'That shift could not be created.', 'css-timeclock-addon' ) );
	}

	/**
	 * @param string $mysql_datetime Datetime string.
	 * @return string
	 */
	public function format_hour_minute( $mysql_datetime ) {
		$ts = strtotime( $mysql_datetime );
		if ( ! $ts ) {
			return '';
		}
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'H:i', $ts );
		}
		return date_i18n( 'H:i', $ts );
	}

	/**
	 * @return string
	 */
	public function site_date() {
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'Y-m-d' );
		}
		return date_i18n( 'Y-m-d' );
	}

	/**
	 * @param string $date Y-m-d.
	 * @param int    $offset_days Days to add (negative to subtract).
	 * @return string
	 */
	public function shift_date( $date, $offset_days ) {
		$ts = strtotime( $date . ' 12:00:00' );
		if ( ! $ts ) {
			$ts = time();
		}
		$ts += ( (int) $offset_days ) * DAY_IN_SECONDS;
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'Y-m-d', $ts );
		}
		return date_i18n( 'Y-m-d', $ts );
	}

	/**
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public function format_day_label( $date ) {
		$ts = strtotime( $date . ' 12:00:00' );
		$format = get_option( 'date_format', 'Y-m-d' );
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $ts );
		}
		return date_i18n( $format, $ts );
	}

	/**
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public function format_weekday( $date ) {
		$ts = strtotime( $date . ' 12:00:00' );
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'l', $ts );
		}
		return date_i18n( 'l', $ts );
	}

	/**
	 * Combine a work date + HH:MM into AIO's site-timezone mysql datetime.
	 *
	 * @param string $date     Y-m-d.
	 * @param string $hm       H:i.
	 * @param bool   $next_day Whether the time is the following calendar day.
	 * @return string
	 */
	public function combine_day_time( $date, $hm, $next_day = false ) {
		$hm = $this->normalize_hour_minute( $hm );
		if ( '' === $hm || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		if ( $next_day ) {
			$date = $this->shift_date( $date, 1 );
		}
		return $date . ' ' . $hm . ':00';
	}

	/**
	 * @param string $hm Raw hour:minute.
	 * @return string
	 */
	public function normalize_hour_minute( $hm ) {
		$hm = trim( (string) $hm );
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $hm, $m ) ) {
			$hour = (int) $m[1];
			$min  = (int) $m[2];
			if ( $hour >= 0 && $hour <= 23 && $min >= 0 && $min <= 59 ) {
				return sprintf( '%02d:%02d', $hour, $min );
			}
		}
		return '';
	}

	/**
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return string
	 */
	public function elapsed_label( $start, $end ) {
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
