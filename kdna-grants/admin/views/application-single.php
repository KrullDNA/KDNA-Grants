<?php
/**
 * Single application: every answer on the left, the scorecard on the right.
 *
 * Variables provided by KDNA_Grants_Admin_Applications::render_single():
 *
 * @var array       $application The application.
 * @var array       $round       Its round.
 * @var array|false $entry       The KDNA Forms entry, or false.
 * @var array       $answers     Answers from KDNA_Grants_Forms_Bridge::get_entry_answers().
 * @var string[]    $logos       Secure links to the uploaded logo.
 * @var array       $criteria    Criteria for this round.
 * @var array       $state       Scorecard state.
 * @var bool        $locked      Whether scores are locked.
 * @var array       $labels      Flag code => label.
 * @var int         $prev_id     Previous application ID, or 0.
 * @var int         $next_id     Next application ID, or 0.
 * @var string      $list_url    Back to the list.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_app  = $application;
$kdna_grants_from = 'single';
$kdna_grants_name = '' !== $application['business_name'] ? $application['business_name'] : __( '(no business name)', 'kdna-grants' );

/**
 * Makes links in an answer clickable and opens them in a new tab.
 *
 * @param string $html Answer HTML from KDNA Forms.
 * @return string
 */
$kdna_grants_links = function ( $html ) {
	$html = make_clickable( $html );
	$html = preg_replace( '/<a (?![^>]*target=)/i', '<a target="_blank" rel="noopener noreferrer" ', $html );

	return wp_kses_post( $html );
};
?>
<div class="wrap kdna-grants-admin kdna-grants-admin--wide kdna-grants-single">

	<div class="kdna-grants-single__top">
		<a href="<?php echo esc_url( $list_url ); ?>" class="kdna-grants-single__back">&larr; <?php echo esc_html( $round['name'] ); ?></a>
		<nav class="kdna-grants-single__nav" aria-label="<?php esc_attr_e( 'Move through the round', 'kdna-grants' ); ?>">
			<?php if ( $prev_id ) : ?>
				<a class="button" href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $prev_id ) ); ?>" rel="prev">&larr; <?php esc_html_e( 'Previous', 'kdna-grants' ); ?></a>
			<?php else : ?>
				<span class="button disabled" aria-disabled="true">&larr; <?php esc_html_e( 'Previous', 'kdna-grants' ); ?></span>
			<?php endif; ?>
			<?php if ( $next_id ) : ?>
				<a class="button" href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $next_id ) ); ?>" rel="next"><?php esc_html_e( 'Next', 'kdna-grants' ); ?> &rarr;</a>
			<?php else : ?>
				<span class="button disabled" aria-disabled="true"><?php esc_html_e( 'Next', 'kdna-grants' ); ?> &rarr;</span>
			<?php endif; ?>
		</nav>
	</div>

	<h1 class="kdna-grants-single__title">
		<?php echo esc_html( $kdna_grants_name ); ?>
		<?php if ( 'winner' === $application['outcome'] ) : ?>
			<span class="kdna-grants-badge kdna-grants-badge--winner"><?php esc_html_e( 'Winner', 'kdna-grants' ); ?></span>
		<?php endif; ?>
	</h1>

	<?php KDNA_Grants_Admin::render_message(); ?>

	<div class="kdna-grants-single__layout">

		<!-- Left: the answers -->
		<div class="kdna-grants-single__answers kdna-grants-card">
			<?php if ( $logos ) : ?>
				<div class="kdna-grants-single__logo">
					<?php foreach ( $logos as $kdna_grants_logo ) : ?>
						<a href="<?php echo esc_url( $kdna_grants_logo ); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url( $kdna_grants_logo ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: business name. */ __( '%s logo', 'kdna-grants' ), $kdna_grants_name ) ); ?>" />
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( KDNA_Grants_Claude::is_enabled() || $application['ai_assessment'] ) : ?>
				<?php $kdna_grants_ai = $application['ai_assessment']; ?>
				<div class="kdna-grants-claude-take">
					<div class="kdna-grants-claude-take__head">
						<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Claude\'s take', 'kdna-grants' ); ?></h2>
						<?php if ( $kdna_grants_ai ) : ?>
							<?php echo KDNA_Grants_Admin_Picks::rating( $kdna_grants_ai['interest_rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
						<?php endif; ?>
					</div>

					<?php if ( $kdna_grants_ai ) : ?>
						<p><?php echo esc_html( $kdna_grants_ai['summary'] ); ?></p>
						<p class="kdna-grants-muted"><strong><?php esc_html_e( 'Interesting brief:', 'kdna-grants' ); ?></strong> <?php echo esc_html( $kdna_grants_ai['interest_reason'] ); ?></p>
						<div class="kdna-grants-claude-take__lists">
							<?php
							$kdna_grants_lists = array(
								'scope_notes'    => __( 'Outside the grant', 'kdna-grants' ),
								'highlights'     => __( 'Strengths', 'kdna-grants' ),
								'concerns'       => __( 'Concerns', 'kdna-grants' ),
								'call_questions' => __( 'Ask on a call', 'kdna-grants' ),
							);
							foreach ( $kdna_grants_lists as $kdna_grants_key => $kdna_grants_title ) :
								if ( empty( $kdna_grants_ai[ $kdna_grants_key ] ) ) {
									continue;
								}
								?>
								<div>
									<h3><?php echo esc_html( $kdna_grants_title ); ?></h3>
									<ul>
										<?php foreach ( $kdna_grants_ai[ $kdna_grants_key ] as $kdna_grants_item ) : ?>
											<li><?php echo esc_html( $kdna_grants_item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="kdna-grants-muted kdna-grants-claude-take__meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: date and time. */
									__( 'Read %s. A second opinion only: your scores are your own.', 'kdna-grants' ),
									KDNA_Grants_Rounds::format( $application['ai_assessed_at'] )
								)
							);
							?>
						</p>
					<?php elseif ( in_array( $application['ai_status'], array( 'queued', 'running' ), true ) ) : ?>
						<p class="kdna-grants-muted"><?php esc_html_e( 'Claude is reading this application now. Refresh in a minute or two.', 'kdna-grants' ); ?></p>
					<?php elseif ( 'failed' === $application['ai_status'] ) : ?>
						<p class="kdna-grants-text-error"><?php echo esc_html( $application['ai_error'] ); ?></p>
					<?php else : ?>
						<p class="kdna-grants-muted"><?php esc_html_e( 'Claude hasn\'t read this application yet.', 'kdna-grants' ); ?></p>
					<?php endif; ?>

					<?php if ( current_user_can( 'manage_options' ) && KDNA_Grants_Claude::is_enabled() ) : ?>
						<p>
							<button type="button" class="button kdna-grants-assess" data-application="<?php echo esc_attr( $application['id'] ); ?>">
								<?php echo $kdna_grants_ai ? esc_html__( 'Ask Claude again', 'kdna-grants' ) : esc_html__( 'Ask Claude now', 'kdna-grants' ); ?>
							</button>
							<span class="kdna-grants-assess__status" role="status" aria-live="polite"></span>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<dl class="kdna-grants-answers">
				<?php if ( $answers ) : ?>
					<?php foreach ( $answers as $kdna_grants_answer ) : ?>
						<?php if ( 'section' === $kdna_grants_answer['type'] ) : ?>
							<dt class="kdna-grants-answers__section"><?php echo esc_html( $kdna_grants_answer['label'] ); ?></dt>
						<?php else : ?>
							<div class="kdna-grants-answers__item">
								<dt><?php echo esc_html( $kdna_grants_answer['label'] ); ?></dt>
								<dd><?php echo $kdna_grants_links( $kdna_grants_answer['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Passed through wp_kses_post(). ?></dd>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php else : ?>
					<?php if ( $application['entry_id'] && ! $entry ) : ?>
						<p class="kdna-grants-muted"><?php esc_html_e( 'The KDNA Forms entry for this application can no longer be found, so only the details KDNA Grants keeps are shown.', 'kdna-grants' ); ?></p>
					<?php endif; ?>
					<?php
					$kdna_grants_basic = array(
						__( 'Name', 'kdna-grants' )     => trim( $application['first_name'] . ' ' . $application['last_name'] ),
						__( 'Email', 'kdna-grants' )    => $application['email'],
						__( 'Business name', 'kdna-grants' ) => $application['business_name'],
						__( 'Industry', 'kdna-grants' ) => $application['industry'],
						__( 'ABN', 'kdna-grants' )      => KDNA_Grants_Eligibility::format_abn( $application['abn'] ),
						__( 'Staff', 'kdna-grants' )    => $application['staff'],
					);
					foreach ( array_filter( $kdna_grants_basic, 'strlen' ) as $kdna_grants_label => $kdna_grants_value ) :
						?>
						<div class="kdna-grants-answers__item">
							<dt><?php echo esc_html( $kdna_grants_label ); ?></dt>
							<dd><?php echo esc_html( $kdna_grants_value ); ?></dd>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</dl>

			<p class="kdna-grants-single__meta kdna-grants-muted">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: date and time. */
						__( 'Applied %s', 'kdna-grants' ),
						KDNA_Grants_Rounds::format( $application['created_at'] )
					)
				);
				?>
				<?php if ( $entry ) : ?>
					&middot; <a href="<?php echo esc_url( KDNA_Grants_Forms_Bridge::get_entry_url( $entry ) ); ?>"><?php esc_html_e( 'Open in KDNA Forms', 'kdna-grants' ); ?></a>
				<?php endif; ?>
			</p>
		</div>

		<!-- Right: contact, eligibility and the scorecard -->
		<aside class="kdna-grants-single__side">

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="kdna-grants-card kdna-grants-side-card">
					<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Contact', 'kdna-grants' ); ?></h2>
					<p>
						<?php echo esc_html( trim( $application['first_name'] . ' ' . $application['last_name'] ) ); ?><br />
						<?php echo KDNA_Grants_Admin_Contact::button( $application ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					</p>
					<?php $kdna_grants_offer_note = KDNA_Grants_Admin_Offers::summary( $application ); ?>
					<?php if ( $kdna_grants_offer_note ) : ?>
						<p><?php echo $kdna_grants_offer_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . KDNA_Grants_Admin::OFFERS_PAGE ) ); ?>"><?php esc_html_e( 'Offers', 'kdna-grants' ); ?></a></p>
					<?php endif; ?>
					<?php if ( $application['contact_log'] ) : ?>
						<details class="kdna-grants-log">
							<summary>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of emails. */
										_n( '%d email sent from the dashboard', '%d emails sent from the dashboard', count( $application['contact_log'] ), 'kdna-grants' ),
										count( $application['contact_log'] )
									)
								);
								?>
							</summary>
							<ul>
								<?php foreach ( array_reverse( $application['contact_log'] ) as $kdna_grants_sent ) : ?>
									<li><span class="kdna-grants-muted"><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_sent['time'] ) ); ?>:</span> <?php echo esc_html( $kdna_grants_sent['subject'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="kdna-grants-card kdna-grants-side-card">
				<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Eligibility', 'kdna-grants' ); ?></h2>
				<?php if ( ! $application['eligibility_flags'] ) : ?>
					<span class="kdna-grants-flag kdna-grants-flag--ok"><?php esc_html_e( 'Eligible', 'kdna-grants' ); ?></span>
				<?php else : ?>
					<ul class="kdna-grants-flags">
						<?php foreach ( $application['eligibility_flags'] as $kdna_grants_flag ) : ?>
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
					<?php if ( ! $application['is_eligible'] && ! $locked ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>" />
							<input type="hidden" name="application_id" value="<?php echo esc_attr( $application['id'] ); ?>" />
							<input type="hidden" name="from" value="single" />
							<?php wp_nonce_field( KDNA_Grants_Admin_Applications::MARK_ELIGIBLE_ACTION ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Mark as eligible', 'kdna-grants' ); ?></button>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="kdna-grants-card kdna-grants-side-card kdna-grants-scorecard<?php echo $locked ? ' is-locked' : ''; ?>"
				data-application="<?php echo esc_attr( $application['id'] ); ?>">

				<div class="kdna-grants-scorecard__head">
					<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Scorecard', 'kdna-grants' ); ?></h2>
					<span class="kdna-grants-scorecard__saved" role="status" aria-live="polite"></span>
				</div>

				<?php if ( $locked ) : ?>
					<p class="kdna-grants-scorecard__locked"><?php esc_html_e( 'This round has been decided, so scores are locked. Reopen judging from the applications list to change them.', 'kdna-grants' ); ?></p>
				<?php endif; ?>

				<?php foreach ( $criteria['items'] as $kdna_grants_item ) : ?>
					<?php $kdna_grants_score = isset( $state['scores']->{$kdna_grants_item['key']} ) ? (int) $state['scores']->{$kdna_grants_item['key']} : 0; ?>
					<fieldset class="kdna-grants-criterion" data-key="<?php echo esc_attr( $kdna_grants_item['key'] ); ?>">
						<legend>
							<?php echo esc_html( $kdna_grants_item['label'] ); ?>
							<?php if ( $kdna_grants_item['key'] === $criteria['tiebreak'] ) : ?>
								<span class="kdna-grants-criterion__tag"><?php esc_html_e( 'Tie-break', 'kdna-grants' ); ?></span>
							<?php endif; ?>
						</legend>
						<div class="kdna-grants-criterion__buttons">
							<?php for ( $kdna_grants_i = KDNA_Grants_Judging::MIN_SCORE; $kdna_grants_i <= KDNA_Grants_Judging::MAX_SCORE; $kdna_grants_i++ ) : ?>
								<button type="button"
									class="kdna-grants-score<?php echo $kdna_grants_i === $kdna_grants_score ? ' is-selected' : ''; ?>"
									data-score="<?php echo esc_attr( $kdna_grants_i ); ?>"
									aria-pressed="<?php echo $kdna_grants_i === $kdna_grants_score ? 'true' : 'false'; ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: 1: criterion, 2: score. */ __( '%1$s: %2$d out of 5', 'kdna-grants' ), $kdna_grants_item['label'], $kdna_grants_i ) ); ?>"
									<?php disabled( $locked ); ?>>
									<?php echo esc_html( $kdna_grants_i ); ?>
								</button>
							<?php endfor; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<div class="kdna-grants-scorecard__total">
					<span><?php esc_html_e( 'Total', 'kdna-grants' ); ?></span>
					<strong><span data-total><?php echo esc_html( $state['total'] ); ?></span> / <?php echo esc_html( $state['max'] ); ?></strong>
				</div>

				<label class="kdna-grants-scorecard__label" for="kdna-grants-notes"><?php esc_html_e( 'Notes', 'kdna-grants' ); ?></label>
				<textarea id="kdna-grants-notes" class="kdna-grants-scorecard__notes" rows="6" <?php disabled( $locked ); ?>><?php echo esc_textarea( $application['notes'] ); ?></textarea>

				<label class="kdna-grants-switch">
					<input type="checkbox" class="kdna-grants-scorecard__shortlist" <?php checked( $application['shortlisted'] ); ?> <?php disabled( $locked ); ?> />
					<span class="kdna-grants-switch__track" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Shortlist', 'kdna-grants' ); ?></span>
				</label>
			</div>

			<?php if ( $application['email_status'] ) : ?>
				<div class="kdna-grants-card kdna-grants-side-card">
					<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Outcome email', 'kdna-grants' ); ?></h2>
					<?php include KDNA_GRANTS_PATH . 'admin/views/email-status.php'; ?>
				</div>
			<?php endif; ?>
		</aside>
	</div>
</div>
