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
		<input type="hidden" name="tab" value="" id="kdna-grants-tab-field" />

		<nav class="nav-tab-wrapper kdna-grants-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'kdna-grants' ); ?>">
			<a href="#form" class="nav-tab nav-tab-active" data-tab="form"><?php esc_html_e( 'Form', 'kdna-grants' ); ?></a>
			<a href="#rounds" class="nav-tab" data-tab="rounds"><?php esc_html_e( 'Rounds and eligibility', 'kdna-grants' ); ?></a>
			<a href="#judging" class="nav-tab" data-tab="judging"><?php esc_html_e( 'Judging', 'kdna-grants' ); ?></a>
			<a href="#emails" class="nav-tab" data-tab="emails"><?php esc_html_e( 'Emails and messages', 'kdna-grants' ); ?></a>
			<a href="#data" class="nav-tab" data-tab="data"><?php esc_html_e( 'Data', 'kdna-grants' ); ?></a>
		</nav>

		<div class="kdna-grants-tab-panel" data-panel="form">
		<!-- Application form -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Application form', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Choose the KDNA Forms form people use to apply for the grant. New submissions to this form are added to the open round.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-form-id" class="kdna-grants-row__label"><?php esc_html_e( 'Grant application form', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<select name="form_id" id="kdna-grants-form-id">
						<option value="0"><?php esc_html_e( 'Choose a form', 'kdna-grants' ); ?></option>
						<?php foreach ( $forms as $kdna_grants_form_option => $kdna_grants_form_title ) : ?>
							<option value="<?php echo esc_attr( $kdna_grants_form_option ); ?>" <?php selected( $form_id, $kdna_grants_form_option ); ?>>
								<?php
								/* translators: 1: form title, 2: form ID. */
								echo esc_html( sprintf( __( '%1$s (ID %2$d)', 'kdna-grants' ), $kdna_grants_form_title, $kdna_grants_form_option ) );
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

		</div>

		<div class="kdna-grants-tab-panel" data-panel="rounds">
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

		</div>

		<div class="kdna-grants-tab-panel" data-panel="judging">
		<!-- Judging criteria -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Judging criteria', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Each criterion is scored 1 to 5. These should match the criteria published on the application page. Changes only affect rounds that haven\'t started judging, so past scores always stay as they were.', 'kdna-grants' ); ?></p>

			<ol class="kdna-grants-criteria" id="kdna-grants-criteria">
				<?php foreach ( array_values( (array) $settings['criteria'] ) as $kdna_grants_index => $kdna_grants_item ) : ?>
					<li class="kdna-grants-criteria__row">
						<input type="hidden" name="criteria[<?php echo esc_attr( $kdna_grants_index ); ?>][key]" value="<?php echo esc_attr( $kdna_grants_item['key'] ); ?>" class="kdna-grants-criteria__key" />
						<label class="screen-reader-text" for="kdna-grants-criterion-<?php echo esc_attr( $kdna_grants_index ); ?>"><?php esc_html_e( 'Criterion name', 'kdna-grants' ); ?></label>
						<input type="text" id="kdna-grants-criterion-<?php echo esc_attr( $kdna_grants_index ); ?>" name="criteria[<?php echo esc_attr( $kdna_grants_index ); ?>][label]" value="<?php echo esc_attr( $kdna_grants_item['label'] ); ?>" class="regular-text kdna-grants-criteria__label" />
						<span class="kdna-grants-criteria__tools">
							<button type="button" class="button kdna-grants-criteria__up" aria-label="<?php esc_attr_e( 'Move up', 'kdna-grants' ); ?>">&uarr;</button>
							<button type="button" class="button kdna-grants-criteria__down" aria-label="<?php esc_attr_e( 'Move down', 'kdna-grants' ); ?>">&darr;</button>
							<button type="button" class="button-link kdna-grants-link-danger kdna-grants-criteria__remove"><?php esc_html_e( 'Remove', 'kdna-grants' ); ?></button>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
			<p><button type="button" class="button" id="kdna-grants-add-criterion"><?php esc_html_e( 'Add criterion', 'kdna-grants' ); ?></button></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-tiebreak" class="kdna-grants-row__label"><?php esc_html_e( 'Tie-break criterion', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<select name="tiebreak_criterion" id="kdna-grants-tiebreak">
						<?php foreach ( array_values( (array) $settings['criteria'] ) as $kdna_grants_index => $kdna_grants_item ) : ?>
							<option value="<?php echo esc_attr( $kdna_grants_index ); ?>" <?php selected( $settings['tiebreak_criterion'], $kdna_grants_item['key'] ); ?>><?php echo esc_html( $kdna_grants_item['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'When two applications have the same total, the one with the higher score here ranks first.', 'kdna-grants' ); ?></p>
				</div>
			</div>
		</div>
		</div>

		<div class="kdna-grants-tab-panel" data-panel="emails">
		<!-- Emails -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Emails', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'The winner and unsuccessful emails send when you choose a winner. The confirmation email applicants get when they apply is sent by KDNA Forms, as a Notification on the form.', 'kdna-grants' ); ?></p>
			<p class="kdna-grants-card__intro">
				<strong><?php esc_html_e( 'Merge tags:', 'kdna-grants' ); ?></strong>
				<?php foreach ( KDNA_Grants_Emails::merge_tags() as $kdna_grants_tag => $kdna_grants_tag_help ) : ?>
					<code title="<?php echo esc_attr( $kdna_grants_tag_help ); ?>"><?php echo esc_html( $kdna_grants_tag ); ?></code>
				<?php endforeach; ?>
			</p>
		</div>

		<?php foreach ( KDNA_Grants_Emails::types() as $kdna_grants_type => $kdna_grants_type_label ) : ?>
			<?php $kdna_grants_template = KDNA_Grants_Emails::get_template( $kdna_grants_type ); ?>
			<div class="kdna-grants-card kdna-grants-email-card" data-type="<?php echo esc_attr( $kdna_grants_type ); ?>">
				<h2 class="kdna-grants-card__title"><?php echo esc_html( $kdna_grants_type_label ); ?></h2>

				<label for="kdna-grants-subject-<?php echo esc_attr( $kdna_grants_type ); ?>" class="kdna-grants-scorecard__label"><?php esc_html_e( 'Subject', 'kdna-grants' ); ?></label>
				<input type="text" class="large-text kdna-grants-email-card__subject" id="kdna-grants-subject-<?php echo esc_attr( $kdna_grants_type ); ?>" name="emails[<?php echo esc_attr( $kdna_grants_type ); ?>][subject]" value="<?php echo esc_attr( $kdna_grants_template['subject'] ); ?>" />

				<p class="kdna-grants-scorecard__label"><?php esc_html_e( 'Message', 'kdna-grants' ); ?></p>
				<?php
				wp_editor(
					$kdna_grants_template['body'],
					'kdna_grants_email_' . $kdna_grants_type,
					array(
						'textarea_name' => 'emails[' . $kdna_grants_type . '][body]',
						'textarea_rows' => 12,
						'media_buttons' => false,
						'teeny'         => true,
					)
				);
				?>

				<p class="kdna-grants-email-card__test">
					<button type="button" class="button kdna-grants-send-test" data-type="<?php echo esc_attr( $kdna_grants_type ); ?>"><?php esc_html_e( 'Send test email', 'kdna-grants' ); ?></button>
					<span class="kdna-grants-email-card__result" role="status" aria-live="polite"></span>
					<span class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: email address. */
								__( 'Sends what is on screen to %s, with sample details filled in.', 'kdna-grants' ),
								get_option( 'admin_email' )
							)
						);
						?>
					</span>
				</p>
			</div>
		<?php endforeach; ?>

		<!-- Closed messages -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Closed message', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Shown in place of the form between rounds, by the Grant Application widget and the [kdna_grant_application] shortcode.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-closed-message" class="kdna-grants-row__label"><?php esc_html_e( 'When the next round is scheduled', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<textarea name="closed_message" id="kdna-grants-closed-message" rows="3" class="large-text"><?php echo esc_textarea( $settings['closed_message'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Use {next_open_date} for the date the next round opens.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-closed-none" class="kdna-grants-row__label"><?php esc_html_e( 'When no next round is scheduled', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<textarea name="closed_message_none" id="kdna-grants-closed-none" rows="3" class="large-text"><?php echo esc_textarea( $settings['closed_message_none'] ); ?></textarea>
				</div>
			</div>
		</div>
		</div>

		<div class="kdna-grants-tab-panel" data-panel="data">
		<!-- Retention -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Data retention', 'kdna-grants' ); ?></h2>
			<div class="kdna-grants-row">
				<label for="kdna-grants-retention" class="kdna-grants-row__label"><?php esc_html_e( 'Keep records for', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="number" name="retention_months" id="kdna-grants-retention" min="0" max="120" step="1" class="small-text" value="<?php echo esc_attr( $settings['retention_months'] ); ?>" />
					<?php esc_html_e( 'months after a round is decided', 'kdna-grants' ); ?>
					<p class="description"><?php esc_html_e( 'After this, the round and its applications, scores and notes are removed automatically. Enter 0 to keep them. KDNA Forms entries are not affected. Requests to export or erase someone\'s data are handled in Tools > Export Personal Data and Tools > Erase Personal Data.', 'kdna-grants' ); ?></p>
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
