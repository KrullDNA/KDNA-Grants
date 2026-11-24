<?php
/**
 * The decision panel at the top of the Applications list: progress, the Choose winner
 * panel with its confirmation box, or the decision once made.
 *
 * Uses the variables of admin/views/applications.php.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $all_applications ) {
	return;
}

$kdna_launchpad_state = $decision['state'];
?>

<?php if ( 'open' === $kdna_launchpad_state ) : ?>
	<div class="kdna-launchpad-panel kdna-launchpad-panel--info">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: closing date and time. */
					__( 'This round is still taking applications. You can score as they come in. The winner can be chosen once it closes on %s.', 'kdna-launchpad' ),
					KDNA_Launchpad_Rounds::format( $round['closes_at'] )
				)
			);
			?>
		</p>
	</div>

<?php elseif ( 'no_eligible' === $kdna_launchpad_state ) : ?>
	<div class="kdna-launchpad-panel kdna-launchpad-panel--info">
		<p><?php esc_html_e( 'There are no eligible applications to choose from. Check the flagged applications and mark any that should go through as eligible.', 'kdna-launchpad' ); ?></p>
	</div>

<?php elseif ( 'scoring' === $kdna_launchpad_state ) : ?>
	<div class="kdna-launchpad-panel kdna-launchpad-panel--info">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: number scored, 2: number eligible. */
					__( '%1$d of %2$d eligible applications are fully scored. The Choose winner panel appears here once every eligible application has a score.', 'kdna-launchpad' ),
					$decision['scored'],
					$decision['eligible']
				)
			);
			?>
			<a href="<?php echo esc_url( add_query_arg( 'filter', 'unscored', $base_url ) ); ?>"><?php esc_html_e( 'Show unscored', 'kdna-launchpad' ); ?></a>
		</p>
		<div class="kdna-launchpad-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $decision['eligible'] ); ?>" aria-valuenow="<?php echo esc_attr( $decision['scored'] ); ?>">
			<span style="width: <?php echo esc_attr( round( 100 * $decision['scored'] / max( 1, $decision['eligible'] ) ) ); ?>%;"></span>
		</div>
	</div>

<?php elseif ( 'ready' === $kdna_launchpad_state ) : ?>
	<?php $kdna_launchpad_top = array_slice( $decision['ranked'], 0, KDNA_Launchpad_Judging::SHORTLIST_SIZE ); ?>
	<div class="kdna-launchpad-panel kdna-launchpad-panel--choose">
		<h2><?php esc_html_e( 'Choose winner', 'kdna-launchpad' ); ?></h2>
		<p class="kdna-launchpad-panel__intro">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: tie-break criterion. */
					__( 'Every eligible application is scored. These are the top 3, highest total first. Equal totals are separated by %s.', 'kdna-launchpad' ),
					KDNA_Launchpad_Judging::criterion_label( $criteria, $criteria['tiebreak'] )
				)
			);
			?>
		</p>

		<div class="kdna-launchpad-finalists">
			<?php foreach ( $kdna_launchpad_top as $kdna_launchpad_rank => $kdna_launchpad_app ) : ?>
				<div class="kdna-launchpad-finalist<?php echo 0 === $kdna_launchpad_rank ? ' is-top' : ''; ?>">
					<div class="kdna-launchpad-finalist__rank"><?php echo esc_html( $kdna_launchpad_rank + 1 ); ?></div>
					<h3 class="kdna-launchpad-finalist__name">
						<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_app['id'] ) ); ?>"><?php echo esc_html( $kdna_launchpad_app['display_name'] ); ?></a>
					</h3>
					<p class="kdna-launchpad-finalist__total">
						<strong><?php echo esc_html( KDNA_Launchpad_Judging::total( $kdna_launchpad_app['scores'], $criteria ) ); ?></strong> / <?php echo esc_html( $max_total ); ?>
					</p>
					<dl class="kdna-launchpad-finalist__scores">
						<?php foreach ( $criteria['items'] as $kdna_launchpad_item ) : ?>
							<div class="<?php echo $kdna_launchpad_item['key'] === $criteria['tiebreak'] ? 'is-tiebreak' : ''; ?>">
								<dt><?php echo esc_html( $kdna_launchpad_item['label'] ); ?></dt>
								<dd><?php echo esc_html( isset( $kdna_launchpad_app['scores'][ $kdna_launchpad_item['key'] ] ) ? $kdna_launchpad_app['scores'][ $kdna_launchpad_item['key'] ] : 0 ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<button type="button" class="button <?php echo 0 === $kdna_launchpad_rank ? 'button-primary' : ''; ?> kdna-launchpad-choose"
							data-application="<?php echo esc_attr( $kdna_launchpad_app['id'] ); ?>"
							data-business="<?php echo esc_attr( $kdna_launchpad_app['display_name'] ); ?>"
							data-email="<?php echo esc_attr( $kdna_launchpad_app['email'] ); ?>"
							data-top="<?php echo 0 === $kdna_launchpad_rank ? '1' : '0'; ?>">
							<?php esc_html_e( 'Choose as winner', 'kdna-launchpad' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<dialog class="kdna-launchpad-dialog" id="kdna-launchpad-confirm-winner" aria-labelledby="kdna-launchpad-confirm-title">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Applications::CHOOSE_WINNER_ACTION ); ?>" />
				<input type="hidden" name="application_id" value="" />
				<?php wp_nonce_field( KDNA_Launchpad_Admin_Applications::CHOOSE_WINNER_ACTION ); ?>

				<h2 id="kdna-launchpad-confirm-title"><?php esc_html_e( 'Confirm the winner', 'kdna-launchpad' ); ?></h2>
				<p><?php esc_html_e( 'This is what will happen:', 'kdna-launchpad' ); ?></p>
				<ul class="kdna-launchpad-list">
					<li>
						<?php esc_html_e( 'The winner email goes to', 'kdna-launchpad' ); ?>
						<strong data-fill="business"></strong> (<span data-fill="email"></span>).
					</li>
					<li>
						<?php
						$kdna_launchpad_others = count( $all_applications ) - 1;
						echo esc_html(
							sprintf(
								/* translators: %d: number of unsuccessful emails. */
								_n( '%d unsuccessful email goes to the other applicant, including any flagged applications.', '%d unsuccessful emails go to the other applicants, including flagged applications.', $kdna_launchpad_others, 'kdna-launchpad' ),
								$kdna_launchpad_others
							)
						);
						?>
					</li>
					<li><?php esc_html_e( 'The emails send straight away, in small batches in the background.', 'kdna-launchpad' ); ?></li>
					<li><?php esc_html_e( 'The round is marked Decided and its scores are locked.', 'kdna-launchpad' ); ?></li>
				</ul>

				<div class="kdna-launchpad-dialog__reason" hidden>
					<label for="kdna-launchpad-reason"><strong><?php esc_html_e( 'This isn\'t the top-scoring application. Please give a short reason.', 'kdna-launchpad' ); ?></strong></label>
					<p class="description"><?php esc_html_e( 'It is saved with the round, as a record that the decision was made on merit against the published criteria.', 'kdna-launchpad' ); ?></p>
					<textarea name="reason" id="kdna-launchpad-reason" rows="3" class="large-text"></textarea>
				</div>

				<div class="kdna-launchpad-dialog__actions">
					<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'kdna-launchpad' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm and send emails', 'kdna-launchpad' ); ?></button>
				</div>
			</form>
		</dialog>
	<?php endif; ?>

<?php elseif ( 'decided' === $kdna_launchpad_state ) : ?>
	<?php
	$kdna_launchpad_email_counts = array_count_values( wp_list_pluck( $all_applications, 'email_status' ) );
	?>
	<div class="kdna-launchpad-panel kdna-launchpad-panel--decided">
		<h2>
			<?php
			echo esc_html(
				$winner
					? sprintf(
						/* translators: %s: business name. */
						__( 'Winner: %s', 'kdna-launchpad' ),
						$winner['display_name']
					)
					: __( 'Decided', 'kdna-launchpad' )
			);
			?>
		</h2>
		<?php if ( $winner ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: total score, 2: maximum. */
						__( 'Total score %1$d out of %2$d.', 'kdna-launchpad' ),
						KDNA_Launchpad_Judging::total( $winner['scores'], $criteria ),
						$max_total
					)
				);
				?>
				<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $winner['id'] ) ); ?>"><?php esc_html_e( 'View application', 'kdna-launchpad' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( '' !== (string) $round['decision_note'] ) : ?>
			<p><strong><?php esc_html_e( 'Reason recorded:', 'kdna-launchpad' ); ?></strong> <?php echo esc_html( $round['decision_note'] ); ?></p>
		<?php endif; ?>
		<p class="kdna-launchpad-muted">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: sent, 2: queued, 3: failed. */
					__( 'Emails: %1$d sent, %2$d queued, %3$d failed. Scores are locked.', 'kdna-launchpad' ),
					isset( $kdna_launchpad_email_counts['sent'] ) ? $kdna_launchpad_email_counts['sent'] : 0,
					isset( $kdna_launchpad_email_counts['queued'] ) ? $kdna_launchpad_email_counts['queued'] : 0,
					isset( $kdna_launchpad_email_counts['failed'] ) ? $kdna_launchpad_email_counts['failed'] : 0
				)
			);
			?>
		</p>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="reopen">
				<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Applications::REOPEN_ACTION ); ?>" />
				<input type="hidden" name="round_id" value="<?php echo esc_attr( $round['id'] ); ?>" />
				<?php wp_nonce_field( KDNA_Launchpad_Admin_Applications::REOPEN_ACTION ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Reopen judging', 'kdna-launchpad' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $round['activity_log'] ) : ?>
	<details class="kdna-launchpad-log">
		<summary><?php esc_html_e( 'Round history', 'kdna-launchpad' ); ?></summary>
		<ul>
			<?php foreach ( array_reverse( $round['activity_log'] ) as $kdna_launchpad_entry ) : ?>
				<li>
					<span class="kdna-launchpad-muted"><?php echo esc_html( KDNA_Launchpad_Rounds::format( $kdna_launchpad_entry['time'] ) ); ?><?php echo $kdna_launchpad_entry['user'] ? esc_html( ', ' . $kdna_launchpad_entry['user'] ) : ''; ?>:</span>
					<?php echo esc_html( $kdna_launchpad_entry['message'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
<?php endif; ?>
