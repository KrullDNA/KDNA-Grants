<?php
/**
 * An application's email status, with a Resend button when it failed.
 *
 * Variables: $kdna_launchpad_app (application row). Optional $kdna_launchpad_from ('single').
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_status = (string) $kdna_launchpad_app['email_status'];

if ( '' === $kdna_launchpad_status ) {
	echo '<span class="kdna-launchpad-muted" aria-hidden="true">&ndash;</span>';
	return;
}
?>
<span class="kdna-launchpad-email kdna-launchpad-email--<?php echo esc_attr( $kdna_launchpad_status ); ?>">
	<?php echo esc_html( KDNA_Launchpad_Emails::status_label( $kdna_launchpad_status ) ); ?>
</span>
<?php if ( KDNA_Launchpad_Emails::STATUS_FAILED === $kdna_launchpad_status && current_user_can( 'manage_options' ) ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-launchpad-inline-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Applications::RESEND_ACTION ); ?>" />
		<input type="hidden" name="application_id" value="<?php echo esc_attr( $kdna_launchpad_app['id'] ); ?>" />
		<input type="hidden" name="from" value="<?php echo esc_attr( isset( $kdna_launchpad_from ) ? $kdna_launchpad_from : '' ); ?>" />
		<?php wp_nonce_field( KDNA_Launchpad_Admin_Applications::RESEND_ACTION ); ?>
		<button type="submit" class="button-link"><?php esc_html_e( 'Resend', 'kdna-launchpad' ); ?></button>
	</form>
<?php endif; ?>
