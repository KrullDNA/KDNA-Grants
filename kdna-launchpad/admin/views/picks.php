<?php
/**
 * Claude's picks.
 *
 * Variables provided by KDNA_Launchpad_Admin_Picks::render():
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
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_is_admin = current_user_can( 'manage_options' );
$kdna_launchpad_enabled  = KDNA_Launchpad_Claude::is_enabled();
$kdna_launchpad_waiting  = ( isset( $counts['queued'] ) ? $counts['queued'] : 0 ) + ( isset( $counts['running'] ) ? $counts['running'] : 0 );
$kdna_launchpad_failed   = isset( $counts['failed'] ) ? $counts['failed'] : 0;
$kdna_launchpad_overview = $round ? $round['ai_overview'] : array();
$kdna_launchpad_usage    = $round ? $round['ai_usage'] : array();
?>
<div class="wrap kdna-launchpad-admin kdna-launchpad-admin--wide kdna-launchpad-picks">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Claude\'s picks', 'kdna-launchpad' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Launchpad_Admin::render_message(); ?>

	<?php if ( ! $kdna_launchpad_enabled ) : ?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'Claude isn\'t switched on yet. Add your Claude API key and your studio profile, and Claude will read every application when a round closes.', 'kdna-launchpad' ); ?>
				<?php if ( $kdna_launchpad_is_admin ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Launchpad_Admin::SETTINGS_PAGE . '#claude' ) ); ?>"><?php esc_html_e( 'Set up Claude', 'kdna-launchpad' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $rounds ) : ?>
		<div class="kdna-launchpad-card kdna-launchpad-card--empty">
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'No rounds yet. Claude\'s picks appear here once a round has applications.', 'kdna-launchpad' ); ?></p>
		</div>
	<?php else : ?>

		<form method="get" class="kdna-launchpad-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( KDNA_Launchpad_Admin::PICKS_PAGE ); ?>" />
			<label for="kdna-launchpad-picks-round"><?php esc_html_e( 'Round', 'kdna-launchpad' ); ?></label>
			<select name="round" id="kdna-launchpad-picks-round" class="kdna-launchpad-autosubmit">
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

			<!-- Status and actions -->
			<div class="kdna-launchpad-picks__bar">
				<p>
					<?php echo KDNA_Launchpad_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number read, 2: number of applications. */
							__( 'Claude has read %1$d of %2$d applications.', 'kdna-launchpad' ),
							count( $assessed ),
							count( $applications )
						)
					);
					if ( $kdna_launchpad_waiting ) {
						echo ' <strong>' . esc_html(
							sprintf(
								/* translators: %d: number waiting. */
								_n( '%d is being read now.', '%d are being read now.', $kdna_launchpad_waiting, 'kdna-launchpad' ),
								$kdna_launchpad_waiting
							)
						) . '</strong> ' . esc_html__( 'Refresh this page in a minute or two.', 'kdna-launchpad' );
					} elseif ( KDNA_Launchpad_Claude::STATUS_QUEUED === $round['ai_status'] || KDNA_Launchpad_Claude::STATUS_RUNNING === $round['ai_status'] ) {
						echo ' <strong>' . esc_html__( 'Claude is writing the round overview now.', 'kdna-launchpad' ) . '</strong>';
					}
					if ( $kdna_launchpad_failed ) {
						echo ' <span class="kdna-launchpad-text-error">' . esc_html(
							sprintf(
								/* translators: %d: number failed. */
								_n( '%d couldn\'t be read.', '%d couldn\'t be read.', $kdna_launchpad_failed, 'kdna-launchpad' ),
								$kdna_launchpad_failed
							)
						) . '</span>';
					}
					?>
				</p>

				<?php if ( $kdna_launchpad_is_admin && $kdna_launchpad_enabled && $applications ) : ?>
					<div class="kdna-launchpad-picks__actions">
						<?php if ( count( $assessed ) < count( $applications ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Picks::QUEUE_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<input type="hidden" name="only_new" value="1" />
								<?php wp_nonce_field( KDNA_Launchpad_Admin_Picks::QUEUE_ACTION ); ?>
								<button type="submit" class="button button-primary"><?php echo $assessed ? esc_html__( 'Read new applications', 'kdna-launchpad' ) : esc_html__( 'Ask Claude to read this round', 'kdna-launchpad' ); ?></button>
							</form>
						<?php endif; ?>
						<?php if ( $assessed ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Picks::OVERVIEW_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<?php wp_nonce_field( KDNA_Launchpad_Admin_Picks::OVERVIEW_ACTION ); ?>
								<button type="submit" class="button"><?php esc_html_e( 'Refresh overview', 'kdna-launchpad' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="reassess">
								<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Picks::QUEUE_ACTION ); ?>" />
								<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
								<?php wp_nonce_field( KDNA_Launchpad_Admin_Picks::QUEUE_ACTION ); ?>
								<button type="submit" class="button-link"><?php esc_html_e( 'Re-assess whole round', 'kdna-launchpad' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $kdna_launchpad_overview ) : ?>
				<!-- Round overview -->
				<div class="kdna-launchpad-panel kdna-launchpad-panel--choose kdna-launchpad-overview">
					<h2><?php esc_html_e( 'Round overview', 'kdna-launchpad' ); ?></h2>
					<p class="kdna-launchpad-overview__text"><?php echo esc_html( $kdna_launchpad_overview['overview'] ); ?></p>

					<?php if ( ! empty( $kdna_launchpad_overview['top_picks'] ) ) : ?>
						<div class="kdna-launchpad-finalists">
							<?php foreach ( $kdna_launchpad_overview['top_picks'] as $kdna_launchpad_rank => $kdna_launchpad_pick ) : ?>
								<?php
								if ( ! isset( $by_id[ $kdna_launchpad_pick['application_id'] ] ) ) {
									continue;
								}
								$kdna_launchpad_app = $by_id[ $kdna_launchpad_pick['application_id'] ];
								?>
								<div class="kdna-launchpad-finalist<?php echo 0 === $kdna_launchpad_rank ? ' is-top' : ''; ?>">
									<div class="kdna-launchpad-finalist__rank"><?php echo esc_html( $kdna_launchpad_rank + 1 ); ?></div>
									<h3 class="kdna-launchpad-finalist__name">
										<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_app['id'] ) ); ?>"><?php echo esc_html( $kdna_launchpad_app['display_name'] ); ?></a>
										<?php if ( $winner && $winner['id'] === $kdna_launchpad_app['id'] ) : ?>
											<span class="kdna-launchpad-badge kdna-launchpad-badge--winner"><?php esc_html_e( 'Your winner', 'kdna-launchpad' ); ?></span>
										<?php endif; ?>
									</h3>
									<p class="kdna-launchpad-finalist__headline"><?php echo esc_html( $kdna_launchpad_pick['headline'] ); ?></p>
									<?php echo KDNA_Launchpad_Admin_Picks::rating( isset( $kdna_launchpad_app['ai_assessment']['interest_rating'] ) ? $kdna_launchpad_app['ai_assessment']['interest_rating'] : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
									<p><?php echo esc_html( $kdna_launchpad_pick['why'] ); ?></p>
									<p><?php echo KDNA_Launchpad_Admin_Contact::button( $kdna_launchpad_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $winner && $kdna_launchpad_overview['top_picks'] && ! in_array( $winner['id'], wp_list_pluck( $kdna_launchpad_overview['top_picks'], 'application_id' ), true ) ) : ?>
						<p class="kdna-launchpad-muted">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: business name. */
									__( 'Your winner, %s, wasn\'t in Claude\'s top picks this round. Worth a look at your studio profile if that keeps happening.', 'kdna-launchpad' ),
									$winner['display_name']
								)
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $kdna_launchpad_overview['second_look'] ) ) : ?>
						<h3><?php esc_html_e( 'Worth a second look', 'kdna-launchpad' ); ?></h3>
						<p class="kdna-launchpad-muted"><?php esc_html_e( 'These are flagged for eligibility, but Claude thinks they are strong. Check whether the flag should be cleared.', 'kdna-launchpad' ); ?></p>
						<ul class="kdna-launchpad-list">
							<?php foreach ( $kdna_launchpad_overview['second_look'] as $kdna_launchpad_pick ) : ?>
								<?php
								if ( ! isset( $by_id[ $kdna_launchpad_pick['application_id'] ] ) ) {
									continue;
								}
								?>
								<li>
									<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_pick['application_id'] ) ); ?>"><strong><?php echo esc_html( $by_id[ $kdna_launchpad_pick['application_id'] ]['business_name'] ); ?></strong></a>:
									<?php echo esc_html( $kdna_launchpad_pick['why'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $assessed ) : ?>
				<!-- Every application Claude has read -->
				<table class="widefat striped kdna-launchpad-table kdna-launchpad-table--picks">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Business', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Interesting brief', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Claude\'s summary', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Eligibility', 'kdna-launchpad' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'kdna-launchpad' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $assessed as $kdna_launchpad_app ) : ?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_app['id'] ) ); ?>"><?php echo esc_html( $kdna_launchpad_app['display_name'] ); ?></a></strong>
									<?php if ( 'winner' === $kdna_launchpad_app['outcome'] ) : ?>
										<span class="kdna-launchpad-badge kdna-launchpad-badge--winner"><?php esc_html_e( 'Winner', 'kdna-launchpad' ); ?></span>
									<?php endif; ?>
									<span class="kdna-launchpad-table__sub"><?php echo esc_html( $kdna_launchpad_app['industry'] ); ?></span>
								</td>
								<td class="kdna-launchpad-nowrap"><?php echo KDNA_Launchpad_Admin_Picks::rating( $kdna_launchpad_app['ai_assessment']['interest_rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></td>
								<td>
									<?php echo esc_html( $kdna_launchpad_app['ai_assessment']['summary'] ); ?>
									<span class="kdna-launchpad-table__sub"><?php echo esc_html( $kdna_launchpad_app['ai_assessment']['interest_reason'] ); ?></span>
									<?php if ( ! empty( $kdna_launchpad_app['ai_assessment']['scope_notes'] ) ) : ?>
										<span class="kdna-launchpad-scope"><strong><?php esc_html_e( 'Outside Launchpad:', 'kdna-launchpad' ); ?></strong> <?php echo esc_html( implode( '; ', $kdna_launchpad_app['ai_assessment']['scope_notes'] ) ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $kdna_launchpad_app['is_eligible'] ) : ?>
										<span class="kdna-launchpad-flag kdna-launchpad-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-launchpad' ); ?></span>
									<?php else : ?>
										<?php foreach ( $kdna_launchpad_app['eligibility_flags'] as $kdna_launchpad_flag ) : ?>
											<?php if ( empty( $kdna_launchpad_flag['cleared'] ) ) : ?>
												<span class="kdna-launchpad-flag" title="<?php echo esc_attr( $kdna_launchpad_flag['reason'] ); ?>"><?php echo esc_html( isset( $labels[ $kdna_launchpad_flag['code'] ] ) ? $labels[ $kdna_launchpad_flag['code'] ] : $kdna_launchpad_flag['code'] ); ?></span>
											<?php endif; ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
								<td><?php echo KDNA_Launchpad_Admin_Contact::button( $kdna_launchpad_app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php elseif ( $applications ) : ?>
				<div class="kdna-launchpad-card kdna-launchpad-card--empty">
					<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Claude hasn\'t read this round yet. It reads every application automatically when a round closes, or you can ask it to start now.', 'kdna-launchpad' ); ?></p>
				</div>
			<?php else : ?>
				<div class="kdna-launchpad-card kdna-launchpad-card--empty">
					<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'No applications in this round yet.', 'kdna-launchpad' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $kdna_launchpad_usage ) : ?>
				<p class="kdna-launchpad-admin__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: date, 2: number of requests, 3: cost in US dollars. */
							__( 'Overview written %1$s. %2$d Claude requests for this round, about US$%3$s.', 'kdna-launchpad' ),
							$round['ai_overview_at'] ? KDNA_Launchpad_Rounds::format( $round['ai_overview_at'] ) : __( 'not yet', 'kdna-launchpad' ),
							isset( $kdna_launchpad_usage['requests'] ) ? $kdna_launchpad_usage['requests'] : 0,
							number_format_i18n( isset( $kdna_launchpad_usage['cost'] ) ? $kdna_launchpad_usage['cost'] : 0, 2 )
						)
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
