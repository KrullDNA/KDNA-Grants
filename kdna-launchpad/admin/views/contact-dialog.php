<?php
/**
 * The shared email window, printed once at the foot of pages with email buttons.
 *
 * Variables provided by KDNA_Launchpad_Admin_Contact::print_dialog():
 *
 * @var array  $templates Saved templates.
 * @var bool   $claude    Whether Claude is switched on.
 * @var string $reply_to  Where replies go.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dialog class="kdna-launchpad-dialog kdna-launchpad-compose" id="kdna-launchpad-compose" aria-labelledby="kdna-launchpad-compose-title">
	<form method="dialog" class="kdna-launchpad-compose__form">
		<input type="hidden" name="application_id" value="" />

		<div class="kdna-launchpad-compose__head">
			<h2 id="kdna-launchpad-compose-title"><?php esc_html_e( 'Email applicant', 'kdna-launchpad' ); ?></h2>
			<button type="button" class="button-link kdna-launchpad-compose__close" data-close aria-label="<?php esc_attr_e( 'Close', 'kdna-launchpad' ); ?>">&times;</button>
		</div>

		<p class="kdna-launchpad-compose__to">
			<strong><?php esc_html_e( 'To:', 'kdna-launchpad' ); ?></strong>
			<span data-fill="to"></span>
			<span class="kdna-launchpad-muted" data-fill="business"></span>
		</p>

		<div class="kdna-launchpad-compose__tools">
			<?php if ( $templates ) : ?>
				<label for="kdna-launchpad-compose-template" class="screen-reader-text"><?php esc_html_e( 'Template', 'kdna-launchpad' ); ?></label>
				<select id="kdna-launchpad-compose-template">
					<option value=""><?php esc_html_e( 'Start from a template…', 'kdna-launchpad' ); ?></option>
					<?php foreach ( $templates as $kdna_launchpad_index => $kdna_launchpad_template ) : ?>
						<option value="<?php echo esc_attr( $kdna_launchpad_index ); ?>"><?php echo esc_html( $kdna_launchpad_template['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( $claude ) : ?>
				<button type="button" class="button kdna-launchpad-compose__draft-toggle"><?php esc_html_e( 'Draft with Claude', 'kdna-launchpad' ); ?></button>
			<?php endif; ?>
		</div>

		<?php if ( $claude ) : ?>
			<div class="kdna-launchpad-compose__draft" hidden>
				<label for="kdna-launchpad-compose-purpose"><?php esc_html_e( 'What should the email do?', 'kdna-launchpad' ); ?></label>
				<textarea id="kdna-launchpad-compose-purpose" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'For example: invite them to a 20-minute call next week, and mention I loved their story about the family recipes.', 'kdna-launchpad' ); ?>"></textarea>
				<p>
					<button type="button" class="button button-primary kdna-launchpad-compose__draft-go"><?php esc_html_e( 'Write draft', 'kdna-launchpad' ); ?></button>
					<span class="description"><?php esc_html_e( 'Claude reads their application and writes a personal draft for you to edit. It doesn\'t see their email address.', 'kdna-launchpad' ); ?></span>
				</p>
			</div>
		<?php endif; ?>

		<label for="kdna-launchpad-compose-subject" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Subject', 'kdna-launchpad' ); ?></label>
		<input type="text" id="kdna-launchpad-compose-subject" name="subject" class="large-text" />

		<label for="kdna-launchpad-compose-body" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Message', 'kdna-launchpad' ); ?></label>
		<textarea id="kdna-launchpad-compose-body" name="body" rows="12" class="large-text kdna-launchpad-rich"></textarea>

		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: email address. */
					__( 'Replies go to %s. The email is recorded on their application.', 'kdna-launchpad' ),
					$reply_to
				)
			);
			?>
		</p>

		<div class="kdna-launchpad-dialog__actions">
			<span class="kdna-launchpad-compose__status" role="status" aria-live="polite"></span>
			<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'kdna-launchpad' ); ?></button>
			<button type="button" class="button button-primary kdna-launchpad-compose__send"><?php esc_html_e( 'Send email', 'kdna-launchpad' ); ?></button>
		</div>
	</form>
</dialog>
