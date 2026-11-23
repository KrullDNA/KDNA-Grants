<?php
/**
 * Claude's picks.
 *
 * Variables provided by KDNA_Grants_Admin_Picks::render():
 *
 * @var array[]    $rounds       Every round, newest first.
 * @var array|null $round        The round being shown.
 * @var array[]    $applications Every application in the round.
 * @var array      $by_id        Application ID => application.
 * @var array[]    $assessed     Applications Claude has read, most interesting first.
 * @var array      $counts       Claude status => count.
 * @var array      $labels       Flag code => label.
 * @var array|null $winner       The winning application, once decided.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_is_admin = current_user_can( 'manage_options' );
$kdna_grants_enabled  = KDNA_Grants_Claude::is_enabled();
$kdna_grants_waiting  = ( isset( $counts['queued'] ) ? $counts['queued'] : 0 ) + ( isset( $counts['running'] ) ? $counts['running'] : 0 );
$kdna_grants_failed   = isset( $counts['failed'] ) ? $counts['failed'] : 0;
$kdna_grants_overview = $round ? $round['ai_overview'] : array();
$kdna_grants_usage    = $round ? $round['ai_usage'] : array();
?>
<div class="wrap kdna-grants-admin kdna-grants-admin--wide kdna-grants-picks">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Claude\'s picks', 'kdna-grants' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Grants_Admin::render_message(); ?>

	<?php if ( ! $kdna_grants_enabled ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Claude isn\'t switched on yet. Add your Claude API key and your studio profile, and Claude will read every application when a round closes.', 'kdna-grants' ); ?>
				<?php if ( $kdna_grants_is_admin ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Grants_Admin::SETTINGS_PAGE . '#claude' ) ); ?>"><?php esc_html_e( 'Set up Claude', 'kdna-grants' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $rounds ) : ?>
		<div class="kdna-grants-card kdna-grants-card--empty">
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'No rounds yet. Claude\'s picks appear here once a round has applications.', 'kdna-grants' ); ?></p>
		</div>
	<?php else : ?>

		<form method="get" class="kdna-grants-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_Grants_Admin::PICKS_PAGE ); ?>" />
			<label for="kdna-grants-picks-round"><?php esc_html_e( 'Round', 'kdna-grants' ); ?></label>
			<select name="round" id="kdna-grants-picks-round" class="kdna-grants-autosubmit">
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

			<!-- Status and actions -->
			<div class="kdna-grants-picks__bar">
				<p>
					<?php echo KDNA_Grants_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number read, 2: number of applications. */
							__( 'Claude has read %1$d of %2$d applications.', 'kdna-grants' ),
							count( $assessed ),
							count( $applications )
						)
					);
					if ( $kdna_grants_waiting ) {
						echo ' <strong>' . esc_html(
							sprintf(
								/* translators: %d: number waiting. */
								_n( '%d is being read now.', '%d are being read now.', $kdna_grants_waiting, 'kdna-grants' ),
								$kdna_grants_waiting
							)
						) . '</strong> ' . esc_html__( 'Refresh this page in a minute or two.', 'kdna-grants' );
					} elseif ( KDNA_Grants_Claude::STATUS_QUEUED === $round['ai_status'] || KDNA_Grants_Claude::STATUS_RUNNING === $round['ai_status'] ) {
						echo ' <strong>' . esc_html__( 'Claude is writing the round overview now.', 'kdna-grants' ) . '</strong>';
					}
					if ( $kdna_grants_failed ) {
						echo ' <span class="kdna-grants-text-error">' . esc_html(
							sprintf(
								/* translators: %d: number failed. */
								_n( '%d couldn\'t be read.', '%d couldn\'t be read.', $kdna_grants_failed, 'kdna-grants' ),
								$kdna_grants_failed
							)
						) . '</span>';
					}
					?>
				</p>

				<?php if ( $kdna_grants_is_admin && $kdna_grants_enabled && $applications ) : ?>
					<div class="kdna-grants-picks__actions">
						<?php if ( count( $assessed ) < count( $applications ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Picks::QUEUE_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<input type="hidden" name="only_new" value="1" />
								<?php wp_nonce_field( KDNA_Grants_Admin_Picks::QUEUE_ACTION ); ?>
								<button type="submit" class="button button-primary"><?php echo $assessed ? esc_html__( 'Read new applications', 'kdna-grants' ) : esc_html__( 'Ask Claude to read this round', 'kdna-grants' ); ?></button>
							</form>
						<?php endif; ?>
						<?php if ( $assessed ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Picks::OVERVIEW_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<?php wp_nonce_field( KDNA_Grants_Admin_Picks::OVERVIEW_ACTION ); ?>
								<button type="submit" class="button"><?php esc_html_e( 'Refresh overview', 'kdna-grants' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="reassess">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Picks::QUEUE_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<?php wp_nonce_field( KDNA_Grants_Admin_Picks::QUEUE_ACTION ); ?>
								<button type="submit" class="button-link"><?php esc_html_e( 'Re-assess whole round', 'kdna-grants' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $kdna_grants_overview ) : ?>
				<!-- Round overview -->
				<div class="kdna-grants-panel kdna-grants-panel--choose kdna-grants-overview">
					<h2><?php esc_html_e( 'Round overview', 'kdna-grants' ); ?></h2>
					<p class="kdna-grants-overview__text"><?php echo esc_html( $kdna_grants_overview['overview'] ); ?></p>

					<?php if ( ! empty( $kdna_grants_overview['top_picks'] ) ) : ?>
						<div class="kdna-grants-finalists">
							<?php foreach ( $kdna_grants_overview['top_picks'] as $kdna_grants_rank => $kdna_grants_pick ) : ?>
								<?php
								if ( ! isset( $by_id[ $kdna_grants_pick['application_id'] ] ) ) {
									continue;
								}
								$kdna_grants_app = $by_id[ $kdna_grants_pick['application_id'] ];
								?>
								<div class="kdna-grants-finalist<?php echo 0 === $kdna_grants_rank ? ' is-top' : ''; ?>">
									<div class="kdna-grants-finalist__rank"><?php echo esc_html( $kdna_grants_rank + 1 ); ?></div>
									<h3 class="kdna-grants-finalist__name">
										<a href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $kdna_grants_app['id'] ) ); ?>"><?php echo esc_html( $kdna_grants_app['business_name'] ); ?></a>
										<?php if ( $winner && $winner['id'] === $kdna_grants_app['id'] ) : ?>
											<span class="kdna-grants-badge kdna-grants-badge--winner"><?php esc_html_e( 'Your winner', 'kdna-grants' ); ?></span>
										<?php endif; ?>
									</h3>
									<p class="kdna-grants-finalist__headline"><?php echo esc_html( $kdna_grants_pick['headline'] ); ?></p>
									<?php echo KDNA_Grants_Admin_Picks::rating( isset( $kdna_grants_app['ai_assessment']['interest_rating'] ) ? $kdna_grants_app['ai_assessment']['interest_rating'] : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
									<p><?php echo esc_html( $kdna_grants_pick['why'] ); ?></p>
									<p><?php echo KDNA_Grants_Admin_Contact::button( $kdna_grants_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $winner && $kdna_grants_overview['top_picks'] && ! in_array( $winner['id'], wp_list_pluck( $kdna_grants_overview['top_picks'], 'application_id' ), true ) ) : ?>
						<p class="kdna-grants-muted">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: business name. */
									__( 'Your winner, %s, wasn\'t in Claude\'s top picks this round. Worth a look at your studio profile if that keeps happening.', 'kdna-grants' ),
									$winner['business_name']
								)
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $kdna_grants_overview['second_look'] ) ) : ?>
						<h3><?php esc_html_e( 'Worth a second look', 'kdna-grants' ); ?></h3>
						<p class="kdna-grants-muted"><?php esc_html_e( 'These are flagged for eligibility, but Claude thinks they are strong. Check whether the flag should be cleared.', 'kdna-grants' ); ?></p>
						<ul class="kdna-grants-list">
							<?php foreach ( $kdna_grants_overview['second_look'] as $kdna_grants_pick ) : ?>
								<?php
								if ( ! isset( $by_id[ $kdna_grants_pick['application_id'] ] ) ) {
									continue;
								}
								?>
								<li>
									<a href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $kdna_grants_pick['application_id'] ) ); ?>"><strong><?php echo esc_html( $by_id[ $kdna_grants_pick['application_id'] ]['business_name'] ); ?></strong></a>:
									<?php echo esc_html( $kdna_grants_pick['why'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $assessed ) : ?>
				<!-- Every application Claude has read -->
				<table class="widefat striped kdna-grants-table kdna-grants-table--picks">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Business', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Interesting brief', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Claude\'s summary', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eligibility', 'kdna-grants' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'kdna-grants' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $assessed as $kdna_grants_app ) : ?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $kdna_grants_app['id'] ) ); ?>"><?php echo esc_html( $kdna_grants_app['business_name'] ); ?></a></strong>
									<?php if ( 'winner' === $kdna_grants_app['outcome'] ) : ?>
										<span class="kdna-grants-badge kdna-grants-badge--winner"><?php esc_html_e( 'Winner', 'kdna-grants' ); ?></span>
									<?php endif; ?>
									<span class="kdna-grants-table__sub"><?php echo esc_html( $kdna_grants_app['industry'] ); ?></span>
								</td>
								<td class="kdna-grants-nowrap"><?php echo KDNA_Grants_Admin_Picks::rating( $kdna_grants_app['ai_assessment']['interest_rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></td>
								<td>
									<?php echo esc_html( $kdna_grants_app['ai_assessment']['summary'] ); ?>
									<span class="kdna-grants-table__sub"><?php echo esc_html( $kdna_grants_app['ai_assessment']['interest_reason'] ); ?></span>
									<?php if ( ! empty( $kdna_grants_app['ai_assessment']['scope_notes'] ) ) : ?>
										<span class="kdna-grants-scope"><strong><?php esc_html_e( 'Outside the grant:', 'kdna-grants' ); ?></strong> <?php echo esc_html( implode( '; ', $kdna_grants_app['ai_assessment']['scope_notes'] ) ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $kdna_grants_app['is_eligible'] ) : ?>
										<span class="kdna-grants-flag kdna-grants-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-grants' ); ?></span>
									<?php else : ?>
										<?php foreach ( $kdna_grants_app['eligibility_flags'] as $kdna_grants_flag ) : ?>
											<?php if ( empty( $kdna_grants_flag['cleared'] ) ) : ?>
												<span class="kdna-grants-flag" title="<?php echo esc_attr( $kdna_grants_flag['reason'] ); ?>"><?php echo esc_html( isset( $labels[ $kdna_grants_flag['code'] ] ) ? $labels[ $kdna_grants_flag['code'] ] : $kdna_grants_flag['code'] ); ?></span>
											<?php endif; ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td><?php echo KDNA_Grants_Admin_Contact::button( $kdna_grants_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php elseif ( $applications ) : ?>
				<div class="kdna-grants-card kdna-grants-card--empty">
					<p class="kdna-grants-card__intro"><?php esc_html_e( 'Claude hasn\'t read this round yet. It reads every application automatically when a round closes, or you can ask it to start now.', 'kdna-grants' ); ?></p>
				</div>
			<?php else : ?>
				<div class="kdna-grants-card kdna-grants-card--empty">
					<p class="kdna-grants-card__intro"><?php esc_html_e( 'No applications in this round yet.', 'kdna-grants' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $kdna_grants_usage ) : ?>
				<p class="kdna-grants-admin__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: date, 2: number of requests, 3: cost in US dollars. */
							__( 'Overview written %1$s. %2$d Claude requests for this round, about US$%3$s.', 'kdna-grants' ),
							$round['ai_overview_at'] ? KDNA_Grants_Rounds::format( $round['ai_overview_at'] ) : __( 'not yet', 'kdna-grants' ),
							isset( $kdna_grants_usage['requests'] ) ? $kdna_grants_usage['requests'] : 0,
							number_format_i18n( isset( $kdna_grants_usage['cost'] ) ? $kdna_grants_usage['cost'] : 0, 2 )
						)
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
