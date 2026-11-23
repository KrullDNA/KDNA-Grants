<?php
/**
 * Offers.
 *
 * Variables provided by KDNA_Grants_Admin_Offers::render():
 *
 * @var array[] $offers       Offers to show.
 * @var array   $counts       Status => count.
 * @var array   $labels       Status => label.
 * @var string  $status       Current filter.
 * @var array   $applications Application ID => application.
 * @var array   $rounds       Round ID => round.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_grants_base = admin_url( 'admin.php?page=' . KDNA_Grants_Admin::OFFERS_PAGE );
$kdna_grants_pct  = absint( kdna_grants_get_setting( 'offer_percent', 25 ) );
?>
<div class="wrap kdna-grants-admin kdna-grants-admin--wide">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Offers', 'kdna-grants' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Grants_Admin::render_message(); ?>

	<p class="kdna-grants-card__intro">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: percentage. */
				__( 'Unsuccessful applicants get %d%% off their first brief with KDNA. When someone takes it up, click Accept offer and note what the first brief covers. After that, no further discounts are offered to that client, even if they apply again.', 'kdna-grants' ),
				$kdna_grants_pct
			)
		);
		?>
	</p>

	<ul class="subsubsub kdna-grants-filters">
		<?php
		$kdna_grants_links = array(
			sprintf(
				'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $kdna_grants_base ),
				'' === $status ? ' class="current" aria-current="page"' : '',
				esc_html__( 'All', 'kdna-grants' ),
				array_sum( $counts )
			),
		);
		foreach ( $labels as $kdna_grants_key => $kdna_grants_label ) {
			$kdna_grants_links[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $kdna_grants_key, $kdna_grants_base ) ),
				$status === $kdna_grants_key ? ' class="current" aria-current="page"' : '',
				esc_html( $kdna_grants_label ),
				(int) $counts[ $kdna_grants_key ]
			);
		}
		echo implode( ' |</li>', $kdna_grants_links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		?>
	</ul>

	<?php if ( ! $offers ) : ?>
		<div class="kdna-grants-card kdna-grants-card--empty">
			<p class="kdna-grants-card__intro"><?php esc_html_e( 'No offers here yet. Offers are created when you choose a round\'s winner, and go out in the unsuccessful emails.', 'kdna-grants' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped kdna-grants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Business', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Contact', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Round', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Code', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sent', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'kdna-grants' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'kdna-grants' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'kdna-grants' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $offers as $kdna_grants_offer ) : ?>
					<?php
					$kdna_grants_app   = isset( $applications[ $kdna_grants_offer['application_id'] ] ) ? $applications[ $kdna_grants_offer['application_id'] ] : null;
					$kdna_grants_round = isset( $rounds[ $kdna_grants_offer['round_id'] ] ) ? $rounds[ $kdna_grants_offer['round_id'] ] : null;
					?>
					<tr>
						<td>
							<strong>
								<?php if ( $kdna_grants_app ) : ?>
									<a href="<?php echo esc_url( KDNA_Grants_Admin_Applications::single_url( $kdna_grants_app['id'] ) ); ?>"><?php echo esc_html( $kdna_grants_offer['business_name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $kdna_grants_offer['business_name'] ); ?>
								<?php endif; ?>
							</strong>
							<?php if ( $kdna_grants_offer['is_test'] ) : ?>
								<span class="kdna-grants-badge kdna-grants-badge--test"><?php esc_html_e( 'Test', 'kdna-grants' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $kdna_grants_offer['first_name'] ); ?>
							<span class="kdna-grants-table__sub">
								<?php
								$kdna_grants_contact = $kdna_grants_app
									? KDNA_Grants_Admin_Contact::button( $kdna_grants_app )
									: esc_html( $kdna_grants_offer['email'] );
								echo $kdna_grants_contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
								?>
							</span>
						</td>
						<td><?php echo esc_html( $kdna_grants_round ? $kdna_grants_round['name'] : '' ); ?></td>
						<td><code class="kdna-grants-code"><?php echo esc_html( $kdna_grants_offer['code'] ); ?></code></td>
						<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_offer['issued_at'], false ) ); ?></td>
						<td><?php echo esc_html( KDNA_Grants_Rounds::format( $kdna_grants_offer['expires_at'], false ) ); ?></td>
						<td>
							<?php echo KDNA_Grants_Admin_Offers::badge( $kdna_grants_offer['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
							<?php if ( KDNA_Grants_Offers::STATUS_ACCEPTED === $kdna_grants_offer['status'] ) : ?>
								<span class="kdna-grants-table__sub">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: date, 2: name. */
											__( 'First brief discounted %1$s (%2$s). No further discounts for this client.', 'kdna-grants' ),
											KDNA_Grants_Rounds::format( $kdna_grants_offer['closed_at'], false ),
											$kdna_grants_offer['closed_by']
										)
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( '' !== $kdna_grants_offer['note'] ) : ?>
								<span class="kdna-grants-table__sub kdna-grants-offer-brief"><?php echo esc_html( $kdna_grants_offer['note'] ); ?></span>
							<?php endif; ?>
							<?php if ( $kdna_grants_offer['cap'] ) : ?>
								<span class="kdna-grants-table__sub">
									<?php
									/* translators: %s: cap, for example $3,000. */
									echo esc_html( sprintf( __( 'Capped at %s', 'kdna-grants' ), $kdna_grants_offer['cap'] ) );
									?>
								</span>
							<?php endif; ?>
						</td>
						<td class="kdna-grants-table__actions">
							<?php if ( KDNA_Grants_Offers::STATUS_ACTIVE === $kdna_grants_offer['status'] ) : ?>
								<button type="button" class="button button-primary button-small kdna-grants-offer-accept"
									data-offer="<?php echo esc_attr( $kdna_grants_offer['id'] ); ?>"
									data-business="<?php echo esc_attr( $kdna_grants_offer['business_name'] ); ?>"
									data-code="<?php echo esc_attr( $kdna_grants_offer['code'] ); ?>">
									<?php esc_html_e( 'Accept offer', 'kdna-grants' ); ?>
								</button>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-grants-inline-form" data-confirm="decline">
									<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>" />
									<input type="hidden" name="offer_id" value="<?php echo esc_attr( $kdna_grants_offer['id'] ); ?>" />
									<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Grants_Offers::STATUS_DECLINED ); ?>" />
									<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
									<?php wp_nonce_field( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Mark declined', 'kdna-grants' ); ?></button>
								</form>
							<?php elseif ( in_array( $kdna_grants_offer['status'], array( KDNA_Grants_Offers::STATUS_ACCEPTED, KDNA_Grants_Offers::STATUS_DECLINED ), true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-grants-inline-form" data-confirm="undo">
									<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>" />
									<input type="hidden" name="offer_id" value="<?php echo esc_attr( $kdna_grants_offer['id'] ); ?>" />
									<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Grants_Offers::STATUS_ACTIVE ); ?>" />
									<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
									<?php wp_nonce_field( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Undo', 'kdna-grants' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<dialog class="kdna-grants-dialog" id="kdna-grants-accept-offer" aria-labelledby="kdna-grants-accept-title">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>" />
			<input type="hidden" name="offer_id" value="" />
			<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Grants_Offers::STATUS_ACCEPTED ); ?>" />
			<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
			<?php wp_nonce_field( KDNA_Grants_Admin_Offers::STATUS_ACTION ); ?>

			<h2 id="kdna-grants-accept-title"><?php esc_html_e( 'Accept offer', 'kdna-grants' ); ?></h2>
			<p>
				<strong data-fill="business"></strong>
				<code class="kdna-grants-code" data-fill="code"></code>
			</p>

			<label for="kdna-grants-accept-note" class="kdna-grants-scorecard__label"><?php esc_html_e( 'What does the first brief cover?', 'kdna-grants' ); ?></label>
			<textarea name="note" id="kdna-grants-accept-note" rows="3" class="large-text" required placeholder="<?php esc_attr_e( 'For example: Logo and 5-page website, quoted $8,400, 25% off = $6,300', 'kdna-grants' ); ?>"></textarea>

			<label for="kdna-grants-accept-date" class="kdna-grants-scorecard__label"><?php esc_html_e( 'Date accepted', 'kdna-grants' ); ?></label>
			<input type="date" name="date" id="kdna-grants-accept-date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" />

			<p class="description"><?php esc_html_e( 'Once accepted, this client gets no further discounts: later work is at your standard rates, and they won\'t get a new offer if they apply again.', 'kdna-grants' ); ?></p>

			<div class="kdna-grants-dialog__actions">
				<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'kdna-grants' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Accept offer', 'kdna-grants' ); ?></button>
			</div>
		</form>
	</dialog>
</div>
