<?php
/**
 * Employee role helpers aligned with AIO Time Clock Lite.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the same WP roles AIO treats as clockable staff.
 */
class Css_Tc_Employees {

	/**
	 * Roles AIO Lite uses for employees (including aliases).
	 *
	 * @return string[]
	 */
	public function aio_roles() {
		$roles = array(
			'employee',
			'volunteer',
			'manager',
			'contractor',
			'time_clock_admin',
			'aio_tc_employee',
			'aio_tc_volunteer',
			'aio_tc_manager',
			'aio_tc_contractor',
		);

		/**
		 * Filter the roles treated as time-clock employees.
		 *
		 * @param string[] $roles Role slugs.
		 */
		return apply_filters( 'css_tc_employee_roles', $roles );
	}

	/**
	 * Roles shown on the shared name-list kiosk (no site administrators).
	 *
	 * @return string[]
	 */
	public function kiosk_roles() {
		$roles = array_values(
			array_diff(
				$this->aio_roles(),
				array( 'administrator' )
			)
		);

		/**
		 * Filter roles listed on the name kiosk.
		 *
		 * @param string[] $roles Role slugs.
		 */
		return apply_filters( 'css_tc_kiosk_roles', $roles );
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_employee( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		return (bool) array_intersect( (array) $user->roles, $this->aio_roles() );
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_kiosk_employee( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		return (bool) array_intersect( (array) $user->roles, $this->kiosk_roles() );
	}

	/**
	 * Display name: "Last, First" then display_name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function display_name( $user_id ) {
		$user_id = (int) $user_id;
		$first   = sanitize_text_field( (string) get_user_meta( $user_id, 'first_name', true ) );
		$last    = sanitize_text_field( (string) get_user_meta( $user_id, 'last_name', true ) );

		if ( '' !== $last || '' !== $first ) {
			if ( '' !== $last && '' !== $first ) {
				return $last . ', ' . $first;
			}
			return trim( $last . ' ' . $first );
		}

		$user = get_userdata( $user_id );
		if ( $user && $user->display_name ) {
			return sanitize_text_field( $user->display_name );
		}

		return $user ? sanitize_text_field( $user->user_login ) : '';
	}

	/**
	 * First + last for kiosk greetings.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function greeting_name( $user_id ) {
		$first = sanitize_text_field( (string) get_user_meta( (int) $user_id, 'first_name', true ) );
		$last  = sanitize_text_field( (string) get_user_meta( (int) $user_id, 'last_name', true ) );
		$name  = trim( $first . ' ' . $last );
		if ( '' !== $name ) {
			return $name;
		}
		return $this->display_name( $user_id );
	}

	/**
	 * Department term name from AIO's user taxonomy, if present.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function department( $user_id ) {
		if ( ! taxonomy_exists( 'department' ) ) {
			return '';
		}

		$terms = wp_get_object_terms( (int) $user_id, 'department', array( 'fields' => 'names' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return sanitize_text_field( (string) $terms[0] );
	}

	/**
	 * Employees for admin PIN table (AIO roles, including time_clock_admin).
	 *
	 * @return WP_User[]
	 */
	public function list_for_admin() {
		return $this->query_users( $this->aio_roles() );
	}

	/**
	 * Active employees for the name kiosk. Optionally only those with a PIN.
	 *
	 * @param bool $require_pin Whether to hide users without a hashed PIN.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for_kiosk( $require_pin = true ) {
		$users  = $this->query_users( $this->kiosk_roles() );
		$result = array();

		foreach ( $users as $user ) {
			$has_pin = Css_Tc_Pins::user_has_pin( (int) $user->ID );
			if ( $require_pin && ! $has_pin ) {
				continue;
			}

			$result[] = array(
				'id'         => (int) $user->ID,
				'name'       => $this->display_name( (int) $user->ID ),
				'greeting'   => $this->greeting_name( (int) $user->ID ),
				'department' => $this->department( (int) $user->ID ),
			);
		}

		usort(
			$result,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $result;
	}

	/**
	 * @param string[] $roles Role slugs.
	 * @return WP_User[]
	 */
	private function query_users( $roles ) {
		if ( empty( $roles ) ) {
			return array();
		}

		$args = array(
			'role__in' => $roles,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'number'   => 400,
			'fields'   => 'all',
		);

		if ( is_multisite() ) {
			$args['blog_id'] = get_current_blog_id();
		}

		return get_users( $args );
	}
}
