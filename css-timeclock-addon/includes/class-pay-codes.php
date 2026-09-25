<?php
/**
 * Pay codes for timecard summaries.
 *
 * Regular is the only built-in code. Holiday and others can be added with the
 * css_tc_pay_codes and css_tc_shift_pay_code filters. This class does not
 * invent overtime rules.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pay-code registry.
 */
class Css_Tc_Pay_Codes {

	const REGULAR = 'regular';

	/**
	 * @return array<string,array{label:string,order:int}>
	 */
	public function definitions() {
		$codes = array(
			self::REGULAR => array(
				'label' => __( 'Regular', 'css-timeclock-addon' ),
				'order' => 10,
			),
		);

		/**
		 * Register additional pay codes (for example Holiday).
		 *
		 * @param array<string,array{label:string,order:int}> $codes Code slug => definition.
		 */
		$filtered = apply_filters( 'css_tc_pay_codes', $codes );
		if ( ! is_array( $filtered ) || ! isset( $filtered[ self::REGULAR ] ) ) {
			return $codes;
		}

		return $filtered;
	}

	/**
	 * Pay code for one shift. Unknown codes fall back to Regular.
	 *
	 * @param array<string,mixed> $shift Shift row.
	 * @return string
	 */
	public function code_for_shift( $shift ) {
		/**
		 * Classify a shift. Return a slug from css_tc_pay_codes.
		 *
		 * @param string              $code  Default regular.
		 * @param array<string,mixed> $shift Shift row.
		 */
		$code = apply_filters( 'css_tc_shift_pay_code', self::REGULAR, $shift );
		$code = is_string( $code ) ? sanitize_key( $code ) : self::REGULAR;
		$defs = $this->definitions();
		if ( ! isset( $defs[ $code ] ) ) {
			return self::REGULAR;
		}
		return $code;
	}
}
