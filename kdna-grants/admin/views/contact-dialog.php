<?php
/**
 * The shared email window, printed once at the foot of pages with email buttons.
 *
 * Variables provided by KDNA_Grants_Admin_Contact::print_dialog():
 *
 * @var array  $templates Saved templates.
 * @var bool   $claude    Whether Claude is switched on.
 * @var string $reply_to  Where replies go.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dialog class="kdna-grants-dialog kdna-grants-compose" id="kdna-grants-compose" aria-labelledby="kdna-grants-compose-title">
	<form method="dialog" class="kdna-grants-compose__form">
		<input type="hidden" name="application_id" value="" />

		<div class="kdna-grants-compose__head">
			<h2 id="kdna-grants-compose-title"><?php esc_html_e( 'Email applicant', 'kdna-grants' ); ?></h2>
			<button type="button" class="button-link kdna-grants-compose__close" data-close aria-label="<?php esc_attr_e( 'Close', 'kdna-grants' ); ?>">&times;</button>
		</div>

		<p class="kdna-grants-compose__to">
			<strong><?php esc_html_e( 'To:', 'kdna-grants' ); ?></strong>
			<span data-fill="to"></span>
			<span class="kdna-grants-muted" data-fill="business"></span>
		</p>

		<div class="kdna-grants-compose__tools">
			<?php if ( $templates ) : ?>
				<label for="kdna-grants-compose-template" class="screen-reader-text"><?php esc_html_e( 'Template', 'kdna-grants' ); ?></label>
				<select id="kdna-grants-compose-template">
					<option value=""><?php esc_html_e( 'Start from a template…', 'kdna-grants' ); ?></option>
					<?php foreach ( $templates as $kdna_grants_index => $kdna_grants_template ) : ?>
						<option value="<?php echo esc_attr( $kdna_grants_index ); ?>"><?php echo esc_html( $kdna_grants_template['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( $claude ) : ?>
				<button type="button" class="button kdna-grants-compose__draft-toggle"><?php esc_html_e( 'Draft with Claude', 'kdna-grants' ); ?></button>
			<?php endif; ?>
		</div>

		<?php if ( $claude ) : ?>
			<div class="kdna-grants-compose__draft" hidden>
				<label for="kdna-grants-compose-purpose"><?php esc_html_e( 'What should the email do?', 'kdna-grants' ); ?></label>
				<textarea id="kdna-grants-compose-purpose" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'For example: invite them to a 20-minute call next week, and mention I loved their story about the family recipes.', 'kdna-grants' ); ?>"></textarea>
				<p>
					<button type="button" class="button button-primary kdna-grants-compose__draft-go"><?php esc_html_e( 'Write draft', 'kdna-grants' ); ?></button>
					<span class="description"><?php esc_html_e( 'Claude reads their application and writes a personal draft for you to edit. It doesn\'t see their email address.', 'kdna-grants' ); ?></span>
				</p>
			</div>
		<?php endif; ?>

		<label for="kdna-grants-compose-subject" class="kdna-grants-scorecard__label"><?php esc_html_e( 'Subject', 'kdna-grants' ); ?></label>
		<input type="text" id="kdna-grants-compose-subject" name="subject" class="large-text" />

		<label for="kdna-grants-compose-body" class="kdna-grants-scorecard__label"><?php esc_html_e( 'Message', 'kdna-grants' ); ?></label>
		<textarea id="kdna-grants-compose-body" name="body" rows="12" class="large-text"></textarea>

		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: email address. */
					__( 'Replies go to %s. The email is recorded on their application.', 'kdna-grants' ),
					$reply_to
				)
			);
			?>
		</p>

		<div class="kdna-grants-dialog__actions">
			<span class="kdna-grants-compose__status" role="status" aria-live="polite"></span>
			<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'kdna-grants' ); ?></button>
			<button type="button" class="button button-primary kdna-grants-compose__send"><?php esc_html_e( 'Send email', 'kdna-grants' ); ?></button>
		</div>
	</form>
</dialog>
