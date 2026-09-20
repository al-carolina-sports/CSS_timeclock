<?php
/**
 * Remove add-on options and hashed PINs. Leaves AIO shift posts untouched.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'css_tc_addon_settings' );

$user_ids = get_users(
	array(
		'meta_key'     => 'css_tc_pin_hash',
		'meta_compare' => 'EXISTS',
		'fields'       => 'ID',
		'number'       => 5000,
	)
);

foreach ( $user_ids as $user_id ) {
	delete_user_meta( (int) $user_id, 'css_tc_pin_hash' );
	delete_user_meta( (int) $user_id, 'css_tc_pin_set_at' );
}
