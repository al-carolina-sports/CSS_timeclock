<?php
/**
 * SMOTC → Timecards.
 *
 * @package CssTimeclockAddon
 *
 * @var WP_User[]                $employees
 * @var int                      $user_id
 * @var int                      $prev_id
 * @var int                      $next_id
 * @var array<string,mixed>|null $sheet
 * @var string                   $edit_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode       = 'admin';
$notice     = '';
$form_error = '';
?>
<div class="wrap css-tc-timecards-admin">
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Timecards', 'css-timeclock-addon' ); ?></h1>
	<?php if ( ! $sheet ) : ?>
		<h1><?php echo esc_html__( 'Timecards', 'css-timeclock-addon' ); ?></h1>
		<p><?php echo esc_html__( 'No time-clock employees yet. Create users with an Employee, Manager, Volunteer, or Contractor role.', 'css-timeclock-addon' ); ?></p>
	<?php else : ?>
		<?php include CSS_TC_ADDON_DIR . 'public/views/timecard-sheet.php'; ?>
	<?php endif; ?>
</div>
