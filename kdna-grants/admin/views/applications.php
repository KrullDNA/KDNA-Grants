<?php
/**
 * Applications screen.
 *
 * Variables provided by KDNA_Grants_Admin_Applications::render():
 *
 * @var array[]    $rounds       Every round, newest first.
 * @var array|null $round        The round being shown.
 * @var array[]    $applications Applications in the round.
 * @var int        $flagged      How many still have flags.
 * @var array      $labels       Flag code => label.
 * @var bool       $settings_ok  Whether the grant form is chosen in Settings.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kdna-grants-admin kdna-grants-admin--wide">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Applications', 'kdna-grants' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Grants_Admin::render_message(); ?>

	<?php if ( ! $settings_ok && current_user_can( 'manage_options' ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No grant application form is chosen yet, so new submissions are not being captured.', 'kdna-grants' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Grants_Admin::SETTINGS_PAGE ) ); ?>"><?php esc_html_e( 'Go to Settings', 'kdna-grants' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $rounds ) : ?>
		<div class="kdna-grants-card kdna-grants-card--empty">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'No rounds yet', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Applications are filed under rounds. Create the first round to open applications.', 'kdna-grants' ); ?></p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Grants_Admin::ROUNDS_PAGE ) ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Rounds', 'kdna-grants' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<!-- Round picker -->
		<form method="get" class="kdna-grants-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_Grants_Admin::APPLICATIONS_PAGE ); ?>" />
			<label for="kdna-grants-round-picker"><?php esc_html_e( 'Round', 'kdna-grants' ); ?></label>
			<select name="round" id="kdna-grants-round-picker" class="kdna-grants-autosubmit">
				<?php foreach ( $rounds as $kdna_grants_option ) : ?>
					<option value="<?php echo esc_attr( $kdna_grants_option['id'] ); ?>" <?php selected( $round ? $round['id'] : 0, $kdna_grants_option['id'] ); ?>>
						<?php
						echo esc_html(
							$kdna_grants_option['name']
							. ' (' . KDNA_Grants_Rounds::status_label( $kdna_grants_option['status'] ) . ')'
							. ( $kdna_grants_option['is_test'] ? ' [' . __( 'Test', 'kdna-grants' ) . ']' : '' )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Show', 'kdna-grants' ); ?></button></noscript>
		</form>

		<?php if ( $round ) : ?>
			<p class="kdna-grants-admin__summary">
				<?php echo KDNA_Grants_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: opening date, 2: closing date. */
						__( '%1$s to %2$s', 'kdna-grants' ),
						KDNA_Grants_Rounds::format( $round['opens_at'] ),
						KDNA_Grants_Rounds::format( $round['closes_at'] )
					)
				);
				?>
				<span class="kdna-grants-admin__counts">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of applications, 2: number eligible, 3: number flagged. */
							__( '%1$d applications: %2$d eligible, %3$d flagged', 'kdna-grants' ),
							count( $applications ),
							count( $applications ) - $flagged,
							$flagged
						)
					);
					?>
				</span>
			</p>

			<?php if ( ! $applications ) : ?>
				<div class="kdna-grants-card kdna-grants-card--empty">
					<p class="kdna-grants-card__intro"><?php esc_html_e( 'No applications in this round yet.', 'kdna-grants' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat striped kdna-grants-table kdna-grants-table--applications">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Business', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applicant', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'ABN', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Staff', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applied', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eligibility', 'kdna-grants' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $applications as $kdna_grants_app ) : ?>
							<tr>
								<td><strong><?php echo esc_html( '' !== $kdna_grants_app['business_name'] ? $kdna_grants_app['business_name'] : __( '(no business name)', 'kdna-grants' ) ); ?></strong></td>
								<td>
									<?php echo esc_html( trim( $kdna_grants_app['first_name'] . ' ' . $kdna_grants_app['last_name'] ) ); ?>
									<?php if ( $kdna_grants_app['email'] ) : ?>
										<br /><a href="mailto:<?php echo esc_attr( $kdna_grants_app['email'] ); ?>"><?php echo esc_html( $kdna_grants_app['email'] ); ?></a>
									<?php endif; ?>
								</td>
								<td class="kdna-grants-nowrap"><?php echo esc_html( KDNA_Grants_Eligibility::format_abn( $kdna_grants_app['abn'] ) ); ?></td>
								<td><?php echo esc_html( $kdna_grants_app['staff'] ); ?></td>
								<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_app['created_at'] ) ); ?></td>
								<td class="kdna-grants-eligibility">
									<?php if ( ! $kdna_grants_app['eligibility_flags'] ) : ?>
										<span class="kdna-grants-flag kdna-grants-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-grants' ); ?></span>
									<?php else : ?>
										<ul class="kdna-grants-flags">
											<?php foreach ( $kdna_grants_app['eligibility_flags'] as $kdna_grants_flag ) : ?>
												<li class="<?php echo ! empty( $kdna_grants_flag['cleared'] ) ? 'is-cleared' : ''; ?>">
													<span class="kdna-grants-flag <?php echo ! empty( $kdna_grants_flag['cleared'] ) ? 'kdna-grants-flag--cleared' : ''; ?>">
														<?php echo esc_html( isset( $labels[ $kdna_grants_flag['code'] ] ) ? $labels[ $kdna_grants_flag['code'] ] : $kdna_grants_flag['code'] ); ?>
													</span>
													<span class="kdna-grants-flag__reason"><?php echo esc_html( $kdna_grants_flag['reason'] ); ?></span>
													<?php if ( ! empty( $kdna_grants_flag['cleared'] ) ) : ?>
														<span class="kdna-grants-flag__cleared">
															<?php
															echo esc_html(
																sprintf(
																	/* translators: 1: date, 2: person's name. */
																	__( 'Marked as eligible %1$s by %2$s', 'kdna-grants' ),
																	KDNA_Grants_Rounds::format( $kdna_grants_flag['cleared_at'], false ),
																	$kdna_grants_flag['cleared_by']
																)
															);
															?>
														</span>
													<?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>

										<?php if ( ! $kdna_grants_app['is_eligible'] ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>" />
												<input type="hidden" name="application_id" value="<?php echo esc_attr( $kdna_grants_app['id'] ); ?>" />
												<?php wp_nonce_field( KDNA_Grants_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>
												<button type="submit" class="button button-small"><?php esc_html_e( 'Mark as eligible', 'kdna-grants' ); ?></button>
											</form>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
