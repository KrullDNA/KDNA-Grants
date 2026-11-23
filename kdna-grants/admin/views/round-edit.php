<?php
/**
 * Round editor, for a new round or an existing one.
 *
 * Variables provided by KDNA_Grants_Admin_Rounds::render_edit():
 *
 * @var array|null $round     The round being edited, or null for a new round.
 * @var array      $values    name, opens, closes (Y-m-d\TH:i), announce (Y-m-d).
 * @var string[]   $errors    Problems from the last attempt to save.
 * @var bool       $is_locked Whether the round is decided and can't be changed.
 * @var string     $timezone  The site's time zone.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_back = add_query_arg( 'page', KDNA_Grants_Admin::ROUNDS_PAGE, admin_url( 'admin.php' ) );
?>
<div class="wrap kdna-grants-admin">
	<h1><?php echo $round ? esc_html__( 'Edit round', 'kdna-grants' ) : esc_html__( 'Create round', 'kdna-grants' ); ?></h1>
	<p><a href="<?php echo esc_url( $kdna_grants_back ); ?>">&larr; <?php esc_html_e( 'Back to Rounds', 'kdna-grants' ); ?></a></p>

	<?php if ( $errors ) : ?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'The round wasn\'t saved:', 'kdna-grants' ); ?></strong></p>
			<ul class="kdna-grants-list">
				<?php foreach ( $errors as $kdna_grants_error ) : ?>
					<li><?php echo esc_html( $kdna_grants_error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $is_locked ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'This round has been decided, so it can no longer be changed.', 'kdna-grants' ); ?></p></div>
	<?php elseif ( $round && KDNA_Grants_Rounds::STATUS_OPEN === $round['status'] ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'This round is open right now. Changing the dates takes effect straight away.', 'kdna-grants' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Rounds::SAVE_ACTION ); ?>" />
		<input type="hidden" name="round_id" value="<?php echo esc_attr( $round ? $round['id'] : 0 ); ?>" />
		<?php wp_nonce_field( KDNA_Grants_Admin_Rounds::SAVE_ACTION ); ?>

		<div class="kdna-grants-card">
			<?php if ( $round ) : ?>
				<p class="kdna-grants-card__status">
					<?php esc_html_e( 'Status:', 'kdna-grants' ); ?>
					<?php echo KDNA_Grants_Admin::status_badge( $round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
				</p>
			<?php else : ?>
				<p class="kdna-grants-card__intro"><?php esc_html_e( 'The dates are filled in for you, following on from the last round. Change anything you need to.', 'kdna-grants' ); ?></p>
			<?php endif; ?>

			<div class="kdna-grants-row">
				<label for="kdna-grants-round-name" class="kdna-grants-row__label"><?php esc_html_e( 'Round name', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="text" name="name" id="kdna-grants-round-name" class="regular-text" value="<?php echo esc_attr( $values['name'] ); ?>" <?php disabled( $is_locked ); ?> />
					<p class="description"><?php esc_html_e( 'Leave blank to name it automatically, for example "Grant 4 April to 4 May 2026".', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-round-opens" class="kdna-grants-row__label"><?php esc_html_e( 'Opens', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="datetime-local" name="opens" id="kdna-grants-round-opens" value="<?php echo esc_attr( $values['opens'] ); ?>" required <?php disabled( $is_locked ); ?> />
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-round-closes" class="kdna-grants-row__label"><?php esc_html_e( 'Closes', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="datetime-local" name="closes" id="kdna-grants-round-closes" value="<?php echo esc_attr( $values['closes'] ); ?>" required <?php disabled( $is_locked ); ?> />
				</div>
			</div>

			<div class="kdna-grants-row">
				<label for="kdna-grants-round-announce" class="kdna-grants-row__label"><?php esc_html_e( 'Winner announced by', 'kdna-grants' ); ?></label>
				<div class="kdna-grants-row__control">
					<input type="date" name="announce" id="kdna-grants-round-announce" value="<?php echo esc_attr( $values['announce'] ); ?>" <?php disabled( $is_locked ); ?> />
					<p class="description"><?php esc_html_e( 'Used in the email copy as {announce_date}.', 'kdna-grants' ); ?></p>
				</div>
			</div>

			<p class="description kdna-grants-card__note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: time zone name. */
						__( 'Times are in the site\'s time zone (%s), set in Settings > General. The form opens and closes at exactly these times, checked every time the form is shown or submitted.', 'kdna-grants' ),
						$timezone
					)
				);
				?>
			</p>
		</div>

		<?php if ( ! $is_locked ) : ?>
			<?php submit_button( $round ? __( 'Save round', 'kdna-grants' ) : __( 'Create round', 'kdna-grants' ) ); ?>
		<?php endif; ?>
	</form>
</div>
