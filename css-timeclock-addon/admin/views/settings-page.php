<?php
/**
 * Admin settings + PIN table.
 *
 * @package CssTimeclockAddon
 *
 * @var array<string,mixed> $settings
 * @var WP_User[]           $employees
 * @var string              $tab
 * @var string              $pin_page
 * @var string              $name_page
 * @var string              $times_page
 * @var string              $base_url
 * @var array<string,mixed> $queue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap css-tc-admin">
	<h1><?php echo esc_html__( 'Time Clock Kiosk', 'css-timeclock-addon' ); ?></h1>
	<p class="css-tc-lead">
		<?php echo esc_html__( 'Shared tablet kiosks for All in One Time Clock Lite. Employees clock in and out with a PIN — no WordPress login on the tablet.', 'css-timeclock-addon' ); ?>
	</p>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( $base_url . '&tab=settings' ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html__( 'Kiosk settings', 'css-timeclock-addon' ); ?>
		</a>
		<a href="<?php echo esc_url( $base_url . '&tab=pins' ); ?>" class="nav-tab <?php echo 'pins' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html__( 'Employee PINs', 'css-timeclock-addon' ); ?>
		</a>
		<a href="<?php echo esc_url( $base_url . '&tab=corrections' ); ?>" class="nav-tab <?php echo 'corrections' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php echo esc_html__( 'Corrections', 'css-timeclock-addon' ); ?>
			<?php if ( ! empty( $queue['pending_count'] ) ) : ?>
				<span class="css-tc-tab-count"><?php echo esc_html( (string) (int) $queue['pending_count'] ); ?></span>
			<?php endif; ?>
		</a>
	</nav>

	<div class="css-tc-notice" hidden></div>

	<?php if ( 'settings' === $tab ) : ?>
		<form class="css-tc-settings-form" method="post" action="">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'PIN kiosk', 'css-timeclock-addon' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="pin_kiosk_enabled" value="1" <?php checked( ! empty( $settings['pin_kiosk_enabled'] ) ); ?> />
							<?php echo esc_html__( 'Enable the PIN pad kiosk', 'css-timeclock-addon' ); ?>
						</label>
						<p class="description">
							<?php echo esc_html__( 'Shortcode:', 'css-timeclock-addon' ); ?>
							<code>[css_tc_pin_kiosk]</code>
							<?php if ( $pin_page ) : ?>
								— <a href="<?php echo esc_url( $pin_page ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open PIN kiosk page', 'css-timeclock-addon' ); ?></a>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Name-list kiosk', 'css-timeclock-addon' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="name_kiosk_enabled" value="1" <?php checked( ! empty( $settings['name_kiosk_enabled'] ) ); ?> />
							<?php echo esc_html__( 'Enable the name-list kiosk', 'css-timeclock-addon' ); ?>
						</label>
						<p class="description">
							<?php echo esc_html__( 'Shortcode:', 'css-timeclock-addon' ); ?>
							<code>[css_tc_name_kiosk]</code>
							<?php echo esc_html__( 'Phase 1 always asks for a PIN after a name is tapped.', 'css-timeclock-addon' ); ?>
							<?php if ( $name_page ) : ?>
								— <a href="<?php echo esc_url( $name_page ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open name kiosk page', 'css-timeclock-addon' ); ?></a>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pin_min_length"><?php echo esc_html__( 'PIN length', 'css-timeclock-addon' ); ?></label></th>
					<td>
						<input name="pin_min_length" id="pin_min_length" type="number" min="4" max="8" value="<?php echo esc_attr( (string) $settings['pin_min_length'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'to', 'css-timeclock-addon' ); ?>
						<input name="pin_max_length" id="pin_max_length" type="number" min="4" max="12" value="<?php echo esc_attr( (string) $settings['pin_max_length'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'digits', 'css-timeclock-addon' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rate_limit_max"><?php echo esc_html__( 'Failed PIN limit', 'css-timeclock-addon' ); ?></label></th>
					<td>
						<input name="rate_limit_max" id="rate_limit_max" type="number" min="3" max="20" value="<?php echo esc_attr( (string) $settings['rate_limit_max'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'incorrect attempts per tablet IP, then lock for', 'css-timeclock-addon' ); ?>
						<input name="rate_limit_window" id="rate_limit_window" type="number" min="60" max="3600" value="<?php echo esc_attr( (string) $settings['rate_limit_window'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'seconds.', 'css-timeclock-addon' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Employee times', 'css-timeclock-addon' ); ?></th>
					<td>
						<p class="description">
							<?php echo esc_html__( 'Shortcode:', 'css-timeclock-addon' ); ?>
							<code>[css_tc_my_times]</code>
							<?php if ( $times_page ) : ?>
								— <a href="<?php echo esc_url( $times_page ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open employee times page', 'css-timeclock-addon' ); ?></a>
							<?php endif; ?>
						</p>
						<p class="description"><?php echo esc_html__( 'Logged-in employees can view their own punches and suggest edits. Supervisors approve them on the Corrections tab.', 'css-timeclock-addon' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="times_lookback_days"><?php echo esc_html__( 'Times lookback', 'css-timeclock-addon' ); ?></label></th>
					<td>
						<input name="times_lookback_days" id="times_lookback_days" type="number" min="7" max="60" value="<?php echo esc_attr( (string) ( isset( $settings['times_lookback_days'] ) ? $settings['times_lookback_days'] : 21 ) ); ?>" class="small-text" />
						<?php echo esc_html__( 'days of punches employees can see and suggest edits for.', 'css-timeclock-addon' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="idle_reset_ms"><?php echo esc_html__( 'Return to idle', 'css-timeclock-addon' ); ?></label></th>
					<td>
						<input name="idle_reset_ms" id="idle_reset_ms" type="number" min="3000" max="30000" step="500" value="<?php echo esc_attr( (string) $settings['idle_reset_ms'] ); ?>" class="small-text" />
						<?php echo esc_html__( 'milliseconds after a successful punch (no lingering employee session).', 'css-timeclock-addon' ); ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save settings', 'css-timeclock-addon' ); ?></button>
				<button type="button" class="button css-tc-create-pages"><?php echo esc_html__( 'Create or restore kiosk and times pages', 'css-timeclock-addon' ); ?></button>
			</p>
		</form>

		<div class="css-tc-help">
			<h2><?php echo esc_html__( 'How punches reach AIO Lite', 'css-timeclock-addon' ); ?></h2>
			<p>
				<?php echo esc_html__( 'AIO Lite’s clock AJAX only runs for a logged-in WordPress user. This add-on does not call that AJAX and does not edit AIO files. After a valid PIN it creates or closes the same shift custom posts AIO uses (post type shift, author = employee, meta employee_clock_in_time / employee_clock_out_time). Time Clock Lite → Real Time Monitoring lists anyone whose clock-out meta is still empty.', 'css-timeclock-addon' ); ?>
			</p>
		</div>
	<?php elseif ( 'pins' === $tab ) : ?>
		<p>
			<?php echo esc_html__( 'PINs are stored with WordPress password hashing. They are never saved in plaintext. The eye on each PIN field only reveals the digits you are typing. Each PIN must be unique. Employees without a PIN do not appear on the name-list kiosk.', 'css-timeclock-addon' ); ?>
		</p>

		<p>
			<label for="css-tc-pin-filter" class="screen-reader-text"><?php echo esc_html__( 'Filter employees', 'css-timeclock-addon' ); ?></label>
			<input type="search" id="css-tc-pin-filter" class="regular-text" placeholder="<?php echo esc_attr__( 'Filter by name…', 'css-timeclock-addon' ); ?>" />
		</p>

		<table class="widefat striped css-tc-pin-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Employee', 'css-timeclock-addon' ); ?></th>
					<th><?php echo esc_html__( 'Role', 'css-timeclock-addon' ); ?></th>
					<th><?php echo esc_html__( 'PIN status', 'css-timeclock-addon' ); ?></th>
					<th><?php echo esc_html__( 'Set PIN', 'css-timeclock-addon' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $employees ) ) : ?>
				<tr>
					<td colspan="4">
						<?php echo esc_html__( 'No time-clock employees found. Create WordPress users with the Employee, Volunteer, Manager, or Contractor role (the roles AIO Lite uses).', 'css-timeclock-addon' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $employees as $user ) : ?>
					<?php
					$has_pin = Css_Tc_Pins::user_has_pin( (int) $user->ID );
					$set_at  = (int) get_user_meta( (int) $user->ID, Css_Tc_Pins::META_SET, true );
					$roles   = implode( ', ', array_map( 'sanitize_text_field', (array) $user->roles ) );
					?>
					<tr class="css-tc-pin-row" data-name="<?php echo esc_attr( strtolower( css_tc_addon()->employees->display_name( (int) $user->ID ) ) ); ?>">
						<td>
							<strong><?php echo esc_html( css_tc_addon()->employees->display_name( (int) $user->ID ) ); ?></strong>
							<div class="row-actions">
								<?php echo esc_html( $user->user_login ); ?>
							</div>
						</td>
						<td><?php echo esc_html( $roles ); ?></td>
						<td class="css-tc-pin-status">
							<?php if ( $has_pin ) : ?>
								<span class="css-tc-pill css-tc-pill-set"><?php echo esc_html__( 'Set', 'css-timeclock-addon' ); ?></span>
								<?php if ( $set_at ) : ?>
									<span class="description"><?php echo esc_html( wp_date( get_option( 'date_format', 'Y-m-d' ), $set_at ) ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="css-tc-pill css-tc-pill-unset"><?php echo esc_html__( 'Not set', 'css-timeclock-addon' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<form class="css-tc-pin-form" data-user-id="<?php echo esc_attr( (string) (int) $user->ID ); ?>">
								<?php $pin_input_id = 'css-tc-pin-' . (int) $user->ID; ?>
								<label class="screen-reader-text" for="<?php echo esc_attr( $pin_input_id ); ?>"><?php echo esc_html__( 'New PIN', 'css-timeclock-addon' ); ?></label>
								<span class="css-tc-pin-field">
									<input id="<?php echo esc_attr( $pin_input_id ); ?>" class="css-tc-pin-input" type="password" inputmode="numeric" autocomplete="new-password" maxlength="12" pattern="[0-9]*" />
									<button type="button" class="css-tc-pin-toggle" aria-pressed="false" aria-controls="<?php echo esc_attr( $pin_input_id ); ?>" aria-label="<?php echo esc_attr__( 'Show PIN', 'css-timeclock-addon' ); ?>">
										<svg class="css-tc-pin-toggle__show" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
											<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="2" />
											<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2" />
										</svg>
										<svg class="css-tc-pin-toggle__hide" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
											<path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
											<path d="M10.6 6.2A10.4 10.4 0 0 1 12 6c6.5 0 10 6 10 6a18.2 18.2 0 0 1-3.2 3.8M6.1 6.7C3.7 8.3 2 12 2 12s3.5 6 10 6c1.2 0 2.3-.2 3.3-.6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
											<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
										</svg>
									</button>
								</span>
								<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save PIN', 'css-timeclock-addon' ); ?></button>
								<button type="button" class="button css-tc-clear-pin" <?php disabled( ! $has_pin ); ?>><?php echo esc_html__( 'Clear', 'css-timeclock-addon' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>
			<?php echo esc_html__( 'Employees suggest clock-in or clock-out corrections from My Time Clock. Approving writes the AIO-compatible shift and keeps the original times plus who suggested and who approved.', 'css-timeclock-addon' ); ?>
		</p>

		<h2><?php echo esc_html__( 'Pending', 'css-timeclock-addon' ); ?></h2>
		<div class="css-tc-correction-list" data-role="pending-list">
			<?php if ( empty( $queue['pending'] ) ) : ?>
				<p class="description css-tc-empty-queue"><?php echo esc_html__( 'No pending suggestions.', 'css-timeclock-addon' ); ?></p>
			<?php else : ?>
				<?php foreach ( $queue['pending'] as $item ) : ?>
					<?php
					$item_status = 'pending';
					include CSS_TC_ADDON_DIR . 'admin/views/correction-card.php';
					?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<h2><?php echo esc_html__( 'Recently reviewed', 'css-timeclock-addon' ); ?></h2>
		<div class="css-tc-correction-list" data-role="recent-list">
			<?php if ( empty( $queue['recent'] ) ) : ?>
				<p class="description css-tc-empty-queue"><?php echo esc_html__( 'No reviewed suggestions yet.', 'css-timeclock-addon' ); ?></p>
			<?php else : ?>
				<?php foreach ( $queue['recent'] as $item ) : ?>
					<?php
					$item_status = isset( $item['status'] ) ? $item['status'] : '';
					include CSS_TC_ADDON_DIR . 'admin/views/correction-card.php';
					?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
