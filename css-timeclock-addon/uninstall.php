<?php
/**
 * Remove add-on options, hashed PINs, and correction posts. Leaves AIO shift posts untouched.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'css_tc_addon_settings' );

$correction_ids = get_posts(
	array(
		'post_type'      => 'css_tc_correction',
		'post_status'    => 'any',
		'posts_per_page' => 500,
		'fields'         => 'ids',
	)
);
foreach ( $correction_ids as $correction_id ) {
	wp_delete_post( (int) $correction_id, true );
}

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
	delete_user_meta( (int) $user_id, 'css_tc_last_facility' );
	delete_user_meta( (int) $user_id, 'css_tc_last_location' );
}

$place_user_ids = get_users(
	array(
		'meta_key'     => 'css_tc_last_facility',
		'meta_compare' => 'EXISTS',
		'fields'       => 'ID',
		'number'       => 5000,
	)
);

foreach ( $place_user_ids as $user_id ) {
	delete_user_meta( (int) $user_id, 'css_tc_last_facility' );
	delete_user_meta( (int) $user_id, 'css_tc_last_location' );
}
