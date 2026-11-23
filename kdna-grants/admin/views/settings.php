<?php
/**
 * Settings screen.
 *
 * Variables provided by KDNA_Grants_Admin::render_settings_page():
 *
 * @var array    $settings   Current settings.
 * @var array    $forms      Active KDNA Forms forms, ID => title.
 * @var int      $form_id    The chosen form ID.
 * @var bool     $form_found Whether the chosen form still exists.
 * @var array    $fields     Mappable fields of the chosen form.
 * @var array    $targets    The KDNA Grants fields to match.
 * @var string[] $warnings   Things that still need attention.
 * @var bool     $saved      Whether settings were just saved.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kdna-grants-admin">
	<h1 class="kdna-grants-admin__title"><?php esc_html_e( 'KDNA Grants Settings', 'kdna-grants' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'kdna-grants' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $warnings ) : ?>
		<div class="kdna-grants-admin__attention">
			<h2><?php esc_html_e( 'Still to do', 'kdna-grants' ); ?></h2>
			<ul>
				<?php foreach ( $warnings as $warning ) : ?>
					<li><?php echo esc_html( $warning ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php elseif ( $form_found ) : ?>
		<div class="kdna-grants-admin__ready">
			<p><?php esc_html_e( 'All set. The application form is chosen and every field is matched.', 'kdna-grants' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kdna-grants-settings-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin::SAVE_ACTION ); ?>" />
		<?php wp_nonce_field( KDNA_Grants_Admin::SAVE_ACTION ); ?>

		<!-- Application form -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Application form', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Choose the KDNA Forms form people use to apply for the grant. New submissions to this form are added to the open round.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-form-id" class="kdna-grants-row__label"><?php esc_html_e( 'Grant application form', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<select name="form_id" id="kdna-grants-form-id">
						<option value="0"><?php esc_html_e( 'Choose a form', 'kdna-grants' ); ?></option>
						<?php foreach ( $forms as $id => $title ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $form_id, $id ); ?>>
								<?php
								/* translators: 1: form title, 2: form ID. */
								echo esc_html( sprintf( __( '%1$s (ID %2$d)', 'kdna-grants' ), $title, $id ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( KDNA_Grants_Forms_Bridge::get_form_edit_url( $form_id ) ); ?>"
						id="kdna-grants-edit-form"
						class="kdna-grants-row__link"
						<?php echo $form_found ? '' : 'hidden'; ?>>
						<?php esc_html_e( 'Edit this form in KDNA Forms', 'kdna-grants' ); ?>
					</a>
					<p class="description">
						<?php esc_html_e( 'The applicant confirmation email is sent by KDNA Forms. Set it up as a Notification on this form, sent to the applicant\'s email field.', 'kdna-grants' ); ?>
						<?php if ( $form_found ) : ?>
							<a href="<?php echo esc_url( KDNA_Grants_Forms_Bridge::get_form_notifications_url( $form_id ) ); ?>"><?php esc_html_e( 'Open this form\'s Notifications', 'kdna-grants' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Field mapping -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Match the form fields', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'KDNA Grants needs to know which field holds each of these answers, so it can run the eligibility checks, fill in emails and show the applications list. Every other answer is still shown in full on the judging screen.', 'kdna-grants' ); ?></p>

			<p class="kdna-grants-card__empty" id="kdna-grants-map-empty" <?php echo $form_found ? 'hidden' : ''; ?>>
				<?php esc_html_e( 'Choose the application form above to see its fields.', 'kdna-grants' ); ?>
			</p>

			<table class="kdna-grants-map" id="kdna-grants-map" <?php echo $form_found ? '' : 'hidden'; ?>>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'KDNA Grants needs', 'kdna-grants' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Field in your form', 'kdna-grants' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $targets as $key => $def ) : ?>
						<?php $current = isset( $settings['field_map'][ $key ] ) ? (string) $settings['field_map'][ $key ] : ''; ?>
						<tr>
							<th scope="row">
								<label for="kdna-grants-map-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
								<span class="kdna-grants-map__help"><?php echo esc_html( $def['help'] ); ?></span>
							</th>
							<td>
								<select name="field_map[<?php echo esc_attr( $key ); ?>]"
									id="kdna-grants-map-<?php echo esc_attr( $key ); ?>"
									class="kdna-grants-map__select"
									data-target="<?php echo esc_attr( $key ); ?>">
									<option value=""><?php esc_html_e( 'Choose a field', 'kdna-grants' ); ?></option>
									<?php foreach ( $fields as $field ) : ?>
										<?php
										if ( ! empty( $def['types'] ) && ! in_array( $field['type'], $def['types'], true ) ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( $field['value'] ); ?>" <?php selected( $current, $field['value'] ); ?>>
											<?php echo esc_html( $field['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Rounds -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Rounds', 'kdna-grants' ); ?></h2>

			<div class="kdna-grants-row">
				<label for="kdna-grants-gap" class="kdna-grants-row__label"><?php esc_html_e( 'Gap between rounds', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="number" name="round_gap_months" id="kdna-grants-gap" min="1" max="12" step="1" class="small-text" value="<?php echo esc_attr( $settings['round_gap_months'] ); ?>" />
					<?php esc_html_e( 'months', 'kdna-grants' ); ?>
					<p class="description"><?php esc_html_e( '"Create next round" fills in dates this many months after the last round.', 'kdna-grants' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Eligibility -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Eligibility checks', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Every new application is checked automatically. Checks only flag an application for you to look at. They never delete or reject it.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-staff-limit" class="kdna-grants-row__label"><?php esc_html_e( 'Staff limit', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<?php esc_html_e( 'Fewer than', 'kdna-grants' ); ?>
					<input type="number" name="staff_limit" id="kdna-grants-staff-limit" min="1" step="1" class="small-text" value="<?php echo esc_attr( $settings['staff_limit'] ); ?>" />
					<?php esc_html_e( 'staff', 'kdna-grants' ); ?>
					<p class="description"><?php esc_html_e( 'Applications at or over this number are flagged. Answers like "5 to 19" or "20 or more" are read using the highest number.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-clients" class="kdna-grants-row__label"><?php esc_html_e( 'Existing clients', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<textarea name="existing_clients" id="kdna-grants-clients" rows="8" class="large-text code"><?php echo esc_textarea( $settings['existing_clients'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. Enter an email domain (for example acmeplumbing.com.au) or a business name (for example Acme Plumbing). Business names are matched ignoring capitals, punctuation and words like "Pty Ltd".', 'kdna-grants' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Data -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Data', 'kdna-grants' ); ?></h2>

			<label class="kdna-grants-check">
				<input type="checkbox" name="delete_data_on_uninstall" value="1" id="kdna-grants-delete-data" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Delete all KDNA Grants data when the plugin is deleted', 'kdna-grants' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Leave this off unless you are removing KDNA Grants for good. When off, deleting the plugin keeps every round, application, score and note, so reinstalling picks up where you left off. KDNA Forms entries are never deleted by KDNA Grants.', 'kdna-grants' ); ?></span>
				</span>
			</label>
		</div>

		<?php submit_button( __( 'Save settings', 'kdna-grants' ) ); ?>
	</form>

	<p class="kdna-grants-admin__meta">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: KDNA Grants version, 2: KDNA Forms version. */
				__( 'KDNA Grants %1$s, using KDNA Forms %2$s', 'kdna-grants' ),
				KDNA_GRANTS_VERSION,
				KDNA_Grants_Forms_Bridge::get_version()
			)
		);
		?>
	</p>
</div>
