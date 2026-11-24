<?php
/**
 * Applications list.
 *
 * Variables provided by KDNA_Launchpad_Admin_Applications::render_list():
 *
 * @var array[]    $rounds           Every round, newest first.
 * @var array|null $round            The round being shown.
 * @var array[]    $all_applications Every application in the round.
 * @var array[]    $applications     Applications after the filter and sorting.
 * @var string     $filter           Current filter.
 * @var array      $filter_counts    Filter => count.
 * @var string     $orderby          'total' when sorted by score.
 * @var string     $order            'asc' or 'desc'.
 * @var array      $criteria         Criteria for this round.
 * @var array|null $decision         From KDNA_Launchpad_Judging::decision_status().
 * @var array|null $winner           Winning application, once decided.
 * @var array      $labels           Flag code => label.
 * @var bool       $settings_ok      Whether the Launchpad form is chosen in Settings.
 * @var int        $max_total        Highest possible total.
 * @var string     $base_url         This screen for this round.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_is_admin = current_user_can( 'manage_options' );
?>
<div class="wrap kdna-launchpad-admin kdna-launchpad-admin--wide">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Applications', 'kdna-launchpad' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Launchpad_Admin::render_message(); ?>

	<?php if ( ! $settings_ok && $kdna_launchpad_is_admin ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No Launchpad application form is chosen yet, so new submissions are not being captured.', 'kdna-launchpad' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Launchpad_Admin::SETTINGS_PAGE ) ); ?>"><?php esc_html_e( 'Go to Settings', 'kdna-launchpad' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $rounds ) : ?>
		<div class="kdna-launchpad-card kdna-launchpad-card--empty">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'No rounds yet', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Applications are filed under rounds. Create the first round to open applications.', 'kdna-launchpad' ); ?></p>
			<?php if ( $kdna_launchpad_is_admin ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Launchpad_Admin::ROUNDS_PAGE ) ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Rounds', 'kdna-launchpad' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<!-- Round picker -->
		<form method="get" class="kdna-launchpad-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_Launchpad_Admin::APPLICATIONS_PAGE ); ?>" />
			<label for="kdna-launchpad-round-picker"><?php esc_html_e( 'Round', 'kdna-launchpad' ); ?></label>
			<select name="round" id="kdna-launchpad-round-picker" class="kdna-launchpad-autosubmit">
				<?php foreach ( $rounds as $kdna_launchpad_option ) : ?>
					<option value="<?php echo esc_attr( $kdna_launchpad_option['id'] ); ?>" <?php selected( $round ? $round['id'] : 0, $kdna_launchpad_option['id'] ); ?>>
						<?php
						echo esc_html(
							$kdna_launchpad_option['name']
							. ' (' . KDNA_Launchpad_Rounds::status_label( $kdna_launchpad_option['status'] ) . ')'
							. ( $kdna_launchpad_option['is_test'] ? ' [' . __( 'Test', 'kdna-launchpad' ) . ']' : '' )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Show', 'kdna-launchpad' ); ?></button></noscript>
		</form>

		<?php if ( $round ) : ?>
			<p class="kdna-launchpad-admin__summary">
				<?php echo KDNA_Launchpad_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: opening date, 2: closing date. */
						__( '%1$s to %2$s', 'kdna-launchpad' ),
						KDNA_Launchpad_Rounds::format( $round['opens_at'] ),
						KDNA_Launchpad_Rounds::format( $round['closes_at'] )
					)
				);
				?>
				<?php if ( $all_applications && $kdna_launchpad_is_admin ) : ?>
					<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Rounds::export_url( $round['id'] ) ); ?>" class="button button-small kdna-launchpad-admin__export"><?php esc_html_e( 'Export CSV', 'kdna-launchpad' ); ?></a>
				<?php endif; ?>
				<span class="kdna-launchpad-admin__counts">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number eligible, 2: number scored. */
							__( '%1$d eligible, %2$d of them fully scored', 'kdna-launchpad' ),
							$decision['eligible'],
							$decision['scored']
						)
					);
					?>
				</span>
			</p>

			<?php include KDNA_LAUNCHPAD_PATH . 'admin/views/decision-panel.php'; ?>

			<?php if ( ! $all_applications ) : ?>
				<div class="kdna-launchpad-card kdna-launchpad-card--empty">
					<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'No applications in this round yet.', 'kdna-launchpad' ); ?></p>
				</div>
			<?php else : ?>

				<!-- Filters -->
				<ul class="subsubsub kdna-launchpad-filters">
					<?php
					$kdna_launchpad_links = array();
					foreach ( KDNA_Launchpad_Admin_Applications::filters() as $kdna_launchpad_key => $kdna_launchpad_label ) {
						$kdna_launchpad_url     = '' === $kdna_launchpad_key ? $base_url : add_query_arg( 'filter', $kdna_launchpad_key, $base_url );
						$kdna_launchpad_links[] = sprintf(
							'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
							esc_url( $kdna_launchpad_url ),
							$filter === $kdna_launchpad_key ? ' class="current" aria-current="page"' : '',
							esc_html( $kdna_launchpad_label ),
							(int) $filter_counts[ $kdna_launchpad_key ]
						);
					}
					echo implode( ' |</li>', $kdna_launchpad_links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					?>
				</ul>

				<?php
				$kdna_launchpad_sort_order = ( 'total' === $orderby && 'desc' === $order ) ? 'asc' : 'desc';
				$kdna_launchpad_sort_url   = add_query_arg(
					array(
						'filter'  => $filter,
						'orderby' => 'total',
						'order'   => $kdna_launchpad_sort_order,
					),
					$base_url
				);
				?>

				<table class="widefat striped kdna-launchpad-table kdna-launchpad-table--applications">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Business', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Industry', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Staff', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applied', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eligibility', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Scored', 'kdna-launchpad' ); ?></th>
							<th scope="col" class="kdna-launchpad-table__num <?php echo 'total' === $orderby ? 'sorted ' . esc_attr( $order ) : 'sortable desc'; ?>">
								<a href="<?php echo esc_url( $kdna_launchpad_sort_url ); ?>">
									<span><?php esc_html_e( 'Total', 'kdna-launchpad' ); ?></span>
									<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>
								</a>
							</th>
							<th scope="col"><?php esc_html_e( 'Shortlisted', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Interest', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'kdna-launchpad' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $applications ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'No applications match this filter.', 'kdna-launchpad' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $applications as $kdna_launchpad_app ) : ?>
							<?php $kdna_launchpad_scored = KDNA_Launchpad_Judging::is_scored( $kdna_launchpad_app, $criteria ); ?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_app['id'] ) ); ?>"><?php echo esc_html( '' !== $kdna_launchpad_app['business_name'] ? $kdna_launchpad_app['business_name'] : __( '(no business name)', 'kdna-launchpad' ) ); ?></a></strong>
									<?php if ( 'winner' === $kdna_launchpad_app['outcome'] ) : ?>
										<span class="kdna-launchpad-badge kdna-launchpad-badge--winner"><?php esc_html_e( 'Winner', 'kdna-launchpad' ); ?></span>
									<?php endif; ?>
									<span class="kdna-launchpad-table__sub"><?php echo esc_html( trim( $kdna_launchpad_app['first_name'] . ' ' . $kdna_launchpad_app['last_name'] ) ); ?></span>
									<span class="kdna-launchpad-table__sub"><?php echo KDNA_Launchpad_Admin_Contact::button( $kdna_launchpad_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></span>
									<?php
									$kdna_launchpad_offer_note = $kdna_launchpad_is_admin ? KDNA_Launchpad_Admin_Offers::summary( $kdna_launchpad_app ) : '';
									if ( $kdna_launchpad_offer_note ) {
										echo '<span class="kdna-launchpad-table__sub">' . $kdna_launchpad_offer_note . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
									}
									?>
								</td>
								<td><?php echo esc_html( $kdna_launchpad_app['industry'] ); ?></td>
								<td><?php echo esc_html( $kdna_launchpad_app['staff'] ); ?></td>
								<td><?php echo esc_html( KDNA_Launchpad_Rounds::format( $kdna_launchpad_app['created_at'], false ) ); ?></td>
								<td class="kdna-launchpad-eligibility">
									<?php if ( $kdna_launchpad_app['is_eligible'] ) : ?>
										<span class="kdna-launchpad-flag kdna-launchpad-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-launchpad' ); ?></span>
									<?php else : ?>
										<?php foreach ( $kdna_launchpad_app['eligibility_flags'] as $kdna_launchpad_flag ) : ?>
											<?php
											if ( ! empty( $kdna_launchpad_flag['cleared'] ) ) {
												continue;
											}
											?>
											<span class="kdna-launchpad-flag" title="<?php echo esc_attr( $kdna_launchpad_flag['reason'] ); ?>">
												<?php echo esc_html( isset( $labels[ $kdna_launchpad_flag['code'] ] ) ? $labels[ $kdna_launchpad_flag['code'] ] : $kdna_launchpad_flag['code'] ); ?>
											</span>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td><?php echo $kdna_launchpad_scored ? esc_html__( 'Yes', 'kdna-launchpad' ) : '<span class="kdna-launchpad-muted">' . esc_html__( 'No', 'kdna-launchpad' ) . '</span>'; ?></td>
								<td class="kdna-launchpad-table__num">
									<?php if ( $kdna_launchpad_app['scores'] ) : ?>
										<strong><?php echo esc_html( $kdna_launchpad_app['total_score'] ); ?></strong><span class="kdna-launchpad-muted"> / <?php echo esc_html( $max_total ); ?></span>
									<?php else : ?>
										<span class="kdna-launchpad-muted" aria-hidden="true">&ndash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo $kdna_launchpad_app['shortlisted'] ? '<span class="dashicons dashicons-star-filled kdna-launchpad-star" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'kdna-launchpad' ) . '</span>' : ''; ?></td>
								<td class="kdna-launchpad-nowrap">
									<?php
									$kdna_launchpad_rating = ! empty( $kdna_launchpad_app['ai_assessment']['interest_rating'] )
										? KDNA_Launchpad_Admin_Picks::rating( $kdna_launchpad_app['ai_assessment']['interest_rating'] )
										: '<span class="kdna-launchpad-muted" aria-hidden="true">&ndash;</span>';
									echo $kdna_launchpad_rating; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
									?>
								</td>
								<td class="kdna-launchpad-nowrap">
									<?php include KDNA_LAUNCHPAD_PATH . 'admin/views/email-status.php'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
