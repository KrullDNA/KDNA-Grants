<?php
/**
 * Rounds screen.
 *
 * Variables provided by KDNA_Grants_Admin_Rounds::render():
 *
 * @var array[]    $rounds      Every round, newest first.
 * @var array      $counts      Round ID => number of applications.
 * @var array|null $open_round  The round open right now.
 * @var bool       $has_rounds  Whether any real round exists.
 * @var bool       $test_exists Whether test rounds exist.
 * @var array      $winners     Round ID => winning application.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_new_url = add_query_arg(
	array(
		'page' => KDNA_Grants_Admin::ROUNDS_PAGE,
		'view' => 'edit',
	),
	admin_url( 'admin.php' )
);
?>
<div class="wrap kdna-grants-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Rounds', 'kdna-grants' ); ?></h1>
	<a href="<?php echo esc_url( $kdna_grants_new_url ); ?>" class="page-title-action">
		<?php echo $has_rounds ? esc_html__( 'Create next round', 'kdna-grants' ) : esc_html__( 'Create first round', 'kdna-grants' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php KDNA_Grants_Admin::render_message(); ?>

	<div class="kdna-grants-admin__summary">
		<?php if ( $open_round ) : ?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: round name, 2: closing date and time. */
						__( '<strong>%1$s</strong> is open for applications until %2$s.', 'kdna-grants' ),
						esc_html( $open_round['name'] ),
						esc_html( KDNA_Grants_Rounds::format( $open_round['closes_at'] ) )
					),
					array( 'strong' => array() )
				);
				?>
			</p>
		<?php else : ?>
			<p>
				<?php
				$kdna_grants_next = KDNA_Grants_Rounds::get_next_round();
				if ( $kdna_grants_next ) {
					echo esc_html(
						sprintf(
							/* translators: 1: round name, 2: opening date and time. */
							__( 'No round is open right now. %1$s opens on %2$s.', 'kdna-grants' ),
							$kdna_grants_next['name'],
							KDNA_Grants_Rounds::format( $kdna_grants_next['opens_at'] )
						)
					);
				} else {
					esc_html_e( 'No round is open right now, and none is scheduled.', 'kdna-grants' );
				}
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! $rounds ) : ?>
		<div class="kdna-grants-card kdna-grants-card--empty">
			<h2 class="kdna-grants-card__title"><?php esc_html_e( 'No rounds yet', 'kdna-grants' ); ?></h2>
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'A round is one application period. The form opens and closes on the round\'s dates, and every application is filed under it.', 'kdna-grants' ); ?></p>
			<a href="<?php echo esc_url( $kdna_grants_new_url ); ?>" class="button button-primary"><?php esc_html_e( 'Create first round', 'kdna-grants' ); ?></a>
		</div>
	<?php else : ?>
		<table class="widefat striped kdna-grants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Round', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Opens', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Closes', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Winner announced by', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'kdna-grants' ); ?></th>
					<th scope="col" class="kdna-grants-table__num"><?php esc_html_e( 'Applications', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Winner', 'kdna-grants' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'kdna-grants' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rounds as $kdna_grants_round ) : ?>
					<?php
					$kdna_grants_count    = isset( $counts[ $kdna_grants_round['id'] ] ) ? $counts[ $kdna_grants_round['id'] ] : 0;
					$kdna_grants_apps_url = add_query_arg(
						array(
							'page'  => KDNA_Grants_Admin::APPLICATIONS_PAGE,
							'round' => $kdna_grants_round['id'],
						),
						admin_url( 'admin.php' )
					);
					$kdna_grants_edit_url = add_query_arg(
						array(
							'page'  => KDNA_Grants_Admin::ROUNDS_PAGE,
							'view'  => 'edit',
							'round' => $kdna_grants_round['id'],
						),
						admin_url( 'admin.php' )
					);
					$kdna_grants_winner   = isset( $winners[ $kdna_grants_round['id'] ] ) ? $winners[ $kdna_grants_round['id'] ] : null;
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $kdna_grants_apps_url ); ?>"><?php echo esc_html( $kdna_grants_round['name'] ); ?></a></strong>
							<?php if ( $kdna_grants_round['is_test'] ) : ?>
								<span class="kdna-grants-badge kdna-grants-badge--test"><?php esc_html_e( 'Test', 'kdna-grants' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_round['opens_at'] ) ); ?></td>
						<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_round['closes_at'] ) ); ?></td>
						<td><?php echo esc_html( KDNA_Grants_Rounds::format_date( $kdna_grants_round['announce_date'] ) ); ?></td>
						<td><?php echo KDNA_Grants_Admin::status_badge( $kdna_grants_round['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?></td>
						<td class="kdna-grants-table__num"><a href="<?php echo esc_url( $kdna_grants_apps_url ); ?>"><?php echo esc_html( number_format_i18n( $kdna_grants_count ) ); ?></a></td>
						<td><?php echo $kdna_grants_winner ? esc_html( $kdna_grants_winner['business_name'] ) : '<span aria-hidden="true">&ndash;</span>'; ?></td>
						<td class="kdna-grants-table__actions">
							<a href="<?php echo esc_url( $kdna_grants_apps_url ); ?>"><?php esc_html_e( 'Applications', 'kdna-grants' ); ?></a>
							<?php if ( $kdna_grants_count ) : ?>
								<a href="<?php echo esc_url( KDNA_Grants_Admin_Rounds::export_url( $kdna_grants_round['id'] ) ); ?>"><?php esc_html_e( 'Export CSV', 'kdna-grants' ); ?></a>
							<?php endif; ?>
							<?php if ( ! $kdna_grants_round['is_test'] && KDNA_Grants_Rounds::STATUS_DECIDED !== $kdna_grants_round['status'] ) : ?>
								<a href="<?php echo esc_url( $kdna_grants_edit_url ); ?>"><?php esc_html_e( 'Edit', 'kdna-grants' ); ?></a>
							<?php endif; ?>
							<?php if ( ! $kdna_grants_round['is_test'] && 0 === $kdna_grants_count ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-grants-inline-form" data-confirm="round">
									<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Rounds::DELETE_ACTION ); ?>" />
									<input type="hidden" name="round_id" value="<?php echo esc_attr( $kdna_grants_round['id'] ); ?>" />
									<?php wp_nonce_field( KDNA_Grants_Admin_Rounds::DELETE_ACTION ); ?>
									<button type="submit" class="button-link kdna-grants-link-danger"><?php esc_html_e( 'Delete', 'kdna-grants' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<!-- Test tool -->
	<div class="kdna-grants-card kdna-grants-card--test">
		<h2 class="kdna-grants-card__title"><?php esc_html_e( 'Test tool', 'kdna-grants' ); ?></h2>
		<p class="kdna-grants-card__intro">
			<?php esc_html_e( 'Creates a test round with 6 sample applications, so you can check the eligibility logic without real applicants: one with no flags, then one each for ABN, Staff, Existing client, Duplicate and Past winner. A second, already decided test round holds the "past winner".', 'kdna-grants' ); ?>
		</p>
		<p class="kdna-grants-card__intro">
			<?php esc_html_e( 'Test rounds never open for real applications and are never compared with real ones. If the grant form is set up in Settings, matching sample entries are added to it in KDNA Forms (without sending any emails), and are removed again when you delete the test rounds.', 'kdna-grants' ); ?>
		</p>

		<?php if ( $test_exists ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="test">
				<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Rounds::DELETE_TEST_ACTION ); ?>" />
				<?php wp_nonce_field( KDNA_Grants_Admin_Rounds::DELETE_TEST_ACTION ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Delete test rounds', 'kdna-grants' ); ?></button>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Rounds::CREATE_TEST_ACTION ); ?>" />
				<?php wp_nonce_field( KDNA_Grants_Admin_Rounds::CREATE_TEST_ACTION ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Create test round', 'kdna-grants' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
