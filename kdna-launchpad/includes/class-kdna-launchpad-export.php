<?php
/**
 * Export: a round's applications as a CSV file, for records, case studies and follow-up lists.
 *
 * @package KDNA_Launchpad
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the CSV export.
 */
class KDNA_Launchpad_Export {

	/**
	 * The rows of a round's CSV: a header row, then one row per application.
	 *
	 * @param array $round Round row.
	 * @return array[]
	 */
	public static function rows( $round ) {
		$criteria = KDNA_Launchpad_Judging::get_criteria( $round );
		$labels   = KDNA_Launchpad_Eligibility::flag_labels();

		$header = array(
			__( 'Round', 'kdna-launchpad' ),
			__( 'Application ID', 'kdna-launchpad' ),
			__( 'KDNA Forms entry ID', 'kdna-launchpad' ),
			__( 'Applied', 'kdna-launchpad' ),
			__( 'First name', 'kdna-launchpad' ),
			__( 'Last name', 'kdna-launchpad' ),
			__( 'Email', 'kdna-launchpad' ),
			__( 'Business name', 'kdna-launchpad' ),
			__( 'Industry', 'kdna-launchpad' ),
			__( 'ABN', 'kdna-launchpad' ),
			__( 'Staff', 'kdna-launchpad' ),
			__( 'Eligible', 'kdna-launchpad' ),
			__( 'Eligibility flags', 'kdna-launchpad' ),
			__( 'Cleared flags', 'kdna-launchpad' ),
		);

		foreach ( $criteria['items'] as $item ) {
			$header[] = $item['label'];
		}

		$header = array_merge(
			$header,
			array(
				__( 'Total', 'kdna-launchpad' ),
				__( 'Out of', 'kdna-launchpad' ),
				__( 'Notes', 'kdna-launchpad' ),
				__( 'Shortlisted', 'kdna-launchpad' ),
				__( 'Outcome', 'kdna-launchpad' ),
				__( 'Email status', 'kdna-launchpad' ),
			)
		);

		$rows = array( $header );

		foreach ( KDNA_Launchpad_Applications::get_for_round( $round['id'] ) as $application ) {
			$active  = array();
			$cleared = array();

			foreach ( $application['eligibility_flags'] as $flag ) {
				$text = ( isset( $labels[ $flag['code'] ] ) ? $labels[ $flag['code'] ] : $flag['code'] ) . ': ' . $flag['reason'];

				if ( empty( $flag['cleared'] ) ) {
					$active[] = $text;
				} else {
					$cleared[] = $text . ' (' . KDNA_Launchpad_Rounds::format( $flag['cleared_at'], false ) . ', ' . $flag['cleared_by'] . ')';
				}
			}

			$row = array(
				$round['name'],
				$application['id'],
				$application['entry_id'] ? $application['entry_id'] : '',
				KDNA_Launchpad_Rounds::to_site( $application['created_at'], 'Y-m-d H:i' ),
				$application['first_name'],
				$application['last_name'],
				$application['email'],
				$application['business_name'],
				$application['industry'],
				KDNA_Launchpad_Eligibility::format_abn( $application['abn'] ),
				$application['staff'],
				$application['is_eligible'] ? __( 'Yes', 'kdna-launchpad' ) : __( 'No', 'kdna-launchpad' ),
				implode( '; ', $active ),
				implode( '; ', $cleared ),
			);

			foreach ( $criteria['items'] as $item ) {
				$row[] = isset( $application['scores'][ $item['key'] ] ) ? $application['scores'][ $item['key'] ] : '';
			}

			$rows[] = array_merge(
				$row,
				array(
					$application['scores'] ? KDNA_Launchpad_Judging::total( $application['scores'], $criteria ) : '',
					KDNA_Launchpad_Judging::max_total( $criteria ),
					$application['notes'],
					$application['shortlisted'] ? __( 'Yes', 'kdna-launchpad' ) : __( 'No', 'kdna-launchpad' ),
					ucfirst( $application['outcome'] ),
					KDNA_Launchpad_Emails::status_label( $application['email_status'] ),
				)
			);
		}

		return $rows;
	}

	/**
	 * Sends a round's CSV to the browser as a download, then stops.
	 *
	 * @param array $round Round row.
	 */
	public static function download( $round ) {
		$filename = sanitize_file_name( 'kdna-launchpad-' . sanitize_title( $round['name'] ) . '-' . wp_date( 'Y-m-d' ) . '.csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// Byte order mark, so Excel opens accented characters correctly.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		foreach ( self::rows( $round ) as $row ) {
			fputcsv( $output, array_map( array( __CLASS__, 'safe_cell' ), $row ), ',', '"', '\\' );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Stops spreadsheet apps treating an applicant's answer as a formula.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
