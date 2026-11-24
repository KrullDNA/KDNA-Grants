<?php
/**
 * Offers.
 *
 * Variables provided by KDNA_Launchpad_Admin_Offers::render():
 *
 * @var array[] $offers       Offers to show.
 * @var array   $counts       Status => count.
 * @var array   $labels       Status => label.
 * @var string  $status       Current filter.
 * @var array   $applications Application ID => application.
 * @var array   $rounds       Round ID => round.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kdna_launchpad_base = admin_url( 'admin.php?page=' . KDNA_Launchpad_Admin::OFFERS_PAGE );
$kdna_launchpad_pct  = absint( kdna_launchpad_get_setting( 'offer_percent', 25 ) );
?>
<div class="wrap kdna-launchpad-admin kdna-launchpad-admin--wide">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Offers', 'kdna-launchpad' ); ?></h1>
	<hr class="wp-header-end" />

	<?php KDNA_Launchpad_Admin::render_message(); ?>

	<p class="kdna-launchpad-card__intro">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: percentage. */
				__( 'Unsuccessful applicants get %d%% off their first brief with KDNA. When someone takes it up, click Accept offer and note what the first brief covers. After that, no further discounts are offered to that client, even if they apply again.', 'kdna-launchpad' ),
				$kdna_launchpad_pct
			)
		);
		?>
	</p>

	<ul class="subsubsub kdna-launchpad-filters">
		<?php
		$kdna_launchpad_links = array(
			sprintf(
				'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $kdna_launchpad_base ),
				'' === $status ? ' class="current" aria-current="page"' : '',
				esc_html__( 'All', 'kdna-launchpad' ),
				array_sum( $counts )
			),
		);
		foreach ( $labels as $kdna_launchpad_key => $kdna_launchpad_label ) {
			$kdna_launchpad_links[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( add_query_arg( 'status', $kdna_launchpad_key, $kdna_launchpad_base ) ),
				$status === $kdna_launchpad_key ? ' class="current" aria-current="page"' : '',
				esc_html( $kdna_launchpad_label ),
				(int) $counts[ $kdna_launchpad_key ]
			);
		}
		echo implode( ' |</li>', $kdna_launchpad_links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		?>
	</ul>

	<?php if ( ! $offers ) : ?>
		<div class="kdna-launchpad-card kdna-launchpad-card--empty">
			<p class="kdna-launchpad-card__intro"><?php esc_html_e( 'No offers here yet. Offers are created when you choose a round\'s winner, and go out in the unsuccessful emails.', 'kdna-launchpad' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped kdna-launchpad-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Business', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Contact', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Round', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Code', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sent', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Expires', 'kdna-launchpad' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'kdna-launchpad' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'kdna-launchpad' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $offers as $kdna_launchpad_offer ) : ?>
					<?php
					$kdna_launchpad_app   = isset( $applications[ $kdna_launchpad_offer['application_id'] ] ) ? $applications[ $kdna_launchpad_offer['application_id'] ] : null;
					$kdna_launchpad_round = isset( $rounds[ $kdna_launchpad_offer['round_id'] ] ) ? $rounds[ $kdna_launchpad_offer['round_id'] ] : null;
					?>
					<tr>
						<td>
							<strong>
								<?php if ( $kdna_launchpad_app ) : ?>
									<a href="<?php echo esc_url( KDNA_Launchpad_Admin_Applications::single_url( $kdna_launchpad_app['id'] ) ); ?>"><?php echo esc_html( $kdna_launchpad_offer['business_name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $kdna_launchpad_offer['business_name'] ); ?>
								<?php endif; ?>
							</strong>
							<?php if ( $kdna_launchpad_offer['is_test'] ) : ?>
								<span class="kdna-launchpad-badge kdna-launchpad-badge--test"><?php esc_html_e( 'Test', 'kdna-launchpad' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $kdna_launchpad_offer['first_name'] ); ?>
							<span class="kdna-launchpad-table__sub">
								<?php
								$kdna_launchpad_contact = $kdna_launchpad_app
									? KDNA_Launchpad_Admin_Contact::button( $kdna_launchpad_app )
									: esc_html( $kdna_launchpad_offer['email'] );
								echo $kdna_launchpad_contact; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
								?>
							</span>
						</td>
						<td><?php echo esc_html( $kdna_launchpad_round ? $kdna_launchpad_round['name'] : '' ); ?></td>
						<td><code class="kdna-launchpad-code"><?php echo esc_html( $kdna_launchpad_offer['code'] ); ?></code></td>
						<td><?php echo esc_html( KDNA_Launchpad_Rounds::format( $kdna_launchpad_offer['issued_at'], false ) ); ?></td>
						<td><?php echo esc_html( KDNA_Launchpad_Rounds::format( $kdna_launchpad_offer['expires_at'], false ) ); ?></td>
						<td>
							<?php echo KDNA_Launchpad_Admin_Offers::badge( $kdna_launchpad_offer['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
							<?php if ( KDNA_Launchpad_Offers::STATUS_ACCEPTED === $kdna_launchpad_offer['status'] ) : ?>
								<span class="kdna-launchpad-table__sub">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: date, 2: name. */
											__( 'First brief discounted %1$s (%2$s). No further discounts for this client.', 'kdna-launchpad' ),
											KDNA_Launchpad_Rounds::format( $kdna_launchpad_offer['closed_at'], false ),
											$kdna_launchpad_offer['closed_by']
										)
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( '' !== $kdna_launchpad_offer['note'] ) : ?>
								<span class="kdna-launchpad-table__sub kdna-launchpad-offer-brief"><?php echo esc_html( $kdna_launchpad_offer['note'] ); ?></span>
							<?php endif; ?>
							<?php if ( $kdna_launchpad_offer['cap'] ) : ?>
								<span class="kdna-launchpad-table__sub">
									<?php
									/* translators: %s: cap, for example $3,000. */
									echo esc_html( sprintf( __( 'Capped at %s', 'kdna-launchpad' ), $kdna_launchpad_offer['cap'] ) );
									?>
								</span>
							<?php endif; ?>
						</td>
						<td class="kdna-launchpad-table__actions">
							<?php if ( KDNA_Launchpad_Offers::STATUS_ACTIVE === $kdna_launchpad_offer['status'] ) : ?>
								<button type="button" class="button button-primary button-small kdna-launchpad-offer-accept"
									data-offer="<?php echo esc_attr( $kdna_launchpad_offer['id'] ); ?>"
									data-business="<?php echo esc_attr( $kdna_launchpad_offer['business_name'] ); ?>"
									data-code="<?php echo esc_attr( $kdna_launchpad_offer['code'] ); ?>">
									<?php esc_html_e( 'Accept offer', 'kdna-launchpad' ); ?>
								</button>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-launchpad-inline-form" data-confirm="decline">
									<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>" />
									<input type="hidden" name="offer_id" value="<?php echo esc_attr( $kdna_launchpad_offer['id'] ); ?>" />
									<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Launchpad_Offers::STATUS_DECLINED ); ?>" />
									<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
									<?php wp_nonce_field( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Mark declined', 'kdna-launchpad' ); ?></button>
								</form>
							<?php elseif ( in_array( $kdna_launchpad_offer['status'], array( KDNA_Launchpad_Offers::STATUS_ACCEPTED, KDNA_Launchpad_Offers::STATUS_DECLINED ), true ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kdna-launchpad-inline-form" data-confirm="undo">
									<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>" />
									<input type="hidden" name="offer_id" value="<?php echo esc_attr( $kdna_launchpad_offer['id'] ); ?>" />
									<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Launchpad_Offers::STATUS_ACTIVE ); ?>" />
									<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
									<?php wp_nonce_field( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>
									<button type="submit" class="button-link"><?php esc_html_e( 'Undo', 'kdna-launchpad' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<dialog class="kdna-launchpad-dialog" id="kdna-launchpad-accept-offer" aria-labelledby="kdna-launchpad-accept-title">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>" />
			<input type="hidden" name="offer_id" value="" />
			<input type="hidden" name="status" value="<?php echo esc_attr( KDNA_Launchpad_Offers::STATUS_ACCEPTED ); ?>" />
			<input type="hidden" name="back" value="<?php echo esc_attr( $status ); ?>" />
			<?php wp_nonce_field( KDNA_Launchpad_Admin_Offers::STATUS_ACTION ); ?>

			<h2 id="kdna-launchpad-accept-title"><?php esc_html_e( 'Accept offer', 'kdna-launchpad' ); ?></h2>
			<p>
				<strong data-fill="business"></strong>
				<code class="kdna-launchpad-code" data-fill="code"></code>
			</p>

			<label for="kdna-launchpad-accept-note" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'What does the first brief cover?', 'kdna-launchpad' ); ?></label>
			<textarea name="note" id="kdna-launchpad-accept-note" rows="3" class="large-text" required placeholder="<?php esc_attr_e( 'For example: Logo and 5-page website, quoted $8,400, 25% off = $6,300', 'kdna-launchpad' ); ?>"></textarea>

			<label for="kdna-launchpad-accept-date" class="kdna-launchpad-scorecard__label"><?php esc_html_e( 'Date accepted', 'kdna-launchpad' ); ?></label>
			<input type="date" name="date" id="kdna-launchpad-accept-date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" />

			<p class="description"><?php esc_html_e( 'Once accepted, this client gets no further discounts: later work is at your standard rates, and they won\'t get a new offer if they apply again.', 'kdna-launchpad' ); ?></p>

			<div class="kdna-launchpad-dialog__actions">
				<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'kdna-launchpad' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Accept offer', 'kdna-launchpad' ); ?></button>
			</div>
		</form>
	</dialog>
</div>
