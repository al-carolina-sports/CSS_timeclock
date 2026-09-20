<?php
/**
 * Public live who-is-working board (PIN and name kiosks).
 *
 * @package CssTimeclockAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$board_suffix = isset( $mode ) ? sanitize_html_class( (string) $mode ) : 'kiosk';
$working_id   = 'css-tc-board-working-' . $board_suffix;
$out_id       = 'css-tc-board-out-' . $board_suffix;
?>
<aside class="css-tc-board" data-role="board" aria-live="polite">
	<header class="css-tc-board__header">
		<p class="css-tc-kiosk__eyebrow"><?php echo esc_html__( 'Live status', 'css-timeclock-addon' ); ?></p>
		<h2 class="css-tc-board__title"><?php echo esc_html__( "Who's working", 'css-timeclock-addon' ); ?></h2>
		<p class="css-tc-board__updated" data-role="board-updated"></p>
	</header>
	<p class="css-tc-kiosk__error" data-role="board-error" hidden></p>

	<section class="css-tc-board__section css-tc-board__section--in" aria-labelledby="<?php echo esc_attr( $working_id ); ?>">
		<h3 id="<?php echo esc_attr( $working_id ); ?>">
			<span><?php echo esc_html__( 'Working now', 'css-timeclock-addon' ); ?></span>
			<span class="css-tc-board__count" data-role="working-count">0</span>
		</h3>
		<ul class="css-tc-board__list" data-role="working-list"></ul>
		<p class="css-tc-board__empty" data-role="working-empty" hidden><?php echo esc_html__( 'Nobody is clocked in.', 'css-timeclock-addon' ); ?></p>
	</section>

	<section class="css-tc-board__section css-tc-board__section--out" aria-labelledby="<?php echo esc_attr( $out_id ); ?>">
		<h3 id="<?php echo esc_attr( $out_id ); ?>">
			<span><?php echo esc_html__( 'Not clocked in', 'css-timeclock-addon' ); ?></span>
			<span class="css-tc-board__count" data-role="out-count">0</span>
		</h3>
		<ul class="css-tc-board__list" data-role="out-list"></ul>
		<p class="css-tc-board__empty" data-role="out-empty" hidden><?php echo esc_html__( 'Everyone is clocked in.', 'css-timeclock-addon' ); ?></p>
	</section>
</aside>
