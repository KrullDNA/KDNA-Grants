<?php
/**
 * Round editor, for a new round or an existing one.
 *
 * Variables provided by KDNA_Launchpad_Admin_Rounds::render_edit():
 *
 * @var array|null $round     The round being edited, or null for a new round.
 * @var array      $values    name, opens, closes (Y-m-d\TH:i), announce (Y-m-d).
 * @var string[]   $errors    Problems from the last attempt to save.
 * @var bool       $is_locked Whether the round is decided and can't be changed.
 * @var string     $timezone  The site's time zone.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_back = add_query_arg( 'page', KDNA_Launchpad_Admin::ROUNDS_PAGE, admin_url( 'admin.php' ) );
?>
<div class="wrap kdna-launchpad-admin">
	<h1><?php echo $round ? esc_html__( 'Edit round', 'kdna-launchpad' ) : esc_html__( 'Create round', 'kdna-launchpad' ); ?></h1>
	<p><a href="<?php echo esc_url( $kdna_launchpad_back ); ?>">&larr; <?php esc_html_e( 'Back to Rounds', 'kdna-launchpad' ); ?></a></p>

	<?php if ( $errors ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'The round wasn\'t saved:', 'kdna-launchpad' ); ?></strong></p>
			<ul class="kdna-launchpad-list">
				<?php foreach ( $errors as $kdna_launchpad_error ) : ?>
					<li><?php echo esc_html( $kdna_launchpad_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $is_locked ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'This round has been decided, so it can no longer be changed.', 'kdna-launchpad' ); ?></p></div>
	<?php elseif ( $round && KDNA_Launchpad_Rounds::STATUS_OPEN === $round['status'] ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'This round is open right now. Changing the dates takes effect straight away.', 'kdna-launchpad' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Rounds::SAVE_ACTION ); ?>" />
		<input type="hidden" name="round_id" value="<?php echo esc_attr( $round ? $round['id'] : 0 ); ?>" />
		<?php wp_nonce_field( KDNA_Launchpad_Admin_Rounds::SAVE_ACTION ); ?>

		<div class="kdna-launchpad-card">
			<?php if ( $round ) : ?>
				<p class="kdna-launchpad-card__status">
					<?php esc_html_e( 'Status:', 'kdna-launchpad' ); ?>
					<?php echo KDNA_Launchpad_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
				</p>
			<?php else : ?>
				<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'The dates are filled in for you, following on from the last round. Change anything you need to.', 'kdna-launchpad' ); ?></p>
			<?php endif; ?>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-round-name" class="kdna-launchpad-row__label"><?php esc_html_e( 'Round name', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="text" name="name" id="kdna-launchpad-round-name" class="regular-text" value="<?php echo esc_attr( $values['name'] ); ?>" <?php disabled( $is_locked ); ?> />
					<p class="description"><?php esc_html_e( 'Leave blank to name it automatically, for example "Q1 2027".', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-round-opens" class="kdna-launchpad-row__label"><?php esc_html_e( 'Opens', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="datetime-local" name="opens" id="kdna-launchpad-round-opens" value="<?php echo esc_attr( $values['opens'] ); ?>" required <?php disabled( $is_locked ); ?> />
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-round-closes" class="kdna-launchpad-row__label"><?php esc_html_e( 'Closes', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="datetime-local" name="closes" id="kdna-launchpad-round-closes" value="<?php echo esc_attr( $values['closes'] ); ?>" required <?php disabled( $is_locked ); ?> />
				</div>
			</div>

			<div class="kdna-launchpad-row">
				<label for="kdna-launchpad-round-announce" class="kdna-launchpad-row__label"><?php esc_html_e( 'Winner announced by', 'kdna-launchpad' ); ?></label>
				<div class="kdna-launchpad-row__control">
					<input type="date" name="announce" id="kdna-launchpad-round-announce" value="<?php echo esc_attr( $values['announce'] ); ?>" <?php disabled( $is_locked ); ?> />
					<p class="description"><?php esc_html_e( 'Used in the email copy as {announce_date}.', 'kdna-launchpad' ); ?></p>
				</div>
			</div>

			<p class="description kdna-launchpad-card__note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: time zone name. */
						__( 'Times are in the site\'s time zone (%s), set in Settings > General. The form opens and closes at exactly these times, checked every time the form is shown or submitted.', 'kdna-launchpad' ),
						$timezone
					)
				);
				?>
			</p>
		</div>

		<?php if ( ! $is_locked ) : ?>
			<?php submit_button( $round ? __( 'Save round', 'kdna-launchpad' ) : __( 'Create round', 'kdna-launchpad' ) ); ?>
		<?php endif; ?>
	</form>
</div>
