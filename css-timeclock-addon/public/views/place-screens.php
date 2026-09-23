<?php
/**
 * Facility then location steps, shared by the PIN and name kiosks.
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="css-tc-kiosk__panel" data-screen="facility" hidden>
	<p class="css-tc-kiosk__hello css-tc-kiosk__hello--small" data-role="facility-hello"></p>
	<p class="css-tc-kiosk__prompt"><?php echo esc_html__( 'Which facility?', 'css-timeclock-addon' ); ?></p>
	<p class="css-tc-kiosk__meta"><?php echo esc_html__( 'Choose where you are working today. You can change it on every punch.', 'css-timeclock-addon' ); ?></p>
	<p class="css-tc-kiosk__hint" data-role="facility-hint" hidden><?php echo esc_html__( 'Your last choice is highlighted. Tap Continue, or pick a different one.', 'css-timeclock-addon' ); ?></p>
	<div class="css-tc-choices" data-role="facilities"></div>
	<p class="css-tc-kiosk__error" data-role="facility-error" hidden><?php echo esc_html__( 'Choose a facility to continue.', 'css-timeclock-addon' ); ?></p>
	<button type="button" class="css-tc-btn css-tc-btn--in css-tc-btn--wide" data-action="confirm-facility" disabled><?php echo esc_html__( 'Continue', 'css-timeclock-addon' ); ?></button>
	<button type="button" class="css-tc-btn css-tc-btn--text" data-action="cancel"><?php echo esc_html__( 'Cancel', 'css-timeclock-addon' ); ?></button>
</section>

<section class="css-tc-kiosk__panel" data-screen="location" hidden>
	<p class="css-tc-kiosk__hello css-tc-kiosk__hello--small" data-role="location-hello"></p>
	<p class="css-tc-kiosk__prompt"><?php echo esc_html__( 'Which location?', 'css-timeclock-addon' ); ?></p>
	<p class="css-tc-kiosk__place" data-role="location-facility"></p>
	<p class="css-tc-kiosk__meta"><?php echo esc_html__( 'Choose the office for this punch. You can change it on every punch.', 'css-timeclock-addon' ); ?></p>
	<p class="css-tc-kiosk__hint" data-role="location-hint" hidden><?php echo esc_html__( 'Your last choice is highlighted. Tap Continue, or pick a different one.', 'css-timeclock-addon' ); ?></p>
	<div class="css-tc-choices" data-role="locations"></div>
	<p class="css-tc-kiosk__error" data-role="location-error" hidden><?php echo esc_html__( 'Choose a location to continue.', 'css-timeclock-addon' ); ?></p>
	<button type="button" class="css-tc-btn css-tc-btn--in css-tc-btn--wide" data-action="confirm-location" disabled><?php echo esc_html__( 'Continue', 'css-timeclock-addon' ); ?></button>
	<button type="button" class="css-tc-btn css-tc-btn--text" data-action="back-facility"><?php echo esc_html__( 'Back', 'css-timeclock-addon' ); ?></button>
	<button type="button" class="css-tc-btn css-tc-btn--text" data-action="cancel"><?php echo esc_html__( 'Cancel', 'css-timeclock-addon' ); ?></button>
</section>
