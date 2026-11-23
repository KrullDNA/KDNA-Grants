<?php
/**
 * Applications list.
 *
 * Variables provided by KDNA_Grants_Admin_Applications::render_list():
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
 * @var array|null $decision         From KDNA_Grants_Judging::decision_status().
 * @var array|null $winner           Winning application, once decided.
 * @var array      $labels           Flag code => label.
 * @var bool       $settings_ok      Whether the grant form is chosen in Settings.
 * @var int        $max_total        Highest possible total.
 * @var string     $base_url         This screen for this round.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_is_admin = current_user_can( 'manage_options' );
?>
<div class="wrap kdna-grants-admin kdna-grants-admin--wide">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Applications', 'kdna-grants' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Grants_Admin::render_message(); ?>

	<?php if ( ! $settings_ok && $kdna_grants_is_admin ) : ?>
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
			<?php if ( $kdna_grants_is_admin ) : ?>
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
				<?php if ( $all_applications && $kdna_grants_is_admin ) : ?>
					<a href="<?php echo esc_url( KDNA_Grants_Admin_Rounds::export_url( $round['id'] ) ); ?>" class="button button-small kdna-grants-admin__export"><?php esc_html_e( 'Export CSV', 'kdna-grants' ); ?></a>
				<?php endif; ?>
				<span class="kdna-grants-admin__counts">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number eligible, 2: number scored. */
							__( '%1$d eligible, %2$d of them fully scored', 'kdna-grants' ),
							$decision['eligible'],
							$decision['scored']
						)
					);
					?>
				</span>
			</p>

			<?php include KDNA_GRANTS_PATH . 'admin/views/decision-panel.php'; ?>

			<?php if ( ! $all_applications ) : ?>
				<div class="kdna-grants-card kdna-grants-card--empty">
					<p class="kdna-grants-card__intro"><?php esc_html_e( 'No applications in this round yet.', 'kdna-grants' ); ?></p>
				</div>
			<?php else : ?>

				<!-- Filters -->
				<ul class="subsubsub kdna-grants-filters">
					<?php
					$kdna_grants_links = array();
					foreach ( KDNA_Grants_Admin_Applications::filters() as $kdna_grants_key => $kdna_grants_label ) {
						$kdna_grants_url     = '' === $kdna_grants_key ? $base_url : add_query_arg( 'filter', $kdna_grants_key, $base_url );
						$kdna_grants_links[] = sprintf(
							'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
							esc_url( $kdna_grants_url ),
							$filter === $kdna_grants_key ? ' class="current" aria-current="page"' : '',
							esc_html( $kdna_grants_label ),
							(int) $filter_counts[ $kdna_grants_key ]
						);
					}
					echo implode( ' |</li>', $kdna_grants_links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					?>
				</ul>

				<?php
				$kdna_grants_sort_order = ( 'total' === $orderby && 'desc' === $order ) ? 'asc' : 'desc';
				$kdna_grants_sort_url   = add_query_arg(
					array(
						'filter'  => $filter,
						'orderby' => 'total',
						'order'   => $kdna_grants_sort_order,
					),
					$base_url
				);
				?>

				<table class="widefat striped kdna-grants-table kdna-grants-table--applications">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Business', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Industry', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Staff', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applied', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eligibility', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Scored', 'kdna-grants' ); ?></th>
							<th scope="col" class="kdna-grants-table__num <?php echo 'total' === $orderby ? 'sorted ' . esc_attr( $order ) : 'sortable desc'; ?>">
								<a href="<?php echo esc_url( $kdna_grants_sort_url ); ?>">
									<span><?php esc_html_e( 'Total', 'kdna-grants' ); ?></span>
									<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>
								</a>
							</th>
							<th scope="col"><?php esc_html_e( 'Shortlisted', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Interest', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'kdna-grants' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $applications ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'No applications match this filter.', 'kdna-grants' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $applications as $kdna_grants_app ) : ?>
							<?php $kdna_grants_scored = KDNA_Grants_Judging::is_scored( $kdna_grants_app, $criteria ); ?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $kdna_grants_app['id'] ) ); ?>"><?php echo esc_html( '' !== $kdna_grants_app['business_name'] ? $kdna_grants_app['business_name'] : __( '(no business name)', 'kdna-grants' ) ); ?></a></strong>
									<?php if ( 'winner' === $kdna_grants_app['outcome'] ) : ?>
										<span class="kdna-grants-badge kdna-grants-badge--winner"><?php esc_html_e( 'Winner', 'kdna-grants' ); ?></span>
									<?php endif; ?>
									<span class="kdna-grants-table__sub"><?php echo esc_html( trim( $kdna_grants_app['first_name'] . ' ' . $kdna_grants_app['last_name'] ) ); ?></span>
									<span class="kdna-grants-table__sub"><?php echo KDNA_Grants_Admin_Contact::button( $kdna_grants_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></span>
									<?php
									$kdna_grants_offer_note = $kdna_grants_is_admin ? KDNA_Grants_Admin_Offers::summary( $kdna_grants_app ) : '';
									if ( $kdna_grants_offer_note ) {
										echo '<span class="kdna-grants-table__sub">' . $kdna_grants_offer_note . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
									}
									?>
								</td>
								<td><?php echo esc_html( $kdna_grants_app['industry'] ); ?></td>
								<td><?php echo esc_html( $kdna_grants_app['staff'] ); ?></td>
								<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_app['created_at'], false ) ); ?></td>
								<td class="kdna-grants-eligibility">
									<?php if ( $kdna_grants_app['is_eligible'] ) : ?>
										<span class="kdna-grants-flag kdna-grants-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-grants' ); ?></span>
									<?php else : ?>
										<?php foreach ( $kdna_grants_app['eligibility_flags'] as $kdna_grants_flag ) : ?>
											<?php
											if ( ! empty( $kdna_grants_flag['cleared'] ) ) {
												continue;
											}
											?>
											<span class="kdna-grants-flag" title="<?php echo esc_attr( $kdna_grants_flag['reason'] ); ?>">
												<?php echo esc_html( isset( $labels[ $kdna_grants_flag['code'] ] ) ? $labels[ $kdna_grants_flag['code'] ] : $kdna_grants_flag['code'] ); ?>
											</span>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td><?php echo $kdna_grants_scored ? esc_html__( 'Yes', 'kdna-grants' ) : '<span class="kdna-grants-muted">' . esc_html__( 'No', 'kdna-grants' ) . '</span>'; ?></td>
								<td class="kdna-grants-table__num">
									<?php if ( $kdna_grants_app['scores'] ) : ?>
										<strong><?php echo esc_html( $kdna_grants_app['total_score'] ); ?></strong><span class="kdna-grants-muted"> / <?php echo esc_html( $max_total ); ?></span>
									<?php else : ?>
										<span class="kdna-grants-muted" aria-hidden="true">&ndash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo $kdna_grants_app['shortlisted'] ? '<span class="dashicons dashicons-star-filled kdna-grants-star" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'kdna-grants' ) . '</span>' : ''; ?></td>
								<td class="kdna-grants-nowrap">
									<?php
									$kdna_grants_rating = ! empty( $kdna_grants_app['ai_assessment']['interest_rating'] )
										? KDNA_Grants_Admin_Picks::rating( $kdna_grants_app['ai_assessment']['interest_rating'] )
										: '<span class="kdna-grants-muted" aria-hidden="true">&ndash;</span>';
									echo $kdna_grants_rating; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
									?>
								</td>
								<td class="kdna-grants-nowrap">
									<?php include KDNA_GRANTS_PATH . 'admin/views/email-status.php'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
