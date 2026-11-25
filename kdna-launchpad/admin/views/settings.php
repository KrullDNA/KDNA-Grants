<?php
/**
 * Settings screen.
 *
 * Variables provided by KDNA_Launchpad_Admin::render_settings_page():
 *
 * @var array    $settings   Current settings.
 * @var array    $forms      Active KDNA Forms forms, ID => title.
 * @var int      $form_id    The chosen form ID.
 * @var bool     $form_found Whether the chosen form still exists.
 * @var array    $fields     Mappable fields of the chosen form.
 * @var array    $targets    The KDNA Launchpad fields to match.
 * @var string[] $warnings   Things that still need attention.
 * @var bool     $saved      Whether settings were just saved.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kdna-launchpad-admin">
	<h1 class="kdna-launchpad-admin__title"><?php esc_html_e( 'Launchpad Settings', 'kdna-launchpad' ); ?></h1>

	<?php KDNA_Launchpad_Admin::render_message(); ?>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'kdna-launchpad' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $warnings ) : ?>
		<div class="kdna-launchpad-admin__attention">
			<h2><?php esc_html_e( 'Still to do', 'kdna-launchpad' ); ?></h2>
			<ul>
				<?php foreach ( $warnings as $warning ) : ?>
					<li><?php echo esc_html( $warning ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php elseif ( $form_found ) : ?>
		<div class="kdna-launchpad-admin__ready">
			<p><?php esc_html_e( 'All set. The application form is chosen and every field is matched.', 'kdna-launchpad' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="kdna-launchpad-settings-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin::SAVE_ACTION ); ?>" />
		<?php wp_nonce_field( KDNA_Launchpad_Admin::SAVE_ACTION ); ?>
		<input type="hidden" name="tab" value="" id="kdna-launchpad-tab-field" />

		<nav class="nav-tab-wrapper kdna-launchpad-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'kdna-launchpad' ); ?>">
			<a href="#form" class="nav-tab nav-tab-active" data-tab="form"><?php esc_html_e( 'Form', 'kdna-launchpad' ); ?></a>
			<a href="#rounds" class="nav-tab" data-tab="rounds"><?php esc_html_e( 'Rounds and eligibility', 'kdna-launchpad' ); ?></a>
			<a href="#judging" class="nav-tab" data-tab="judging"><?php esc_html_e( 'Judging', 'kdna-launchpad' ); ?></a>
			<a href="#emails" class="nav-tab" data-tab="emails"><?php esc_html_e( 'Emails and messages', 'kdna-launchpad' ); ?></a>
			<a href="#claude" class="nav-tab" data-tab="claude"><?php esc_html_e( 'Claude', 'kdna-launchpad' ); ?></a>
			<a href="#offers" class="nav-tab" data-tab="offers"><?php esc_html_e( 'Offers', 'kdna-launchpad' ); ?></a>
			<a href="#data" class="nav-tab" data-tab="data"><?php esc_html_e( 'Data', 'kdna-launchpad' ); ?></a>
		</nav>

		<div class="kdna-launchpad-tab-panel" data-panel="form">
		<!-- Launchpad form builder -->
		<div class="kdna-launchpad-card kdna-launchpad-card--builder">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'The Launchpad form', 'kdna-launchpad' ); ?></h2>
			<?php if ( $form_found ) : ?>
				<p class="kdna-launchpad-card__intro">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: form title. */
							__( 'Launchpad is using "%s". KDNA Forms looks after the form itself: spam protection, secure logo uploads, entries and the applicant\'s confirmation email.', 'kdna-launchpad' ),
							$forms[ $form_id ]
						)
					);
					?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( KDNA_Launchpad_Forms_Bridge::get_form_edit_url( $form_id ) ); ?>"><?php esc_html_e( 'Edit questions in KDNA Forms', 'kdna-launchpad' ); ?></a>
					<a class="button-link" href="<?php echo esc_url( KDNA_Launchpad_Forms_Bridge::get_form_notifications_url( $form_id ) ); ?>"><?php esc_html_e( 'Edit the confirmation email', 'kdna-launchpad' ); ?></a>
				</p>
			<?php else : ?>
				<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'One click builds the complete application form in KDNA Forms: the questions in order, the ABN lookup, word limits, the terms tick box and the applicant\'s confirmation email. Everything is matched for you.', 'kdna-launchpad' ); ?></p>
			<?php endif; ?>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-terms-url" class="kdna-launchpad-row__label"><?php esc_html_e( 'Terms and conditions page', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="url" name="terms_url" id="kdna-launchpad-terms-url" class="regular-text" value="<?php echo esc_attr( $settings['terms_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/launchpad-terms/' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'The address of your terms page. The form\'s "I agree to the terms" tick box links to it.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<?php if ( $form_found ) : ?>
				<details class="kdna-launchpad-builder__again">
					<summary><?php esc_html_e( 'Start again with a fresh Launchpad form', 'kdna-launchpad' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Builds a brand-new form and switches Launchpad to it. Your current form and its entries stay in KDNA Forms.', 'kdna-launchpad' ); ?></p>
					<button type="submit" name="create_form" value="1" class="button" data-confirm-click="newform"><?php esc_html_e( 'Create a new Launchpad form', 'kdna-launchpad' ); ?></button>
				</details>
			<?php else : ?>
				<p><button type="submit" name="create_form" value="1" class="button button-primary"><?php esc_html_e( 'Create the Launchpad form', 'kdna-launchpad' ); ?></button></p>
			<?php endif; ?>
		</div>

		<!-- Application form -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Application form', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Choose the KDNA Forms form people use to apply for Launchpad. New submissions to this form are added to the open round.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-form-id" class="kdna-launchpad-row__label"><?php esc_html_e( 'Launchpad application form', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<select name="form_id" id="kdna-launchpad-form-id">
						<option value="0"><?php esc_html_e( 'Choose a form', 'kdna-launchpad' ); ?></option>
						<?php foreach ( $forms as $kdna_launchpad_form_option => $kdna_launchpad_form_title ) : ?>
							<option value="<?php echo esc_attr( $kdna_launchpad_form_option ); ?>" <?php selected( $form_id, $kdna_launchpad_form_option ); ?>>
								<?php
								/* translators: 1: form title, 2: form ID. */
								echo esc_html( sprintf( __( '%1$s (ID %2$d)', 'kdna-launchpad' ), $kdna_launchpad_form_title, $kdna_launchpad_form_option ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( KDNA_Launchpad_Forms_Bridge::get_form_edit_url( $form_id ) ); ?>"
						id="kdna-launchpad-edit-form"
						class="kdna-launchpad-row__link"
						<?php echo $form_found ? '' : 'hidden'; ?>>
						<?php esc_html_e( 'Edit questions in KDNA Forms', 'kdna-launchpad' ); ?>
					</a>
					<p class="description">
						<?php esc_html_e( 'The applicant confirmation email is sent by KDNA Forms. Set it up as a Notification on this form, sent to the applicant\'s email field.', 'kdna-launchpad' ); ?>
						<?php if ( $form_found ) : ?>
							<a href="<?php echo esc_url( KDNA_Launchpad_Forms_Bridge::get_form_notifications_url( $form_id ) ); ?>"><?php esc_html_e( 'Open this form\'s Notifications', 'kdna-launchpad' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Field mapping -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Match the form fields', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'KDNA Launchpad needs to know which field holds each of these answers, so it can run the eligibility checks, fill in emails and show the applications list. Every other answer is still shown in full on the judging screen.', 'kdna-launchpad' ); ?></p>

			<p class="kdna-launchpad-card__empty" id="kdna-launchpad-map-empty" <?php echo $form_found ? 'hidden' : ''; ?>>
				<?php esc_html_e( 'Choose the application form above to see its fields.', 'kdna-launchpad' ); ?>
			</p>

			<table class="kdna-launchpad-map" id="kdna-launchpad-map" <?php echo $form_found ? '' : 'hidden'; ?>>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Launchpad needs', 'kdna-launchpad' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Field in your form', 'kdna-launchpad' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $targets as $key => $def ) : ?>
						<?php $current = isset( $settings['field_map'][ $key ] ) ? (string) $settings['field_map'][ $key ] : ''; ?>
						<tr>
							<th scope="row">
								<label for="kdna-launchpad-map-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
								<span class="kdna-launchpad-map__help"><?php echo esc_html( $def['help'] ); ?></span>
							</th>
							<td>
								<select name="field_map[<?php echo esc_attr( $key ); ?>]"
									id="kdna-launchpad-map-<?php echo esc_attr( $key ); ?>"
									class="kdna-launchpad-map__select"
									data-target="<?php echo esc_attr( $key ); ?>">
									<option value=""><?php esc_html_e( 'Choose a field', 'kdna-launchpad' ); ?></option>
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

		<div class="kdna-launchpad-tab-panel" data-panel="rounds">
		<!-- Rounds -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Rounds', 'kdna-launchpad' ); ?></h2>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-gap" class="kdna-launchpad-row__label"><?php esc_html_e( 'Gap between rounds', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" name="round_gap_months" id="kdna-launchpad-gap" min="1" max="12" step="1" class="small-text" value="<?php echo esc_attr( $settings['round_gap_months'] ); ?>" />
					<?php esc_html_e( 'months', 'kdna-launchpad' ); ?>
					<p class="description"><?php esc_html_e( '"Create next round" fills in dates this many months after the last round.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>
		</div>

		<!-- ABN Lookup -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'ABN Lookup', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'With a free ABN Lookup key (called a GUID) from the Australian Business Register, the form looks up each ABN as it\'s typed: the business name fills itself in, registered trading names are offered, and ABNs that don\'t exist or have been cancelled can\'t be submitted. Without a key, ABNs are still checked with the official checksum.', 'kdna-launchpad' ); ?></p>
			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-abr-guid" class="kdna-launchpad-row__label"><?php esc_html_e( 'ABN Lookup key (GUID)', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<?php if ( defined( 'KDNA_LAUNCHPAD_ABR_GUID' ) && KDNA_LAUNCHPAD_ABR_GUID ) : ?>
						<p><?php esc_html_e( 'Set in wp-config.php (KDNA_LAUNCHPAD_ABR_GUID).', 'kdna-launchpad' ); ?></p>
					<?php else : ?>
						<?php $kdna_launchpad_guid = KDNA_Launchpad_Abn::get_guid(); ?>
						<input type="text" name="abr_guid" id="kdna-launchpad-abr-guid" class="regular-text code" autocomplete="off" value="" placeholder="<?php echo $kdna_launchpad_guid ? esc_attr( sprintf( /* translators: %s: last four characters. */ __( 'Saved, ends in %s. Type a new key to replace it.', 'kdna-launchpad' ), substr( $kdna_launchpad_guid, -4 ) ) ) : esc_attr__( 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'kdna-launchpad' ); ?>" />
						<?php if ( $kdna_launchpad_guid ) : ?>
							<label class="kdna-launchpad-inline-check"><input type="checkbox" name="abr_remove_guid" value="1" /> <?php esc_html_e( 'Remove saved key', 'kdna-launchpad' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Register for free by searching for "ABN Lookup web services registration" on the Australian Business Register website. They email you the key.', 'kdna-launchpad' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Eligibility -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Eligibility checks', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Every new application is checked automatically. Checks only flag an application for you to look at. They never delete or reject it.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-staff-limit" class="kdna-launchpad-row__label"><?php esc_html_e( 'Staff limit', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<?php esc_html_e( 'Fewer than', 'kdna-launchpad' ); ?>
					<input type="number" name="staff_limit" id="kdna-launchpad-staff-limit" min="1" step="1" class="small-text" value="<?php echo esc_attr( $settings['staff_limit'] ); ?>" />
					<?php esc_html_e( 'staff', 'kdna-launchpad' ); ?>
					<p class="description"><?php esc_html_e( 'Applications at or over this number are flagged. Answers like "5 to 19" or "20 or more" are read using the highest number.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-clients" class="kdna-launchpad-row__label"><?php esc_html_e( 'Existing clients', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<textarea name="existing_clients" id="kdna-launchpad-clients" rows="8" class="large-text code"><?php echo esc_textarea( $settings['existing_clients'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line. Enter an email domain (for example acmeplumbing.com.au) or a business name (for example Acme Plumbing). Business names are matched ignoring capitals, punctuation and words like "Pty Ltd".', 'kdna-launchpad' ); ?></p>
				</div>
			</div>
		</div>

		</div>

		<div class="kdna-launchpad-tab-panel" data-panel="judging">
		<!-- Judging criteria -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Judging criteria', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Each criterion is scored 1 to 5. These should match the criteria published on the application page. Changes only affect rounds that haven\'t started judging, so past scores always stay as they were.', 'kdna-launchpad' ); ?></p>

			<ol class="kdna-launchpad-criteria" id="kdna-launchpad-criteria">
				<?php foreach ( array_values( (array) $settings['criteria'] ) as $kdna_launchpad_index => $kdna_launchpad_item ) : ?>
					<li class="kdna-launchpad-criteria__row">
						<input type="hidden" name="criteria[<?php echo esc_attr( $kdna_launchpad_index ); ?>][key]" value="<?php echo esc_attr( $kdna_launchpad_item['key'] ); ?>" class="kdna-launchpad-criteria__key" />
						<label class="screen-reader-text" for="kdna-launchpad-criterion-<?php echo esc_attr( $kdna_launchpad_index ); ?>"><?php esc_html_e( 'Criterion name', 'kdna-launchpad' ); ?></label>
						<input type="text" id="kdna-launchpad-criterion-<?php echo esc_attr( $kdna_launchpad_index ); ?>" name="criteria[<?php echo esc_attr( $kdna_launchpad_index ); ?>][label]" value="<?php echo esc_attr( $kdna_launchpad_item['label'] ); ?>" class="regular-text kdna-launchpad-criteria__label" />
						<span class="kdna-launchpad-criteria__tools">
							<button type="button" class="button kdna-launchpad-criteria__up" aria-label="<?php esc_attr_e( 'Move up', 'kdna-launchpad' ); ?>">&uarr;</button>
							<button type="button" class="button kdna-launchpad-criteria__down" aria-label="<?php esc_attr_e( 'Move down', 'kdna-launchpad' ); ?>">&darr;</button>
							<button type="button" class="button-link kdna-launchpad-link-danger kdna-launchpad-criteria__remove"><?php esc_html_e( 'Remove', 'kdna-launchpad' ); ?></button>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
			<p><button type="button" class="button" id="kdna-launchpad-add-criterion"><?php esc_html_e( 'Add criterion', 'kdna-launchpad' ); ?></button></p>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-tiebreak" class="kdna-launchpad-row__label"><?php esc_html_e( 'Tie-break criterion', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<select name="tiebreak_criterion" id="kdna-launchpad-tiebreak">
						<?php foreach ( array_values( (array) $settings['criteria'] ) as $kdna_launchpad_index => $kdna_launchpad_item ) : ?>
							<option value="<?php echo esc_attr( $kdna_launchpad_index ); ?>" <?php selected( $settings['tiebreak_criterion'], $kdna_launchpad_item['key'] ); ?>><?php echo esc_html( $kdna_launchpad_item['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'When two applications have the same total, the one with the higher score here ranks first.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>
		</div>
		</div>

		<div class="kdna-launchpad-tab-panel" data-panel="emails">
		<!-- Emails -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Emails', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'The winner and unsuccessful emails send when you choose a winner. The confirmation email applicants get when they apply is sent by KDNA Forms, as a Notification on the form.', 'kdna-launchpad' ); ?></p>
			<p class="kdna-launchpad-card__intro">
				<strong><?php esc_html_e( 'Merge tags:', 'kdna-launchpad' ); ?></strong>
				<?php foreach ( KDNA_Launchpad_Emails::merge_tags() as $kdna_launchpad_tag => $kdna_launchpad_tag_help ) : ?>
					<code title="<?php echo esc_attr( $kdna_launchpad_tag_help ); ?>"><?php echo esc_html( $kdna_launchpad_tag ); ?></code>
				<?php endforeach; ?>
			</p>
		</div>

		<!-- Email design -->
		<?php
		$kdna_launchpad_design   = KDNA_Launchpad_Email_Design::settings();
		$kdna_launchpad_logo_url = KDNA_Launchpad_Email_Design::logo_url( $kdna_launchpad_design );
		$kdna_launchpad_preview  = wp_nonce_url( admin_url( 'admin-post.php?action=' . KDNA_Launchpad_Email_Design::PREVIEW_ACTION ), KDNA_Launchpad_Email_Design::PREVIEW_ACTION );
		?>
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Email design', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'The look of every Launchpad email, including the confirmation and "new application" emails from the application form. Save, then preview.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-row">
				<span class="kdna-launchpad-row__label"><?php esc_html_e( 'Logo', 'kdna-launchpad' ); ?></span>
				<div class="kdna-launchpad-row__control kdna-launchpad-logo-picker">
					<input type="hidden" name="email_design[logo_id]" class="kdna-launchpad-logo-picker__id" value="<?php echo esc_attr( $kdna_launchpad_design['logo_id'] ); ?>" />
					<div class="kdna-launchpad-logo-picker__preview"<?php echo $kdna_launchpad_logo_url ? '' : ' hidden'; ?>>
						<img src="<?php echo esc_url( $kdna_launchpad_logo_url ); ?>" alt="" />
					</div>
					<p>
						<button type="button" class="button kdna-launchpad-logo-picker__choose"><?php esc_html_e( 'Choose logo', 'kdna-launchpad' ); ?></button>
						<button type="button" class="button-link kdna-launchpad-link-danger kdna-launchpad-logo-picker__remove"<?php echo $kdna_launchpad_design['logo_id'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'kdna-launchpad' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Use a PNG or JPG. Most email apps, including Gmail and Outlook, can\'t show SVG files. With no logo chosen, your site logo is used.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-logo-width" class="kdna-launchpad-row__label"><?php esc_html_e( 'Logo width', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" min="40" max="560" name="email_design[logo_width]" id="kdna-launchpad-logo-width" class="small-text" value="<?php echo esc_attr( $kdna_launchpad_design['logo_width'] ); ?>" /> px
					<select name="email_design[logo_align]" class="kdna-launchpad-select-auto" aria-label="<?php esc_attr_e( 'Logo position', 'kdna-launchpad' ); ?>">
						<option value="left" <?php selected( $kdna_launchpad_design['logo_align'], 'left' ); ?>><?php esc_html_e( 'Left', 'kdna-launchpad' ); ?></option>
						<option value="center" <?php selected( $kdna_launchpad_design['logo_align'], 'center' ); ?>><?php esc_html_e( 'Centre', 'kdna-launchpad' ); ?></option>
					</select>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-email-font" class="kdna-launchpad-row__label"><?php esc_html_e( 'Font', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="text" name="email_design[font]" id="kdna-launchpad-email-font" class="regular-text" value="<?php echo esc_attr( $kdna_launchpad_design['font'] ); ?>" placeholder="Inter" />
					<p class="description"><?php esc_html_e( 'A Google Fonts name, exactly as Google spells it. It shows in Apple Mail, iPhone and some other apps. Gmail and Outlook use the fallback below instead.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-email-fallback" class="kdna-launchpad-row__label"><?php esc_html_e( 'Fallback font', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<select name="email_design[fallback]" id="kdna-launchpad-email-fallback">
						<?php foreach ( KDNA_Launchpad_Email_Design::fallbacks() as $kdna_launchpad_key => $kdna_launchpad_fallback ) : ?>
							<option value="<?php echo esc_attr( $kdna_launchpad_key ); ?>" <?php selected( $kdna_launchpad_design['fallback'], $kdna_launchpad_key ); ?>><?php echo esc_html( $kdna_launchpad_fallback[0] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-heading-ls" class="kdna-launchpad-row__label"><?php esc_html_e( 'Heading spacing and position', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" step="0.1" min="-5" max="10" name="email_design[heading_ls]" id="kdna-launchpad-heading-ls" class="small-text" value="<?php echo esc_attr( $kdna_launchpad_design['heading_ls'] ); ?>" /> px
					<select name="email_design[heading_al]" class="kdna-launchpad-select-auto" aria-label="<?php esc_attr_e( 'Heading position', 'kdna-launchpad' ); ?>">
						<option value="center" <?php selected( $kdna_launchpad_design['heading_al'], 'center' ); ?>><?php esc_html_e( 'Centre', 'kdna-launchpad' ); ?></option>
						<option value="left" <?php selected( $kdna_launchpad_design['heading_al'], 'left' ); ?>><?php esc_html_e( 'Left', 'kdna-launchpad' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'For the 24px headings. 0 is normal, a minus number tightens, for example -0.5. Works in Outlook too.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-body-ls" class="kdna-launchpad-row__label"><?php esc_html_e( 'Body text letter spacing', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" step="0.1" min="-5" max="10" name="email_design[body_ls]" id="kdna-launchpad-body-ls" class="small-text" value="<?php echo esc_attr( $kdna_launchpad_design['body_ls'] ); ?>" /> px
					<p class="description"><?php esc_html_e( 'For the 16px body text. 0 is normal.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<span class="kdna-launchpad-row__label"><?php esc_html_e( 'Colours', 'kdna-launchpad' ); ?></span>
				<div class="kdna-launchpad-row__control kdna-launchpad-colours">
					<label><input type="color" name="email_design[background]" value="<?php echo esc_attr( $kdna_launchpad_design['background'] ); ?>" /> <?php esc_html_e( 'Background', 'kdna-launchpad' ); ?></label>
					<label><input type="color" name="email_design[text]" value="<?php echo esc_attr( $kdna_launchpad_design['text'] ); ?>" /> <?php esc_html_e( 'Text', 'kdna-launchpad' ); ?></label>
					<label><input type="color" name="email_design[link]" value="<?php echo esc_attr( $kdna_launchpad_design['link'] ); ?>" /> <?php esc_html_e( 'Links', 'kdna-launchpad' ); ?></label>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-email-footer" class="kdna-launchpad-row__label"><?php esc_html_e( 'Footer', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<textarea name="email_design[footer]" id="kdna-launchpad-email-footer" rows="3" class="large-text"><?php echo esc_textarea( $kdna_launchpad_design['footer'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown in small grey text under every email. Simple HTML works, such as links and bold, and pressing Enter starts a new line. For example: <a href="https://krulldna.com">krulldna.com</a>. Leave blank for no footer.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<p>
				<a class="button" href="<?php echo esc_url( $kdna_launchpad_preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview the winner email', 'kdna-launchpad' ); ?></a>
				<span class="description"><?php esc_html_e( 'Opens in a new tab with sample details. Save first to see your changes. For a true test, use "Send test email" below and open it in Gmail and Outlook.', 'kdna-launchpad' ); ?></span>
			</p>
		</div>

		<?php foreach ( KDNA_Launchpad_Emails::types() as $kdna_launchpad_type => $kdna_launchpad_type_label ) : ?>
			<?php $kdna_launchpad_template = KDNA_Launchpad_Emails::get_template( $kdna_launchpad_type ); ?>
			<div class="kdna-launchpad-card kdna-launchpad-email-card" data-type="<?php echo esc_attr( $kdna_launchpad_type ); ?>">
				<h2 class="kdna-launchpad-card__title"><?php echo esc_html( $kdna_launchpad_type_label ); ?></h2>

				<label for="kdna-launchpad-subject-<?php echo esc_attr( $kdna_launchpad_type ); ?>" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Subject', 'kdna-launchpad' ); ?></label>
				<input type="text" class="large-text kdna-launchpad-email-card__subject" id="kdna-launchpad-subject-<?php echo esc_attr( $kdna_launchpad_type ); ?>" name="emails[<?php echo esc_attr( $kdna_launchpad_type ); ?>][subject]" value="<?php echo esc_attr( $kdna_launchpad_template['subject'] ); ?>" />

				<label for="kdna-launchpad-heading-<?php echo esc_attr( $kdna_launchpad_type ); ?>" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Heading', 'kdna-launchpad' ); ?></label>
				<input type="text" class="large-text kdna-launchpad-email-card__heading" id="kdna-launchpad-heading-<?php echo esc_attr( $kdna_launchpad_type ); ?>" name="emails[<?php echo esc_attr( $kdna_launchpad_type ); ?>][heading]" value="<?php echo esc_attr( $kdna_launchpad_template['heading'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Shown large at the top of the email, above the text. Merge tags work here. Leave blank for no heading.', 'kdna-launchpad' ); ?></p>

				<p class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Message', 'kdna-launchpad' ); ?></p>
				<?php
				wp_editor(
					$kdna_launchpad_template['body'],
					'kdna_launchpad_email_' . $kdna_launchpad_type,
					array(
						'textarea_name' => 'emails[' . $kdna_launchpad_type . '][body]',
						'textarea_rows' => 12,
						'media_buttons' => false,
						'teeny'         => true,
					)
				);
				?>

				<p class="kdna-launchpad-email-card__test">
					<button type="button" class="button kdna-launchpad-send-test" data-type="<?php echo esc_attr( $kdna_launchpad_type ); ?>"><?php esc_html_e( 'Send test email', 'kdna-launchpad' ); ?></button>
					<span class="kdna-launchpad-email-card__result" role="status" aria-live="polite"></span>
					<span class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: email address. */
								__( 'Sends what is on screen to %s, with sample details filled in.', 'kdna-launchpad' ),
								get_option( 'admin_email' )
							)
						);
						?>
					</span>
				</p>
			</div>
		<?php endforeach; ?>

		<!-- Contacting applicants -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Emailing applicants from the dashboard', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Click any applicant\'s email address in KDNA Launchpad to write to them without leaving the dashboard. These templates are the starting points in that window.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-reply-to" class="kdna-launchpad-row__label"><?php esc_html_e( 'Replies go to', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="email" name="reply_to" id="kdna-launchpad-reply-to" class="regular-text" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Used for every KDNA Launchpad email, so replies land in your normal inbox. Leave blank to use the site admin email.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<p class="kdna-launchpad-card__intro">
				<strong><?php esc_html_e( 'Merge tags:', 'kdna-launchpad' ); ?></strong>
				<code>{first_name}</code> <code>{business_name}</code> <code>{round_name}</code> <code>{offer_code}</code> <code>{offer_percent}</code> <code>{offer_expiry}</code>
			</p>

			<div class="kdna-launchpad-repeat" id="kdna-launchpad-compose-templates" data-name="compose_templates">
				<?php foreach ( array_values( (array) $settings['compose_templates'] ) as $kdna_launchpad_index => $kdna_launchpad_template ) : ?>
					<div class="kdna-launchpad-repeat__row">
						<div class="kdna-launchpad-repeat__head">
							<input type="text" data-field="name" name="compose_templates[<?php echo esc_attr( $kdna_launchpad_index ); ?>][name]" value="<?php echo esc_attr( $kdna_launchpad_template['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Template name', 'kdna-launchpad' ); ?>" />
							<button type="button" class="button-link kdna-launchpad-link-danger kdna-launchpad-repeat__remove"><?php esc_html_e( 'Remove', 'kdna-launchpad' ); ?></button>
						</div>
						<input type="text" data-field="subject" name="compose_templates[<?php echo esc_attr( $kdna_launchpad_index ); ?>][subject]" value="<?php echo esc_attr( $kdna_launchpad_template['subject'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Subject', 'kdna-launchpad' ); ?>" />
						<textarea data-field="body" name="compose_templates[<?php echo esc_attr( $kdna_launchpad_index ); ?>][body]" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Message', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Message', 'kdna-launchpad' ); ?>"><?php echo esc_textarea( $kdna_launchpad_template['body'] ); ?></textarea>
					</div>
				<?php endforeach; ?>
				<template class="kdna-launchpad-repeat__template">
					<div class="kdna-launchpad-repeat__row">
						<div class="kdna-launchpad-repeat__head">
							<input type="text" data-field="name" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Template name', 'kdna-launchpad' ); ?>" />
							<button type="button" class="button-link kdna-launchpad-link-danger kdna-launchpad-repeat__remove"><?php esc_html_e( 'Remove', 'kdna-launchpad' ); ?></button>
						</div>
						<input type="text" data-field="subject" value="" class="large-text" placeholder="<?php esc_attr_e( 'Subject', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Subject', 'kdna-launchpad' ); ?>" />
						<textarea data-field="body" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Message', 'kdna-launchpad' ); ?>" aria-label="<?php esc_attr_e( 'Message', 'kdna-launchpad' ); ?>"></textarea>
					</div>
				</template>
			</div>
			<p><button type="button" class="button kdna-launchpad-repeat__add" data-target="kdna-launchpad-compose-templates"><?php esc_html_e( 'Add template', 'kdna-launchpad' ); ?></button></p>
		</div>

		<!-- Closed messages -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Closed message', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Shown in place of the form between rounds, by the Launchpad Application widget and the [kdna_launchpad] shortcode.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-closed-message" class="kdna-launchpad-row__label"><?php esc_html_e( 'When the next round is scheduled', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<textarea name="closed_message" id="kdna-launchpad-closed-message" rows="3" class="large-text"><?php echo esc_textarea( $settings['closed_message'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Use {next_open_date} for the date the next round opens, and {next_round} for its name (for example Q2 2027).', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-closed-none" class="kdna-launchpad-row__label"><?php esc_html_e( 'When no next round is scheduled', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<textarea name="closed_message_none" id="kdna-launchpad-closed-none" rows="3" class="large-text"><?php echo esc_textarea( $settings['closed_message_none'] ); ?></textarea>
				</div>
			</div>
		</div>
		</div>

		<div class="kdna-launchpad-tab-panel" data-panel="claude">
		<!-- Claude -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Claude', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'Claude reads every application against your studio profile and gives you a second opinion: a summary, how interesting the brief is for KDNA, strengths, concerns and questions for a shortlist call, plus an overview of each round\'s strongest applications. It never scores, chooses a winner or sends anything by itself.', 'kdna-launchpad' ); ?></p>

			<label class="kdna-launchpad-check">
				<input type="checkbox" name="claude_enabled" value="1" <?php checked( ! empty( $settings['claude_enabled'] ) ); ?> />
				<span><strong><?php esc_html_e( 'Switch on Claude', 'kdna-launchpad' ); ?></strong></span>
			</label>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-claude-key" class="kdna-launchpad-row__label"><?php esc_html_e( 'Claude API key', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<?php if ( KDNA_Launchpad_Claude::key_from_config() ) : ?>
						<p><?php esc_html_e( 'Set in wp-config.php (KDNA_LAUNCHPAD_CLAUDE_API_KEY).', 'kdna-launchpad' ); ?></p>
					<?php else : ?>
						<?php $kdna_launchpad_key = KDNA_Launchpad_Claude::get_api_key(); ?>
						<input type="password" name="claude_api_key" id="kdna-launchpad-claude-key" class="regular-text" autocomplete="off" value="" placeholder="<?php echo $kdna_launchpad_key ? esc_attr( sprintf( /* translators: %s: last four characters of the key. */ __( 'Saved, ends in %s. Type a new key to replace it.', 'kdna-launchpad' ), substr( $kdna_launchpad_key, -4 ) ) ) : esc_attr__( 'sk-ant-…', 'kdna-launchpad' ); ?>" />
						<?php if ( $kdna_launchpad_key ) : ?>
							<label class="kdna-launchpad-inline-check"><input type="checkbox" name="claude_remove_key" value="1" /> <?php esc_html_e( 'Remove saved key', 'kdna-launchpad' ); ?></label>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Create a key in the Anthropic Console (console.anthropic.com) under API keys. For extra security, a developer can put it in wp-config.php as KDNA_LAUNCHPAD_CLAUDE_API_KEY instead.', 'kdna-launchpad' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-claude-profile" class="kdna-launchpad-row__label"><?php esc_html_e( 'Your studio profile', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<textarea name="claude_profile" id="kdna-launchpad-claude-profile" rows="10" class="large-text" placeholder="<?php esc_attr_e( 'For example: I love brands with a strong founder story, hospitality and food, and projects where I can art direct photography. I\'m drawn to businesses open to bold ideas. Less keen on simple brochure sites with no brand to build on. Past favourites: ...', 'kdna-launchpad' ); ?>"><?php echo esc_textarea( $settings['claude_profile'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Describe the projects you love and why, and the ones you\'d rather avoid. Claude uses this for its "Interesting brief" rating. The more specific, the better its picks.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<label class="kdna-launchpad-check">
				<input type="checkbox" name="claude_auto" value="1" <?php checked( ! empty( $settings['claude_auto'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Read every application automatically when a round closes', 'kdna-launchpad' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Claude works through them in the background, then writes the round overview. You can also start it by hand on the Claude\'s picks tab.', 'kdna-launchpad' ); ?></span>
				</span>
			</label>

			<label class="kdna-launchpad-check">
				<input type="checkbox" name="claude_include_logo" value="1" <?php checked( ! empty( $settings['claude_include_logo'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Let Claude see the applicant\'s logo', 'kdna-launchpad' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Helps it judge where the brand is visually, at a small extra cost.', 'kdna-launchpad' ); ?></span>
				</span>
			</label>

			<div class="kdna-launchpad-note">
				<p><strong><?php esc_html_e( 'Privacy', 'kdna-launchpad' ); ?></strong></p>
				<p><?php esc_html_e( 'Claude only sees the business answers (and the logo, if switched on). Names, email addresses, phone numbers, addresses and ABNs are never sent. Anthropic doesn\'t train its models on data sent through the API by default. Because answers are sent to an overseas service, say on your application form and in your privacy policy that AI helps assess applications.', 'kdna-launchpad' ); ?></p>
				<p><?php esc_html_e( 'Cost: about 5 to 15 US cents per application, so a few dollars a round. Each round\'s running total shows on the Claude\'s picks tab.', 'kdna-launchpad' ); ?></p>
			</div>
		</div>
		</div>

		<div class="kdna-launchpad-tab-panel" data-panel="offers">
		<!-- Offers -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Offers for unsuccessful applicants', 'kdna-launchpad' ); ?></h2>
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'When you choose a winner, every unsuccessful applicant gets a personal code for a discount on their first brief with KDNA, in their unsuccessful email. Existing clients and past winners don\'t get one, and each business only ever gets one offer, even if they apply again.', 'kdna-launchpad' ); ?></p>

			<label class="kdna-launchpad-check">
				<input type="checkbox" name="offers_enabled" value="1" <?php checked( ! empty( $settings['offers_enabled'] ) ); ?> />
				<span><strong><?php esc_html_e( 'Give unsuccessful applicants an offer', 'kdna-launchpad' ); ?></strong></span>
			</label>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-offer-percent" class="kdna-launchpad-row__label"><?php esc_html_e( 'Discount', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" name="offer_percent" id="kdna-launchpad-offer-percent" min="1" max="100" class="small-text" value="<?php echo esc_attr( $settings['offer_percent'] ); ?>" /> <?php esc_html_e( '% off the first brief', 'kdna-launchpad' ); ?>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-offer-days" class="kdna-launchpad-row__label"><?php esc_html_e( 'Valid for', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" name="offer_days" id="kdna-launchpad-offer-days" min="1" max="3650" class="small-text" value="<?php echo esc_attr( $settings['offer_days'] ); ?>" /> <?php esc_html_e( 'days after the unsuccessful email', 'kdna-launchpad' ); ?>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-offer-cap" class="kdna-launchpad-row__label"><?php esc_html_e( 'Cap (optional)', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="text" name="offer_cap" id="kdna-launchpad-offer-cap" class="regular-text" value="<?php echo esc_attr( $settings['offer_cap'] ); ?>" placeholder="<?php esc_attr_e( 'For example: up to $3,000 off', 'kdna-launchpad' ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown on the Offers tab and available in emails as {offer_cap}. Leave blank for no cap.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<p class="description"><?php esc_html_e( 'Changes only apply to offers made from now on. Offers already sent keep their own discount and expiry date.', 'kdna-launchpad' ); ?></p>
		</div>
		</div>

		<div class="kdna-launchpad-tab-panel" data-panel="data">
		<!-- Retention -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Data retention', 'kdna-launchpad' ); ?></h2>
			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-retention" class="kdna-launchpad-row__label"><?php esc_html_e( 'Keep records for', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="number" name="retention_months" id="kdna-launchpad-retention" min="0" max="120" step="1" class="small-text" value="<?php echo esc_attr( $settings['retention_months'] ); ?>" />
					<?php esc_html_e( 'months after a round is decided', 'kdna-launchpad' ); ?>
					<p class="description"><?php esc_html_e( 'After this, the round and its applications, scores and notes are removed automatically. Enter 0 to keep them. KDNA Forms entries are not affected. Requests to export or erase someone\'s data are handled in Tools > Export Personal Data and Tools > Erase Personal Data.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Data -->
		<div class="kdna-launchpad-card">
			<h2 class="kdna-launchpad-card__title"><?php esc_html_e( 'Data', 'kdna-launchpad' ); ?></h2>

			<label class="kdna-launchpad-check">
				<input type="checkbox" name="delete_data_on_uninstall" value="1" id="kdna-launchpad-delete-data" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?> />
				<span>
					<strong><?php esc_html_e( 'Delete all KDNA Launchpad data when the plugin is deleted', 'kdna-launchpad' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Leave this off unless you are removing KDNA Launchpad for good. When off, deleting the plugin keeps every round, application, score and note, so reinstalling picks up where you left off. KDNA Forms entries are never deleted by KDNA Launchpad.', 'kdna-launchpad' ); ?></span>
				</span>
			</label>
		</div>

		</div>

		<?php submit_button( __( 'Save settings', 'kdna-launchpad' ) ); ?>
	</form>

	<p class="kdna-launchpad-admin__meta">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: KDNA Launchpad version, 2: KDNA Forms version. */
				__( 'KDNA Launchpad %1$s, using KDNA Forms %2$s', 'kdna-launchpad' ),
				KDNA_LAUNCHPAD_VERSION,
				KDNA_Launchpad_Forms_Bridge::get_version()
			)
		);
		?>
	</p>
</div>
