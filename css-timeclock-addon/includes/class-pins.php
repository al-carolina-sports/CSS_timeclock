<?php
/**
 * Hashed employee PIN storage, lookup, and failed-attempt rate limits.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PINs are stored with wp_hash_password() and checked with wp_check_password().
 * Plaintext is never written to the database.
 */
class Css_Tc_Pins {

	const META_HASH = 'css_tc_pin_hash';
	const META_SET  = 'css_tc_pin_set_at';

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_pin( $user_id ) {
		$hash = get_user_meta( (int) $user_id, self::META_HASH, true );
		return is_string( $hash ) && '' !== $hash;
	}

	/**
	 * Normalize a submitted PIN to digits only.
	 *
	 * @param mixed $pin Raw PIN.
	 * @return string
	 */
	public function normalize( $pin ) {
		return preg_replace( '/\D+/', '', (string) $pin );
	}

	/**
	 * @param string $pin Normalized PIN.
	 * @return true|WP_Error
	 */
	public function validate_format( $pin ) {
		$settings = css_tc_addon()->get_settings();
		$min      = max( 4, (int) $settings['pin_min_length'] );
		$max      = max( $min, (int) $settings['pin_max_length'] );

		if ( strlen( $pin ) < $min || strlen( $pin ) > $max ) {
			return new WP_Error(
				'css_tc_pin_length',
				sprintf(
					/* translators: 1: minimum digits, 2: maximum digits */
					__( 'PIN must be %1$d to %2$d digits.', 'css-timeclock-addon' ),
					$min,
					$max
				)
			);
		}

		return true;
	}

	/**
	 * Store a hashed PIN for an employee. Enforces uniqueness across users.
	 *
	 * @param int    $user_id User ID.
	 * @param string $pin     Plain PIN (will not be stored).
	 * @return true|WP_Error
	 */
	public function set_pin( $user_id, $pin ) {
		$user_id = (int) $user_id;
		$pin     = $this->normalize( $pin );

		if ( ! css_tc_addon()->employees->is_employee( $user_id ) ) {
			return new WP_Error( 'css_tc_not_employee', __( 'That user is not a time-clock employee.', 'css-timeclock-addon' ) );
		}

		$valid = $this->validate_format( $pin );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$owner = $this->find_user_id_by_pin( $pin );
		if ( $owner && (int) $owner !== $user_id ) {
			return new WP_Error( 'css_tc_pin_taken', __( 'That PIN is already assigned to another employee. Choose a different PIN.', 'css-timeclock-addon' ) );
		}

		$hash = wp_hash_password( $pin );
		if ( ! is_string( $hash ) || '' === $hash ) {
			return new WP_Error( 'css_tc_pin_hash', __( 'Could not hash the PIN. Try again.', 'css-timeclock-addon' ) );
		}

		update_user_meta( $user_id, self::META_HASH, $hash );
		update_user_meta( $user_id, self::META_SET, time() );

		return true;
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function clear_pin( $user_id ) {
		delete_user_meta( (int) $user_id, self::META_HASH );
		delete_user_meta( (int) $user_id, self::META_SET );
	}

	/**
	 * Find the employee whose hashed PIN matches. Returns 0 if none.
	 *
	 * @param string $pin Normalized PIN.
	 * @return int
	 */
	public function find_user_id_by_pin( $pin ) {
		$pin = $this->normalize( $pin );
		if ( '' === $pin ) {
			return 0;
		}

		$users = get_users(
			array(
				'meta_key'     => self::META_HASH,
				'meta_compare' => 'EXISTS',
				'fields'       => 'ID',
				'number'       => 400,
			)
		);

		foreach ( $users as $user ) {
			$user_id = (int) $user;
			$hash    = get_user_meta( $user_id, self::META_HASH, true );
			if ( ! is_string( $hash ) || '' === $hash ) {
				continue;
			}
			if ( wp_check_password( $pin, $hash, $user_id ) ) {
				return $user_id;
			}
		}

		return 0;
	}

	/**
	 * Verify a PIN against a specific employee.
	 *
	 * @param int    $user_id User ID.
	 * @param string $pin     Plain PIN.
	 * @return bool
	 */
	public function verify_for_user( $user_id, $pin ) {
		$pin  = $this->normalize( $pin );
		$hash = get_user_meta( (int) $user_id, self::META_HASH, true );
		if ( ! is_string( $hash ) || '' === $hash || '' === $pin ) {
			return false;
		}
		return wp_check_password( $pin, $hash, (int) $user_id );
	}

	/**
	 * @return string
	 */
	public function client_key() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return 'css_tc_' . md5( $ip . '|' . (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public function assert_not_rate_limited() {
		$settings = css_tc_addon()->get_settings();
		$key      = $this->client_key() . '_lock';
		if ( get_transient( $key ) ) {
			return new WP_Error(
				'css_tc_locked',
				__( 'Too many incorrect PIN attempts. Wait a few minutes and try again.', 'css-timeclock-addon' )
			);
		}

		$fails = (int) get_transient( $this->client_key() . '_fails' );
		if ( $fails >= (int) $settings['rate_limit_max'] ) {
			set_transient( $key, 1, (int) $settings['rate_limit_window'] );
			delete_transient( $this->client_key() . '_fails' );
			return new WP_Error(
				'css_tc_locked',
				__( 'Too many incorrect PIN attempts. Wait a few minutes and try again.', 'css-timeclock-addon' )
			);
		}

		return true;
	}

	/**
	 * @return void
	 */
	public function record_failure() {
		$settings = css_tc_addon()->get_settings();
		$key      = $this->client_key() . '_fails';
		$fails    = (int) get_transient( $key );
		++$fails;
		set_transient( $key, $fails, (int) $settings['rate_limit_window'] );

		if ( $fails >= (int) $settings['rate_limit_max'] ) {
			set_transient( $this->client_key() . '_lock', 1, (int) $settings['rate_limit_window'] );
			delete_transient( $key );
		}
	}

	/**
	 * @return void
	 */
	public function record_success() {
		delete_transient( $this->client_key() . '_fails' );
		delete_transient( $this->client_key() . '_lock' );
	}
}
