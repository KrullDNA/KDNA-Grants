<?php
/**
 * Export: a round's applications as a CSV file, for records, case studies and follow-up lists.
 *
 * @package KDNA_Grants
 */

// Stop anyone loading this file directly in a browser.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the CSV export.
 */
class KDNA_Grants_Export {

	/**
	 * The rows of a round's CSV: a header row, then one row per application.
	 *
	 * @param array $round Round row.
	 * @return array[]
	 */
	public static function rows( $round ) {
		$criteria = KDNA_Grants_Judging::get_criteria( $round );
		$labels   = KDNA_Grants_Eligibility::flag_labels();

		$header = array(
			__( 'Round', 'kdna-grants' ),
			__( 'Application ID', 'kdna-grants' ),
			__( 'KDNA Forms entry ID', 'kdna-grants' ),
			__( 'Applied', 'kdna-grants' ),
			__( 'First name', 'kdna-grants' ),
			__( 'Last name', 'kdna-grants' ),
			__( 'Email', 'kdna-grants' ),
			__( 'Business name', 'kdna-grants' ),
			__( 'Industry', 'kdna-grants' ),
			__( 'ABN', 'kdna-grants' ),
			__( 'Staff', 'kdna-grants' ),
			__( 'Eligible', 'kdna-grants' ),
			__( 'Eligibility flags', 'kdna-grants' ),
			__( 'Cleared flags', 'kdna-grants' ),
		);

		foreach ( $criteria['items'] as $item ) {
			$header[] = $item['label'];
		}

		$header = array_merge(
			$header,
			array(
				__( 'Total', 'kdna-grants' ),
				__( 'Out of', 'kdna-grants' ),
				__( 'Notes', 'kdna-grants' ),
				__( 'Shortlisted', 'kdna-grants' ),
				__( 'Outcome', 'kdna-grants' ),
				__( 'Email status', 'kdna-grants' ),
			)
		);

		$rows = array( $header );

		foreach ( KDNA_Grants_Applications::get_for_round( $round['id'] ) as $application ) {
			$active  = array();
			$cleared = array();

			foreach ( $application['eligibility_flags'] as $flag ) {
				$text = ( isset( $labels[ $flag['code'] ] ) ? $labels[ $flag['code'] ] : $flag['code'] ) . ': ' . $flag['reason'];

				if ( empty( $flag['cleared'] ) ) {
					$active[] = $text;
				} else {
					$cleared[] = $text . ' (' . KDNA_Grants_Rounds::format( $flag['cleared_at'], false ) . ', ' . $flag['cleared_by'] . ')';
				}
			}

			$row = array(
				$round['name'],
				$application['id'],
				$application['entry_id'] ? $application['entry_id'] : '',
				KDNA_Grants_Rounds::to_site( $application['created_at'], 'Y-m-d H:i' ),
				$application['first_name'],
				$application['last_name'],
				$application['email'],
				$application['business_name'],
				$application['industry'],
				KDNA_Grants_Eligibility::format_abn( $application['abn'] ),
				$application['staff'],
				$application['is_eligible'] ? __( 'Yes', 'kdna-grants' ) : __( 'No', 'kdna-grants' ),
				implode( '; ', $active ),
				implode( '; ', $cleared ),
			);

			foreach ( $criteria['items'] as $item ) {
				$row[] = isset( $application['scores'][ $item['key'] ] ) ? $application['scores'][ $item['key'] ] : '';
			}

			$rows[] = array_merge(
				$row,
				array(
					$application['scores'] ? KDNA_Grants_Judging::total( $application['scores'], $criteria ) : '',
					KDNA_Grants_Judging::max_total( $criteria ),
					$application['notes'],
					$application['shortlisted'] ? __( 'Yes', 'kdna-grants' ) : __( 'No', 'kdna-grants' ),
					ucfirst( $application['outcome'] ),
					KDNA_Grants_Emails::status_label( $application['email_status'] ),
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
		$filename = sanitize_file_name( 'kdna-grants-' . sanitize_title( $round['name'] ) . '-' . wp_date( 'Y-m-d' ) . '.csv' );

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
