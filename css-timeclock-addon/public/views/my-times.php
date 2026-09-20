<?php
/**
 * Employee times dashboard markup.
 *
 * @package CssTimeclockAddon
 *
 * @var bool   $logged_in
 * @var bool   $allowed
 * @var string $login_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="css-tc-times" data-enabled="<?php echo $allowed ? '1' : '0'; ?>">
	<div class="css-tc-times__chrome">
		<div>
			<p class="css-tc-times__eyebrow"><?php echo esc_html__( 'My time clock', 'css-timeclock-addon' ); ?></p>
			<h1 class="css-tc-times__title"><?php echo esc_html__( 'Your recent punches', 'css-timeclock-addon' ); ?></h1>
		</div>
	</div>

	<?php if ( ! $logged_in ) : ?>
		<div class="css-tc-times__panel">
			<p><?php echo esc_html__( 'Sign in with your work WordPress account to see your punches and suggest a correction.', 'css-timeclock-addon' ); ?></p>
			<p><a class="css-tc-times__button" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Sign in', 'css-timeclock-addon' ); ?></a></p>
		</div>
	<?php elseif ( ! $allowed ) : ?>
		<div class="css-tc-times__panel">
			<p><?php echo esc_html__( 'This page is for time-clock employees. If you need access, ask a supervisor to assign you an Employee role.', 'css-timeclock-addon' ); ?></p>
		</div>
	<?php else : ?>
		<p class="css-tc-times__intro" data-role="intro"></p>
		<p class="css-tc-times__error" data-role="error" hidden></p>
		<p class="css-tc-times__notice" data-role="notice" hidden></p>
		<div class="css-tc-times__days" data-role="days"></div>

		<div class="css-tc-times__modal" data-role="modal" hidden>
			<div class="css-tc-times__dialog" role="dialog" aria-modal="true" aria-labelledby="css-tc-suggest-title">
				<h2 id="css-tc-suggest-title"><?php echo esc_html__( 'Suggest an edit', 'css-timeclock-addon' ); ?></h2>
				<p class="css-tc-times__dialog-day" data-role="modal-day"></p>
				<form data-role="suggest-form">
					<input type="hidden" name="work_date" value="" />
					<input type="hidden" name="shift_id" value="0" />
					<label class="css-tc-times__field">
						<span><?php echo esc_html__( 'Clock in', 'css-timeclock-addon' ); ?></span>
						<input type="time" name="proposed_in" />
					</label>
					<label class="css-tc-times__field">
						<span><?php echo esc_html__( 'Clock out', 'css-timeclock-addon' ); ?></span>
						<input type="time" name="proposed_out" />
					</label>
					<label class="css-tc-times__check">
						<input type="checkbox" name="out_next_day" value="1" />
						<span><?php echo esc_html__( 'Clock-out is the next day', 'css-timeclock-addon' ); ?></span>
					</label>
					<label class="css-tc-times__check">
						<input type="checkbox" name="missing_punch" value="1" />
						<span><?php echo esc_html__( 'I missed a punch / this day is incomplete', 'css-timeclock-addon' ); ?></span>
					</label>
					<label class="css-tc-times__field">
						<span><?php echo esc_html__( 'Reason (required)', 'css-timeclock-addon' ); ?></span>
						<textarea name="reason" rows="3" maxlength="500" required></textarea>
					</label>
					<p class="css-tc-times__error" data-role="form-error" hidden></p>
					<div class="css-tc-times__dialog-actions">
						<button type="submit" class="css-tc-times__button"><?php echo esc_html__( 'Send suggestion', 'css-timeclock-addon' ); ?></button>
						<button type="button" class="css-tc-times__button css-tc-times__button--ghost" data-action="close-modal"><?php echo esc_html__( 'Cancel', 'css-timeclock-addon' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>
