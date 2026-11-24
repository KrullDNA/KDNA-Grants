<?php
/**
 * Single application: every answer on the left, the scorecard on the right.
 *
 * Variables provided by KDNA_Launchpad_Admin_Applications::render_single():
 *
 * @var array       $application The application.
 * @var array       $round       Its round.
 * @var array|false $entry       The KDNA Forms entry, or false.
 * @var array       $answers     Answers from KDNA_Launchpad_Forms_Bridge::get_entry_answers().
 * @var string[]    $logos       Secure links to the uploaded logo.
 * @var array       $criteria    Criteria for this round.
 * @var array       $state       Scorecard state.
 * @var bool        $locked      Whether scores are locked.
 * @var array       $labels      Flag code => label.
 * @var int         $prev_id     Previous application ID, or 0.
 * @var int         $next_id     Next application ID, or 0.
 * @var string      $list_url    Back to the list.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_app  = $application;
$kdna_launchpad_from = 'single';
$kdna_launchpad_name = '' !== $application['business_name'] ? $application['business_name'] : __( '(no business name)', 'kdna-launchpad' );

/**
 * Makes links in an answer clickable and opens them in a new tab.
 *
 * @param string $html Answer HTML from KDNA Forms.
 * @return string
 */
$kdna_launchpad_links = function ( $html ) {
	$html = make_clickable( $html );
	$html = preg_replace( '/<a (?![^>]*target=)/i', '<a target="_blank" rel="noopener noreferrer" ', $html );

	return wp_kses_post( $html );
};
?>
<div class="wrap kdna-launchpad-admin kdna-launchpad-admin--wide kdna-launchpad-single">

	<div class="kdna-launchpad-single__top">
		<a href="<?php echo esc_url( $list_url ); ?>" class="kdna-launchpad-single__back">&larr; <?php echo esc_html( $round['name'] ); ?></a>
		<nav class="kdna-launchpad-single__nav" aria-label="<?php esc_attr_e( 'Move through the round', 'kdna-launchpad' ); ?>">
			<?php if ( $prev_id ) : ?>
				<a class="button" href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $prev_id ) ); ?>" rel="prev">&larr; <?php esc_html_e( 'Previous', 'kdna-launchpad' ); ?></a>
			<?php else : ?>
				<span class="button disabled" aria-disabled="true">&larr; <?php esc_html_e( 'Previous', 'kdna-launchpad' ); ?></span>
			<?php endif; ?>
			<?php if ( $next_id ) : ?>
				<a class="button" href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $next_id ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'kdna-launchpad' ); ?> &rarr;</a>
			<?php else : ?>
				<span class="button disabled" aria-disabled="true"><?php esc_html_e( 'Next', 'kdna-launchpad' ); ?> &rarr;</span>
			<?php endif; ?>
		</nav>
	</div>

	<h1 class="kdna-launchpad-single__title">
		<?php echo esc_html( $kdna_launchpad_name ); ?>
		<?php if ( 'winner' === $application['outcome'] ) : ?>
			<span class="kdna-launchpad-badge kdna-launchpad-badge--winner"><?php esc_html_e( 'Winner', 'kdna-launchpad' ); ?></span>
		<?php endif; ?>
	</h1>

	<?php KDNA_Launchpad_Admin::render_message(); ?>

	<div class="kdna-launchpad-single__layout">

		<!-- Left: the answers -->
		<div class="kdna-launchpad-single__answers kdna-launchpad-card">
			<?php if ( $logos ) : ?>
				<div class="kdna-launchpad-single__logo">
					<?php foreach ( $logos as $kdna_launchpad_logo ) : ?>
						<a href="<?php echo esc_url( $kdna_launchpad_logo ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $kdna_launchpad_logo ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: business name. */ __( '%s logo', 'kdna-launchpad' ), $kdna_launchpad_name ) ); ?>" />
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( KDNA_Launchpad_Claude::is_enabled() || $application['ai_assessment'] ) : ?>
				<?php $kdna_launchpad_ai = $application['ai_assessment']; ?>
				<div class="kdna-launchpad-claude-take">
					<div class="kdna-launchpad-claude-take__head">
						<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Claude\'s take', 'kdna-launchpad' ); ?></h2>
						<?php if ( $kdna_launchpad_ai ) : ?>
							<?php echo KDNA_Launchpad_Admin_Picks::rating( $kdna_launchpad_ai['interest_rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
						<?php endif; ?>
					</div>

					<?php if ( $kdna_launchpad_ai ) : ?>
						<p><?php echo esc_html( $kdna_launchpad_ai['summary'] ); ?></p>
						<p class="kdna-launchpad-muted"><strong><?php esc_html_e( 'Interesting brief:', 'kdna-launchpad' ); ?></strong> <?php echo esc_html( $kdna_launchpad_ai['interest_reason'] ); ?></p>
						<div class="kdna-launchpad-claude-take__lists">
							<?php
							$kdna_launchpad_lists = array(
								'scope_notes'    => __( 'Outside Launchpad', 'kdna-launchpad' ),
								'highlights'     => __( 'Strengths', 'kdna-launchpad' ),
								'concerns'       => __( 'Concerns', 'kdna-launchpad' ),
								'call_questions' => __( 'Ask on a call', 'kdna-launchpad' ),
							);
							foreach ( $kdna_launchpad_lists as $kdna_launchpad_key => $kdna_launchpad_title ) :
								if ( empty( $kdna_launchpad_ai[ $kdna_launchpad_key ] ) ) {
									continue;
								}
								?>
								<div>
									<h3><?php echo esc_html( $kdna_launchpad_title ); ?></h3>
									<ul>
										<?php foreach ( $kdna_launchpad_ai[ $kdna_launchpad_key ] as $kdna_launchpad_item ) : ?>
											<li><?php echo esc_html( $kdna_launchpad_item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="kdna-launchpad-muted kdna-launchpad-claude-take__meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: date and time. */
									__( 'Read %s. A second opinion only: your scores are your own.', 'kdna-launchpad' ),
									KDNA_Launchpad_Rounds::format( $application['ai_assessed_at'] )
								)
							);
							?>
						</p>
					<?php elseif ( in_array( $application['ai_status'], array( 'queued', 'running' ), true ) ) : ?>
						<p class="kdna-launchpad-muted"><?php esc_html_e( 'Claude is reading this application now. Refresh in a minute or two.', 'kdna-launchpad' ); ?></p>
					<?php elseif ( 'failed' === $application['ai_status'] ) : ?>
						<p class="kdna-launchpad-text-error"><?php echo esc_html( $application['ai_error'] ); ?></p>
					<?php else : ?>
						<p class="kdna-launchpad-muted"><?php esc_html_e( 'Claude hasn\'t read this application yet.', 'kdna-launchpad' ); ?></p>
					<?php endif; ?>

					<?php if ( current_user_can( 'manage_options' ) && KDNA_Launchpad_Claude::is_enabled() ) : ?>
						<p>
							<button type="button" class="button kdna-launchpad-assess" data-application="<?php echo esc_attr( $application['id'] ); ?>">
								<?php echo $kdna_launchpad_ai ? esc_html__( 'Ask Claude again', 'kdna-launchpad' ) : esc_html__( 'Ask Claude now', 'kdna-launchpad' ); ?>
							</button>
							<span class="kdna-launchpad-assess__status" role="status" aria-live="polite"></span>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<dl class="kdna-launchpad-answers">
				<?php if ( $answers ) : ?>
					<?php foreach ( $answers as $kdna_launchpad_answer ) : ?>
						<?php if ( 'section' === $kdna_launchpad_answer['type'] ) : ?>
							<dt class="kdna-launchpad-answers__section"><?php echo esc_html( $kdna_launchpad_answer['label'] ); ?></dt>
						<?php else : ?>
							<div class="kdna-launchpad-answers__item">
								<dt><?php echo esc_html( $kdna_launchpad_answer['label'] ); ?></dt>
								<dd><?php echo $kdna_launchpad_links( $kdna_launchpad_answer['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses_post(). ?></dd>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php else : ?>
					<?php if ( $application['entry_id'] && ! $entry ) : ?>
						<p class="kdna-launchpad-muted"><?php esc_html_e( 'The KDNA Forms entry for this application can no longer be found, so only the details KDNA Launchpad keeps are shown.', 'kdna-launchpad' ); ?></p>
					<?php endif; ?>
					<?php
					$kdna_launchpad_basic = array(
						__( 'Name', 'kdna-launchpad' )     => trim( $application['first_name'] . ' ' . $application['last_name'] ),
						__( 'Email', 'kdna-launchpad' )    => $application['email'],
						__( 'Business name', 'kdna-launchpad' ) => $application['business_name'],
						__( 'Industry', 'kdna-launchpad' ) => $application['industry'],
						__( 'ABN', 'kdna-launchpad' )      => KDNA_Launchpad_Eligibility::format_abn( $application['abn'] ),
						__( 'Staff', 'kdna-launchpad' )    => $application['staff'],
					);
					foreach ( array_filter( $kdna_launchpad_basic, 'strlen' ) as $kdna_launchpad_label => $kdna_launchpad_value ) :
						?>
						<div class="kdna-launchpad-answers__item">
							<dt><?php echo esc_html( $kdna_launchpad_label ); ?></dt>
							<dd><?php echo esc_html( $kdna_launchpad_value ); ?></dd>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</dl>

			<p class="kdna-launchpad-single__meta kdna-launchpad-muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: date and time. */
						__( 'Applied %s', 'kdna-launchpad' ),
						KDNA_Launchpad_Rounds::format( $application['created_at'] )
					)
				);
				?>
				<?php if ( $entry ) : ?>
					&middot; <a href="<?php echo esc_url( KDNA_Launchpad_Forms_Bridge::get_entry_url( $entry ) ); ?>"><?php esc_html_e( 'Open in KDNA Forms', 'kdna-launchpad' ); ?></a>
				<?php endif; ?>
			</p>
		</div>

		<!-- Right: contact, eligibility and the scorecard -->
		<aside class="kdna-launchpad-single__side">

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="kdna-launchpad-card kdna-launchpad-side-card">
					<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Contact', 'kdna-launchpad' ); ?></h2>
					<p>
						<?php echo esc_html( trim( $application['first_name'] . ' ' . $application['last_name'] ) ); ?><br />
						<?php echo KDNA_Launchpad_Admin_Contact::button( $application ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					</p>
					<?php $kdna_launchpad_offer_note = KDNA_Launchpad_Admin_Offers::summary( $application ); ?>
					<?php if ( $kdna_launchpad_offer_note ) : ?>
						<p><?php echo $kdna_launchpad_offer_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Launchpad_Admin::OFFERS_PAGE ) ); ?>"><?php esc_html_e( 'Offers', 'kdna-launchpad' ); ?></a></p>
					<?php endif; ?>
					<?php if ( $application['contact_log'] ) : ?>
						<details class="kdna-launchpad-log">
							<summary>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of emails. */
										_n( '%d email sent from the dashboard', '%d emails sent from the dashboard', count( $application['contact_log'] ), 'kdna-launchpad' ),
										count( $application['contact_log'] )
									)
								);
								?>
							</summary>
							<ul>
								<?php foreach ( array_reverse( $application['contact_log'] ) as $kdna_launchpad_sent ) : ?>
									<li><span class="kdna-launchpad-muted"><?php echo esc_html( KDNA_Launchpad_Rounds::format( $kdna_launchpad_sent['time'] ) ); ?>:</span> <?php echo esc_html( $kdna_launchpad_sent['subject'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="kdna-launchpad-card kdna-launchpad-side-card">
				<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Eligibility', 'kdna-launchpad' ); ?></h2>
				<?php if ( ! $application['eligibility_flags'] ) : ?>
					<span class="kdna-launchpad-flag kdna-launchpad-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-launchpad' ); ?></span>
				<?php else : ?>
					<ul class="kdna-launchpad-flags">
						<?php foreach ( $application['eligibility_flags'] as $kdna_launchpad_flag ) : ?>
							<li class="<?php echo ! empty( $kdna_launchpad_flag['cleared'] ) ? 'is-cleared' : ''; ?>">
								<span class="kdna-launchpad-flag <?php echo ! empty( $kdna_launchpad_flag['cleared'] ) ? 'kdna-launchpad-flag--cleared' : ''; ?>">
									<?php echo esc_html( isset( $labels[ $kdna_launchpad_flag['code'] ] ) ? $labels[ $kdna_launchpad_flag['code'] ] : $kdna_launchpad_flag['code'] ); ?>
								</span>
								<span class="kdna-launchpad-flag__reason"><?php echo esc_html( $kdna_launchpad_flag['reason'] ); ?></span>
								<?php if ( ! empty( $kdna_launchpad_flag['cleared'] ) ) : ?>
									<span class="kdna-launchpad-flag__cleared">
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: date, 2: person's name. */
												__( 'Marked as eligible %1$s by %2$s', 'kdna-launchpad' ),
												KDNA_Launchpad_Rounds::format( $kdna_launchpad_flag['cleared_at'], false ),
												$kdna_launchpad_flag['cleared_by']
											)
										);
										?>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php if ( ! $application['is_eligible'] && ! $locked ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>" />
							<input type="hidden" name="application_id" value="<?php echo esc_attr( $application['id'] ); ?>" />
							<input type="hidden" name="from" value="single" />
							<?php wp_nonce_field( KDNA_Launchpad_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Mark as eligible', 'kdna-launchpad' ); ?></button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="kdna-launchpad-card kdna-launchpad-side-card kdna-launchpad-scorecard<?php echo $locked ? ' is-locked' : ''; ?>"
				data-application="<?php echo esc_attr( $application['id'] ); ?>">

				<div class="kdna-launchpad-scorecard__head">
					<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Scorecard', 'kdna-launchpad' ); ?></h2>
					<span class="kdna-launchpad-scorecard__saved" role="status" aria-live="polite"></span>
				</div>

				<?php if ( $locked ) : ?>
					<p class="kdna-launchpad-scorecard__locked"><?php esc_html_e( 'This round has been decided, so scores are locked. Reopen judging from the applications list to change them.', 'kdna-launchpad' ); ?></p>
				<?php endif; ?>

				<?php foreach ( $criteria['items'] as $kdna_launchpad_item ) : ?>
					<?php $kdna_launchpad_score = isset( $state['scores']->{$kdna_launchpad_item['key']} ) ? (int) $state['scores']->{$kdna_launchpad_item['key']} : 0; ?>
					<fieldset class="kdna-launchpad-criterion" data-key="<?php echo esc_attr( $kdna_launchpad_item['key'] ); ?>">
						<legend>
							<?php echo esc_html( $kdna_launchpad_item['label'] ); ?>
							<?php if ( $kdna_launchpad_item['key'] === $criteria['tiebreak'] ) : ?>
								<span class="kdna-launchpad-criterion__tag"><?php esc_html_e( 'Tie-break', 'kdna-launchpad' ); ?></span>
							<?php endif; ?>
						</legend>
						<div class="kdna-launchpad-criterion__buttons">
							<?php for ( $kdna_launchpad_i = KDNA_Launchpad_Judging::MIN_SCORE; $kdna_launchpad_i <= KDNA_Launchpad_Judging::MAX_SCORE; $kdna_launchpad_i++ ) : ?>
								<button type="button"
									class="kdna-launchpad-score<?php echo $kdna_launchpad_i === $kdna_launchpad_score ? ' is-selected' : ''; ?>"
									data-score="<?php echo esc_attr( $kdna_launchpad_i ); ?>"
									aria-pressed="<?php echo $kdna_launchpad_i === $kdna_launchpad_score ? 'true' : 'false'; ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: 1: criterion, 2: score. */ __( '%1$s: %2$d out of 5', 'kdna-launchpad' ), $kdna_launchpad_item['label'], $kdna_launchpad_i ) ); ?>"
									<?php disabled( $locked ); ?>>
									<?php echo esc_html( $kdna_launchpad_i ); ?>
								</button>
							<?php endfor; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<div class="kdna-launchpad-scorecard__total">
					<span><?php esc_html_e( 'Total', 'kdna-launchpad' ); ?></span>
					<strong><span data-total><?php echo esc_html( $state['total'] ); ?></span> / <?php echo esc_html( $state['max'] ); ?></strong>
				</div>

				<label class="kdna-launchpad-scorecard__label" for="kdna-launchpad-notes"><?php esc_html_e( 'Notes', 'kdna-launchpad' ); ?></label>
				<textarea id="kdna-launchpad-notes" class="kdna-launchpad-scorecard__notes" rows="6" <?php disabled( $locked ); ?>><?php echo esc_textarea( $application['notes'] ); ?></textarea>

				<label class="kdna-launchpad-switch">
					<input type="checkbox" class="kdna-launchpad-scorecard__shortlist" <?php checked( $application['shortlisted'] ); ?> <?php disabled( $locked ); ?> />
					<span class="kdna-launchpad-switch__track" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Shortlist', 'kdna-launchpad' ); ?></span>
				</label>
			</div>

			<?php if ( $application['email_status'] ) : ?>
				<div class="kdna-launchpad-card kdna-launchpad-side-card">
					<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Outcome email', 'kdna-launchpad' ); ?></h2>
					<?php include KDNA_LAUNCHPAD_PATH . 'admin/views/email-status.php'; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</div>
