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
			<a href="#claude" class="nav-tab" data-tab="claude"><?php esc_html_e( 'Claude', 'kdna-grants' ); ?></a>
			<a href="#offers" class="nav-tab" data-tab="offers"><?php esc_html_e( 'Offers', 'kdna-grants' ); ?></a>
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

		<!-- Contacting applicants -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Emailing applicants from the dashboard', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Click any applicant\'s email address in KDNA Grants to write to them without leaving the dashboard. These templates are the starting points in that window.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-row">
				<label for="kdna-grants-reply-to" class="kdna-grants-row__label"><?php esc_html_e( 'Replies go to', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="email" name="reply_to" id="kdna-grants-reply-to" class="regular-text" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Used for every KDNA Grants email, so replies land in your normal inbox. Leave blank to use the site admin email.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<p class="kdna-grants-card__intro">
				<strong><?php esc_html_e( 'Merge tags:', 'kdna-grants' ); ?></strong>
				<code>{first_name}</code> <code>{business_name}</code> <code>{round_name}</code> <code>{offer_code}</code> <code>{offer_percent}</code> <code>{offer_expiry}</code>
			</p>

			<div class="kdna-grants-repeat" id="kdna-grants-compose-templates" data-name="compose_templates">
				<?php foreach ( array_values( (array) $settings['compose_templates'] ) as $kdna_grants_index => $kdna_grants_template ) : ?>
					<div class="kdna-grants-repeat__row">
						<div class="kdna-grants-repeat__head">
							<input type="text" data-field="name" name="compose_templates[<?php echo esc_attr( $kdna_grants_index ); ?>][name]" value="<?php echo esc_attr( $kdna_grants_template['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Template name', 'kdna-grants' ); ?>" />
							<button type="button" class="button-link kdna-grants-link-danger kdna-grants-repeat__remove"><?php esc_html_e( 'Remove', 'kdna-grants' ); ?></button>
						</div>
						<input type="text" data-field="subject" name="compose_templates[<?php echo esc_attr( $kdna_grants_index ); ?>][subject]" value="<?php echo esc_attr( $kdna_grants_template['subject'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Subject', 'kdna-grants' ); ?>" />
						<textarea data-field="body" name="compose_templates[<?php echo esc_attr( $kdna_grants_index ); ?>][body]" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Message', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Message', 'kdna-grants' ); ?>"><?php echo esc_textarea( $kdna_grants_template['body'] ); ?></textarea>
					</div>
				<?php endforeach; ?>
				<template class="kdna-grants-repeat__template">
					<div class="kdna-grants-repeat__row">
						<div class="kdna-grants-repeat__head">
							<input type="text" data-field="name" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Template name', 'kdna-grants' ); ?>" />
							<button type="button" class="button-link kdna-grants-link-danger kdna-grants-repeat__remove"><?php esc_html_e( 'Remove', 'kdna-grants' ); ?></button>
						</div>
						<input type="text" data-field="subject" value="" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Subject', 'kdna-grants' ); ?>" />
						<textarea data-field="body" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Message', 'kdna-grants' ); ?>" aria-label="<?php esc_attr_e( 'Message', 'kdna-grants' ); ?>"></textarea>
					</div>
				</template>
			</div>
			<p><button type="button" class="button kdna-grants-repeat__add" data-target="kdna-grants-compose-templates"><?php esc_html_e( 'Add template', 'kdna-grants' ); ?></button></p>
		</div>

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

		<div class="kdna-grants-tab-panel" data-panel="claude">
		<!-- Claude -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Claude', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'Claude reads every application against your studio profile and gives you a second opinion: a summary, how interesting the brief is for KDNA, strengths, concerns and questions for a shortlist call, plus an overview of each round\'s strongest applications. It never scores, chooses a winner or sends anything by itself.', 'kdna-grants' ); ?></p>

			<label class="kdna-grants-check">
				<input type="checkbox" name="claude_enabled" value="1" <?php checked( ! empty( $settings['claude_enabled'] ) ); ?> />
				<span><strong><?php esc_html_e( 'Switch on Claude', 'kdna-grants' ); ?></strong></span>
			</label>

			<div class="kdna-grants-row">
				<label for="kdna-grants-claude-key" class="kdna-grants-row__label"><?php esc_html_e( 'Claude API key', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<?php if ( KDNA_Grants_Claude::key_from_config() ) : ?>
						<p><?php esc_html_e( 'Set in wp-config.php (KDNA_GRANTS_CLAUDE_API_KEY).', 'kdna-grants' ); ?></p>
					<?php else : ?>
						<?php $kdna_grants_key = KDNA_Grants_Claude::get_api_key(); ?>
						<input type="password" name="claude_api_key" id="kdna-grants-claude-key" class="regular-text" autocomplete="off" value="" placeholder="<?php echo $kdna_grants_key ? esc_attr( sprintf( /* translators: %s: last four characters of the key. */ __( 'Saved, ends in %s. Type a new key to replace it.', 'kdna-grants' ), substr( $kdna_grants_key, -4 ) ) ) : esc_attr__( 'sk-ant-…', 'kdna-grants' ); ?>" />
						<?php if ( $kdna_grants_key ) : ?>
							<label class="kdna-grants-inline-check"><input type="checkbox" name="claude_remove_key" value="1" /> <?php esc_html_e( 'Remove saved key', 'kdna-grants' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Create a key in the Anthropic Console (console.anthropic.com) under API keys. For extra security, a developer can put it in wp-config.php as KDNA_GRANTS_CLAUDE_API_KEY instead.', 'kdna-grants' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-claude-profile" class="kdna-grants-row__label"><?php esc_html_e( 'Your studio profile', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<textarea name="claude_profile" id="kdna-grants-claude-profile" rows="10" class="large-text" placeholder="<?php esc_attr_e( 'For example: I love brands with a strong founder story, hospitality and food, and projects where I can art direct photography. I\'m drawn to businesses open to bold ideas. Less keen on simple brochure sites with no brand to build on. Past favourites: ...', 'kdna-grants' ); ?>"><?php echo esc_textarea( $settings['claude_profile'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Describe the projects you love and why, and the ones you\'d rather avoid. Claude uses this for its "Interesting brief" rating. The more specific, the better its picks.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<label class="kdna-grants-check">
				<input type="checkbox" name="claude_auto" value="1" <?php checked( ! empty( $settings['claude_auto'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Read every application automatically when a round closes', 'kdna-grants' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Claude works through them in the background, then writes the round overview. You can also start it by hand on the Claude\'s picks tab.', 'kdna-grants' ); ?></span>
				</span>
			</label>

			<label class="kdna-grants-check">
				<input type="checkbox" name="claude_include_logo" value="1" <?php checked( ! empty( $settings['claude_include_logo'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Let Claude see the applicant\'s logo', 'kdna-grants' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Helps it judge where the brand is visually, at a small extra cost.', 'kdna-grants' ); ?></span>
				</span>
			</label>

			<div class="kdna-grants-note">
				<p><strong><?php esc_html_e( 'Privacy', 'kdna-grants' ); ?></strong></p>
				<p><?php esc_html_e( 'Claude only sees the business answers (and the logo, if switched on). Names, email addresses, phone numbers, addresses and ABNs are never sent. Anthropic doesn\'t train its models on data sent through the API by default. Because answers are sent to an overseas service, say on your application form and in your privacy policy that AI helps assess applications.', 'kdna-grants' ); ?></p>
				<p><?php esc_html_e( 'Cost: about 5 to 15 US cents per application, so a few dollars a round. Each round\'s running total shows on the Claude\'s picks tab.', 'kdna-grants' ); ?></p>
			</div>
		</div>
		</div>

		<div class="kdna-grants-tab-panel" data-panel="offers">
		<!-- Offers -->
		<div class="kdna-grants-card">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Offers for unsuccessful applicants', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'When you choose a winner, every unsuccessful applicant gets a personal code for a discount on their first brief with KDNA, in their unsuccessful email. Existing clients and past winners don\'t get one, and each business only ever gets one offer, even if they apply again.', 'kdna-grants' ); ?></p>

			<label class="kdna-grants-check">
				<input type="checkbox" name="offers_enabled" value="1" <?php checked( ! empty( $settings['offers_enabled'] ) ); ?> />
				<span><strong><?php esc_html_e( 'Give unsuccessful applicants an offer', 'kdna-grants' ); ?></strong></span>
			</label>

			<div class="kdna-grants-row">
				<label for="kdna-grants-offer-percent" class="kdna-grants-row__label"><?php esc_html_e( 'Discount', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="number" name="offer_percent" id="kdna-grants-offer-percent" min="1" max="100" class="small-text" value="<?php echo esc_attr( $settings['offer_percent'] ); ?>" /> <?php esc_html_e( '% off the first brief', 'kdna-grants' ); ?>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-offer-days" class="kdna-grants-row__label"><?php esc_html_e( 'Valid for', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="number" name="offer_days" id="kdna-grants-offer-days" min="1" max="3650" class="small-text" value="<?php echo esc_attr( $settings['offer_days'] ); ?>" /> <?php esc_html_e( 'days after the unsuccessful email', 'kdna-grants' ); ?>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-offer-cap" class="kdna-grants-row__label"><?php esc_html_e( 'Cap (optional)', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="text" name="offer_cap" id="kdna-grants-offer-cap" class="regular-text" value="<?php echo esc_attr( $settings['offer_cap'] ); ?>" placeholder="<?php esc_attr_e( 'For example: up to $3,000 off', 'kdna-grants' ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown on the Offers tab and available in emails as {offer_cap}. Leave blank for no cap.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<p class="description"><?php esc_html_e( 'Changes only apply to offers made from now on. Offers already sent keep their own discount and expiry date.', 'kdna-grants' ); ?></p>
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
