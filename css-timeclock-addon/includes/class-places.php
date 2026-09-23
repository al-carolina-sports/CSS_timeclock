<?php
/**
 * Facility and location lists for kiosk punches.
 *
 * Facility replaces AIO's department for this product: the facility label is
 * written to the shift's department meta so Real Time Monitoring still shows
 * a business, and css_tc_facility / css_tc_location are stored for filtering.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed facilities, locations, and the last pair chosen by an employee.
 */
class Css_Tc_Places {

	const META_FACILITY = 'css_tc_facility';
	const META_LOCATION = 'css_tc_location';
	const USER_FACILITY = 'css_tc_last_facility';
	const USER_LOCATION = 'css_tc_last_location';

	/**
	 * @return string[]
	 */
	public static function default_facilities() {
		return array(
			'Carolina Sports and Spine',
			'BioFunctionalMed',
			'TrueRadiance Medispa',
			'Other',
		);
	}

	/**
	 * @return string[]
	 */
	public static function default_locations() {
		return array(
			'Rocky Mount',
			'Wilson',
			'Raleigh',
		);
	}

	/**
	 * Whether the kiosk should ask for facility, then location, on each punch.
	 *
	 * @return bool
	 */
	public function prompt_enabled() {
		$settings = css_tc_addon()->get_settings();
		if ( empty( $settings['place_prompt_enabled'] ) ) {
			return false;
		}

		return ! empty( $this->facilities() ) && ! empty( $this->locations() );
	}

	/**
	 * Facilities shown on the kiosk (settings, then the css_tc_facilities filter).
	 *
	 * @return string[]
	 */
	public function facilities() {
		$list = apply_filters( 'css_tc_facilities', $this->stored_facilities() );
		return $this->finalize_list( $list, self::default_facilities() );
	}

	/**
	 * Locations shown on the kiosk (settings, then the css_tc_locations filter).
	 *
	 * @return string[]
	 */
	public function locations() {
		$list = apply_filters( 'css_tc_locations', $this->stored_locations() );
		return $this->finalize_list( $list, self::default_locations() );
	}

	/**
	 * Saved facility list for the settings screen (no filter).
	 *
	 * @return string[]
	 */
	public function stored_facilities() {
		return $this->stored_labels( 'facilities', self::default_facilities() );
	}

	/**
	 * Saved location list for the settings screen (no filter).
	 *
	 * @return string[]
	 */
	public function stored_locations() {
		return $this->stored_labels( 'locations', self::default_locations() );
	}

	/**
	 * One label per line. Empty input returns an empty array (caller restores defaults).
	 *
	 * @param mixed $raw Textarea string or list.
	 * @return string[]
	 */
	public function parse_lines( $raw ) {
		if ( is_array( $raw ) ) {
			$lines = $raw;
		} else {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
			if ( ! is_array( $lines ) ) {
				$lines = array();
			}
		}

		$clean = array();
		$seen  = array();

		foreach ( $lines as $line ) {
			$label = sanitize_text_field( (string) $line );
			$label = trim( $label );
			if ( '' === $label ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$label = mb_substr( $label, 0, 80 );
			} else {
				$label = substr( $label, 0, 80 );
			}
			$key = strtolower( $label );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean[]      = $label;
			if ( count( $clean ) >= 24 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * @param mixed $raw Submitted facility label.
	 * @return string Canonical label, or empty when it is not allowed.
	 */
	public function canonical_facility( $raw ) {
		return $this->canonical( $raw, $this->facilities() );
	}

	/**
	 * @param mixed $raw Submitted location label.
	 * @return string Canonical label, or empty when it is not allowed.
	 */
	public function canonical_location( $raw ) {
		return $this->canonical( $raw, $this->locations() );
	}

	/**
	 * Last facility and location still present in the current lists.
	 *
	 * @param int $user_id Employee user ID.
	 * @return array{facility:string,location:string}
	 */
	public function last_for( $user_id ) {
		$user_id = (int) $user_id;
		return array(
			'facility' => $this->canonical_facility( get_user_meta( $user_id, self::USER_FACILITY, true ) ),
			'location' => $this->canonical_location( get_user_meta( $user_id, self::USER_LOCATION, true ) ),
		);
	}

	/**
	 * @param int    $user_id  Employee user ID.
	 * @param string $facility Canonical facility.
	 * @param string $location Canonical location.
	 * @return void
	 */
	public function remember( $user_id, $facility, $location ) {
		update_user_meta( (int) $user_id, self::USER_FACILITY, $facility );
		update_user_meta( (int) $user_id, self::USER_LOCATION, $location );
	}

	/**
	 * @param int $shift_id Shift post ID.
	 * @return array{facility:string,location:string}
	 */
	public function read_shift( $shift_id ) {
		$shift_id = (int) $shift_id;
		return array(
			'facility' => sanitize_text_field( (string) get_post_meta( $shift_id, self::META_FACILITY, true ) ),
			'location' => sanitize_text_field( (string) get_post_meta( $shift_id, self::META_LOCATION, true ) ),
		);
	}

	/**
	 * Persist the pair and mirror the facility into AIO's department meta.
	 *
	 * @param int    $shift_id Shift post ID.
	 * @param string $facility Canonical facility.
	 * @param string $location Canonical location.
	 * @return void
	 */
	public function write_shift( $shift_id, $facility, $location ) {
		$shift_id = (int) $shift_id;
		update_post_meta( $shift_id, self::META_FACILITY, $facility );
		update_post_meta( $shift_id, self::META_LOCATION, $location );

		/**
		 * Value stored in AIO's department shift meta. Defaults to the facility
		 * label so monitoring and reports show the business the employee chose.
		 *
		 * @param string $department Department meta value.
		 * @param string $facility   Facility label.
		 * @param string $location   Location label.
		 * @param int    $shift_id   Shift post ID.
		 */
		$department = apply_filters( 'css_tc_aio_department', $facility, $facility, $location, $shift_id );
		$department = sanitize_text_field( (string) $department );
		if ( '' !== $department ) {
			update_post_meta( $shift_id, 'department', $department );
		}
	}

	/**
	 * @param string   $key      Settings key.
	 * @param string[] $defaults Fallback labels.
	 * @return string[]
	 */
	private function stored_labels( $key, $defaults ) {
		$settings = css_tc_addon()->get_settings();
		$stored   = ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) ? $settings[ $key ] : $defaults;
		$clean    = $this->parse_lines( $stored );
		return ! empty( $clean ) ? $clean : $defaults;
	}

	/**
	 * @param mixed    $list     Filter result.
	 * @param string[] $defaults Fallback labels.
	 * @return string[]
	 */
	private function finalize_list( $list, $defaults ) {
		if ( ! is_array( $list ) ) {
			return $defaults;
		}
		$clean = $this->parse_lines( $list );
		return ! empty( $clean ) ? $clean : $defaults;
	}

	/**
	 * @param mixed    $raw     Submitted label.
	 * @param string[] $allowed Canonical labels.
	 * @return string
	 */
	private function canonical( $raw, $allowed ) {
		$raw = sanitize_text_field( (string) $raw );
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		foreach ( $allowed as $label ) {
			if ( 0 === strcasecmp( $label, $raw ) ) {
				return $label;
			}
		}
		return '';
	}
}
