<?php
/**
 * Employee punch-correction suggestions and supervisor review.
 *
 * Suggestions are stored as a private CPT so AIO Lite shift files stay
 * untouched. Approving writes AIO-compatible shift meta and keeps an audit.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correction requests: pending until a time-clock admin reviews them.
 */
class Css_Tc_Corrections {

	const POST_TYPE     = 'css_tc_correction';
	const EMPLOYEE_NONCE = 'css_tc_employee';
	const STATUS_PENDING  = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';

	/**
	 * @return void
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Time corrections', 'css-timeclock-addon' ),
					'singular_name' => __( 'Time correction', 'css-timeclock-addon' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
			)
		);
	}

	/**
	 * Dashboard payload: recent days + this employee's suggestions only.
	 *
	 * @param int $user_id Employee user ID.
	 * @return array<string,mixed>
	 */
	public function dashboard_for_user( $user_id ) {
		$user_id  = (int) $user_id;
		$settings = css_tc_addon()->get_settings();
		$lookback = isset( $settings['times_lookback_days'] ) ? (int) $settings['times_lookback_days'] : 21;
		$days     = css_tc_addon()->punches->calendar_days_for_user( $user_id, $lookback );
		$by_date  = $this->suggestions_by_date( $user_id );

		foreach ( $days as &$day ) {
			$day['suggestion'] = isset( $by_date[ $day['date'] ] ) ? $by_date[ $day['date'] ] : null;
		}
		unset( $day );

		return array(
			'days'     => $days,
			'lookback' => $lookback,
			'name'     => css_tc_addon()->employees->greeting_name( $user_id ),
		);
	}

	/**
	 * @param int $user_id Employee user ID.
	 * @return array<string,array<string,mixed>>
	 */
	public function suggestions_by_date( $user_id ) {
		$items = $this->query_posts(
			array(
				'author'         => (int) $user_id,
				'posts_per_page' => 80,
				'post_status'    => array( 'pending', 'private', 'draft' ),
			)
		);

		$map = array();
		foreach ( $items as $post ) {
			$row = $this->to_public_row( $post, false );
			if ( ! $row ) {
				continue;
			}
			$date = $row['work_date'];
			if ( ! isset( $map[ $date ] ) || $this->is_newer_status( $row, $map[ $date ] ) ) {
				$map[ $date ] = $row;
			}
		}

		return $map;
	}

	/**
	 * Prefer a pending suggestion, else the most recently reviewed.
	 *
	 * @param array<string,mixed> $candidate New row.
	 * @param array<string,mixed> $current   Stored row.
	 * @return bool
	 */
	private function is_newer_status( $candidate, $current ) {
		if ( self::STATUS_PENDING === $candidate['status'] && self::STATUS_PENDING !== $current['status'] ) {
			return true;
		}
		if ( self::STATUS_PENDING === $current['status'] && self::STATUS_PENDING !== $candidate['status'] ) {
			return false;
		}
		return (int) $candidate['id'] > (int) $current['id'];
	}

	/**
	 * Create or replace a pending suggestion for one work day.
	 *
	 * @param int                  $user_id Employee user ID.
	 * @param array<string,mixed>  $input   Sanitized form fields.
	 * @return array<string,mixed>|WP_Error
	 */
	public function submit( $user_id, $input ) {
		$user_id = (int) $user_id;
		$parsed  = $this->parse_submission( $user_id, $input );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$limited = $this->assert_not_rate_limited( $user_id );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$existing = $this->pending_for_day( $user_id, $parsed['work_date'] );
		$title    = sprintf(
			/* translators: 1: employee name, 2: work date */
			__( 'Correction: %1$s — %2$s', 'css-timeclock-addon' ),
			css_tc_addon()->employees->display_name( $user_id ),
			$parsed['work_date']
		);

		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'pending',
			'post_author' => $user_id,
		);

		if ( $existing ) {
			$postarr['ID'] = (int) $existing->ID;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		foreach ( $parsed['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		delete_post_meta( $post_id, 'css_tc_reviewer_id' );
		delete_post_meta( $post_id, 'css_tc_reviewed_at' );
		delete_post_meta( $post_id, 'css_tc_review_note' );
		delete_post_meta( $post_id, 'css_tc_applied_shift_id' );

		$post = get_post( $post_id );
		return $this->to_public_row( $post, false );
	}

	/**
	 * @param int    $user_id Employee user ID.
	 * @param string $date    Y-m-d.
	 * @return WP_Post|null
	 */
	public function pending_for_day( $user_id, $date ) {
		$items = $this->query_posts(
			array(
				'author'         => (int) $user_id,
				'posts_per_page' => 5,
				'post_status'    => 'pending',
				'meta_key'       => 'css_tc_work_date',
				'meta_value'     => $date,
			)
		);

		return ! empty( $items ) ? $items[0] : null;
	}

	/**
	 * Admin queue: pending first, then recently reviewed.
	 *
	 * @return array{pending:array<int,array<string,mixed>>,recent:array<int,array<string,mixed>>,pending_count:int}
	 */
	public function admin_queue() {
		$pending_posts = $this->query_posts(
			array(
				'posts_per_page' => 50,
				'post_status'    => 'pending',
			)
		);
		$recent_posts  = $this->query_posts(
			array(
				'posts_per_page' => 20,
				'post_status'    => array( 'private', 'draft' ),
			)
		);

		$pending = array();
		foreach ( $pending_posts as $post ) {
			$row = $this->to_public_row( $post, true );
			if ( $row ) {
				$pending[] = $row;
			}
		}

		$recent = array();
		foreach ( $recent_posts as $post ) {
			$row = $this->to_public_row( $post, true );
			if ( $row ) {
				$recent[] = $row;
			}
		}

		return array(
			'pending'       => $pending,
			'recent'        => $recent,
			'pending_count' => count( $pending ),
		);
	}

	/**
	 * @return int
	 */
	public function pending_count() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$count = (int) $query->found_posts;
		wp_reset_postdata();
		return $count;
	}

	/**
	 * @param int    $correction_id Correction post ID.
	 * @param int    $reviewer_id   Admin user ID.
	 * @param string $note          Optional review note.
	 * @return array<string,mixed>|WP_Error
	 */
	public function approve( $correction_id, $reviewer_id, $note = '' ) {
		$post = $this->require_pending( $correction_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$row     = $this->to_public_row( $post, true );
		$user_id = (int) $post->post_author;
		$punches = css_tc_addon()->punches;
		$audit   = array(
			'correction_id' => (int) $post->ID,
			'suggested_by'  => $user_id,
			'approved_by'   => (int) $reviewer_id,
		);

		$proposed_in  = (string) get_post_meta( $post->ID, 'css_tc_proposed_in', true );
		$proposed_out = (string) get_post_meta( $post->ID, 'css_tc_proposed_out', true );
		$shift_id     = (int) get_post_meta( $post->ID, 'css_tc_shift_id', true );
		$clear_out    = ( '' === $proposed_out && '1' === (string) get_post_meta( $post->ID, 'css_tc_clear_out', true ) );

		if ( $shift_id > 0 ) {
			$applied = $punches->apply_times( $shift_id, $user_id, $proposed_in, $proposed_out, $clear_out, $audit );
		} else {
			if ( '' === $proposed_in ) {
				return new WP_Error( 'css_tc_need_in', __( 'This suggestion has no clock-in time to apply.', 'css-timeclock-addon' ) );
			}
			$applied = $punches->create_corrected_shift( $user_id, $proposed_in, $proposed_out, $audit );
		}

		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		wp_update_post(
			array(
				'ID'          => (int) $post->ID,
				'post_status' => 'private',
			)
		);

		update_post_meta( $post->ID, 'css_tc_reviewer_id', (int) $reviewer_id );
		update_post_meta( $post->ID, 'css_tc_reviewed_at', $punches->current_mysql_time() );
		update_post_meta( $post->ID, 'css_tc_review_note', $this->sanitize_note( $note ) );
		update_post_meta( $post->ID, 'css_tc_applied_shift_id', (int) $applied['id'] );

		$fresh = get_post( $post->ID );
		return $this->to_public_row( $fresh, true );
	}

	/**
	 * @param int    $correction_id Correction post ID.
	 * @param int    $reviewer_id   Admin user ID.
	 * @param string $note          Optional review note.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reject( $correction_id, $reviewer_id, $note = '' ) {
		$post = $this->require_pending( $correction_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		wp_update_post(
			array(
				'ID'          => (int) $post->ID,
				'post_status' => 'draft',
			)
		);

		update_post_meta( $post->ID, 'css_tc_reviewer_id', (int) $reviewer_id );
		update_post_meta( $post->ID, 'css_tc_reviewed_at', css_tc_addon()->punches->current_mysql_time() );
		update_post_meta( $post->ID, 'css_tc_review_note', $this->sanitize_note( $note ) );

		$fresh = get_post( $post->ID );
		return $this->to_public_row( $fresh, true );
	}

	/**
	 * @param int $correction_id Correction post ID.
	 * @return WP_Post|WP_Error
	 */
	private function require_pending( $correction_id ) {
		$post = get_post( (int) $correction_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'css_tc_missing', __( 'That suggestion was not found.', 'css-timeclock-addon' ) );
		}
		if ( 'pending' !== $post->post_status ) {
			return new WP_Error( 'css_tc_not_pending', __( 'That suggestion has already been reviewed.', 'css-timeclock-addon' ) );
		}
		return $post;
	}

	/**
	 * @param int                 $user_id Employee user ID.
	 * @param array<string,mixed> $input   Raw input.
	 * @return array<string,mixed>|WP_Error
	 */
	private function parse_submission( $user_id, $input ) {
		$punches = css_tc_addon()->punches;
		$date    = isset( $input['work_date'] ) ? sanitize_text_field( (string) $input['work_date'] ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'css_tc_bad_date', __( 'Choose a valid day.', 'css-timeclock-addon' ) );
		}

		$today    = $punches->site_date();
		$settings = css_tc_addon()->get_settings();
		$lookback = isset( $settings['times_lookback_days'] ) ? (int) $settings['times_lookback_days'] : 21;
		$oldest   = $punches->shift_date( $today, 1 - min( 60, max( 7, $lookback ) ) );
		if ( $date > $today || $date < $oldest ) {
			return new WP_Error( 'css_tc_bad_date', __( 'That day is outside the editable window.', 'css-timeclock-addon' ) );
		}

		$reason = $this->sanitize_note( isset( $input['reason'] ) ? $input['reason'] : '' );
		if ( strlen( $reason ) < 8 ) {
			return new WP_Error( 'css_tc_reason', __( 'Please add a short reason (at least 8 characters).', 'css-timeclock-addon' ) );
		}

		$shift_id      = isset( $input['shift_id'] ) ? absint( $input['shift_id'] ) : 0;
		$missing       = ! empty( $input['missing_punch'] );
		$out_next_day  = ! empty( $input['out_next_day'] );
		$proposed_in   = $punches->combine_day_time( $date, isset( $input['proposed_in'] ) ? $input['proposed_in'] : '', false );
		$proposed_out  = $punches->combine_day_time( $date, isset( $input['proposed_out'] ) ? $input['proposed_out'] : '', $out_next_day );
		$clear_out     = empty( $input['proposed_out'] ) && ! empty( $input['clear_out'] );

		$original_in  = '';
		$original_out = '';

		if ( $shift_id > 0 ) {
			$post = get_post( $shift_id );
			if ( ! $post || Css_Tc_Punches::POST_TYPE !== $post->post_type || (int) $post->post_author !== $user_id ) {
				return new WP_Error( 'css_tc_bad_shift', __( 'That shift does not belong to you.', 'css-timeclock-addon' ) );
			}
			$original_in  = (string) get_post_meta( $shift_id, 'employee_clock_in_time', true );
			$original_out = (string) get_post_meta( $shift_id, 'employee_clock_out_time', true );
		} elseif ( ! $missing && '' === $proposed_in ) {
			return new WP_Error( 'css_tc_need_in', __( 'Add a clock-in time, or mark this as a missing punch.', 'css-timeclock-addon' ) );
		}

		if ( '' === $proposed_in && '' === $proposed_out && ! $missing && ! $clear_out ) {
			return new WP_Error( 'css_tc_empty', __( 'Propose a clock-in or clock-out time, or mark a missing punch.', 'css-timeclock-addon' ) );
		}

		if ( '' !== $proposed_in && '' !== $proposed_out && strcmp( $proposed_out, $proposed_in ) <= 0 ) {
			return new WP_Error( 'css_tc_order', __( 'Clock-out must be after clock-in. Check “next day” if the shift ran past midnight.', 'css-timeclock-addon' ) );
		}

		if ( $shift_id > 0 && $proposed_in === $original_in && $proposed_out === $original_out && ! $missing && ! $clear_out ) {
			return new WP_Error( 'css_tc_unchanged', __( 'Change a time or note a missing punch before sending this.', 'css-timeclock-addon' ) );
		}

		if ( $shift_id < 1 && '' === $proposed_in ) {
			return new WP_Error( 'css_tc_need_in', __( 'A missing punch still needs a proposed clock-in time so a supervisor can apply it.', 'css-timeclock-addon' ) );
		}

		return array(
			'work_date' => $date,
			'meta'      => array(
				'css_tc_work_date'    => $date,
				'css_tc_shift_id'     => $shift_id,
				'css_tc_original_in'  => $original_in,
				'css_tc_original_out' => $original_out,
				'css_tc_proposed_in'  => $proposed_in,
				'css_tc_proposed_out' => $proposed_out,
				'css_tc_clear_out'    => $clear_out ? '1' : '',
				'css_tc_missing'      => $missing ? '1' : '',
				'css_tc_out_next_day' => $out_next_day ? '1' : '',
				'css_tc_reason'       => $reason,
			),
		);
	}

	/**
	 * @param WP_Post $post Correction post.
	 * @param bool    $for_admin Include employee name.
	 * @return array<string,mixed>|null
	 */
	public function to_public_row( $post, $for_admin = false ) {
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$punches      = css_tc_addon()->punches;
		$status       = $this->status_from_post( $post );
		$proposed_in  = (string) get_post_meta( $post->ID, 'css_tc_proposed_in', true );
		$proposed_out = (string) get_post_meta( $post->ID, 'css_tc_proposed_out', true );
		$original_in  = (string) get_post_meta( $post->ID, 'css_tc_original_in', true );
		$original_out = (string) get_post_meta( $post->ID, 'css_tc_original_out', true );
		$reviewed_at  = (string) get_post_meta( $post->ID, 'css_tc_reviewed_at', true );
		$reviewer_id  = (int) get_post_meta( $post->ID, 'css_tc_reviewer_id', true );

		$row = array(
			'id'              => (int) $post->ID,
			'work_date'       => (string) get_post_meta( $post->ID, 'css_tc_work_date', true ),
			'shift_id'        => (int) get_post_meta( $post->ID, 'css_tc_shift_id', true ),
			'status'          => $status,
			'reason'          => (string) get_post_meta( $post->ID, 'css_tc_reason', true ),
			'missing_punch'   => ( '1' === (string) get_post_meta( $post->ID, 'css_tc_missing', true ) ),
			'out_next_day'    => ( '1' === (string) get_post_meta( $post->ID, 'css_tc_out_next_day', true ) ),
			'original_in'     => $original_in ? $punches->format_time( $original_in ) : '',
			'original_out'    => $original_out ? $punches->format_time( $original_out ) : '',
			'proposed_in'     => $proposed_in ? $punches->format_time( $proposed_in ) : '',
			'proposed_out'    => $proposed_out ? $punches->format_time( $proposed_out ) : '',
			'proposed_in_hm'  => $proposed_in ? $punches->format_hour_minute( $proposed_in ) : '',
			'proposed_out_hm' => $proposed_out ? $punches->format_hour_minute( $proposed_out ) : '',
			'review_note'     => (string) get_post_meta( $post->ID, 'css_tc_review_note', true ),
			'reviewed_at'     => $reviewed_at ? $punches->format_time( $reviewed_at ) : '',
			'submitted_at'    => $punches->format_time( $post->post_date ),
		);

		if ( $for_admin ) {
			$row['employee']        = css_tc_addon()->employees->display_name( (int) $post->post_author );
			$row['employee_id']     = (int) $post->post_author;
			$row['reviewer']        = $reviewer_id ? css_tc_addon()->employees->display_name( $reviewer_id ) : '';
			$row['applied_shift_id'] = (int) get_post_meta( $post->ID, 'css_tc_applied_shift_id', true );
		}

		return $row;
	}

	/**
	 * @param WP_Post $post Correction post.
	 * @return string
	 */
	private function status_from_post( $post ) {
		if ( 'private' === $post->post_status ) {
			return self::STATUS_APPROVED;
		}
		if ( 'draft' === $post->post_status ) {
			return self::STATUS_REJECTED;
		}
		return self::STATUS_PENDING;
	}

	/**
	 * @param array<string,mixed> $args WP_Query args.
	 * @return WP_Post[]
	 */
	private function query_posts( $args ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'posts_per_page' => 40,
		);
		$query    = new WP_Query( array_merge( $defaults, $args ) );
		$posts    = $query->posts;
		wp_reset_postdata();
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @param mixed $note Raw note.
	 * @return string
	 */
	private function sanitize_note( $note ) {
		$text = sanitize_textarea_field( (string) $note );
		if ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 );
		}
		return $text;
	}

	/**
	 * @param int $user_id Employee user ID.
	 * @return true|WP_Error
	 */
	private function assert_not_rate_limited( $user_id ) {
		$key   = 'css_tc_suggest_' . (int) $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= 12 ) {
			return new WP_Error( 'css_tc_suggest_limited', __( 'Please wait a bit before sending another suggestion.', 'css-timeclock-addon' ) );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Delete all correction posts (uninstall).
	 *
	 * @return void
	 */
	public static function delete_all() {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}
}
