<?php
/**
 * An application's email status, with a Resend button when it failed.
 *
 * Variables: $kdna_grants_app (application row). Optional $kdna_grants_from ('single').
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_status = (string) $kdna_grants_app['email_status'];

if ( '' === $kdna_grants_status ) {
	echo '<span class="kdna-grants-muted" aria-hidden="true">&ndash;</span>';
	return;
}
?>
<span class="kdna-grants-email kdna-grants-email--<?php echo esc_attr( $kdna_grants_status ); ?>">
	<?php echo esc_html( KDNA_Grants_Emails::status_label( $kdna_grants_status ) ); ?>
</span>
<?php if ( KDNA_Grants_Emails::STATUS_FAILED === $kdna_grants_status && current_user_can( 'manage_options' ) ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-grants-inline-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Applications::RESEND_ACTION ); ?>" />
		<input type="hidden" name="application_id" value="<?php echo esc_attr( $kdna_grants_app['id'] ); ?>" />
		<input type="hidden" name="from" value="<?php echo esc_attr( isset( $kdna_grants_from ) ? $kdna_grants_from : '' ); ?>" />
		<?php wp_nonce_field( KDNA_Grants_Admin_Applications::RESEND_ACTION ); ?>
		<button type="submit" class="button-link"><?php esc_html_e( 'Resend', 'kdna-grants' ); ?></button>
	</form>
<?php endif; ?>
