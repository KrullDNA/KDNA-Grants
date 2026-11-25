<?php
/**
 * The look of every KDNA Launchpad email: logo, font, colours and footer.
 *
 * Built the way email needs: tables, inline styles and a 600px column, so it holds
 * together in Gmail, Apple Mail and Outlook. The web font (from Google Fonts) loads
 * where the email app allows it, such as Apple Mail and iPhone. Everywhere else,
 * including Gmail and Outlook on Windows, the fallback fonts are used instead.
 *
 * Used for Launchpad's own emails, and for the application form's KDNA Forms
 * notifications (the applicant's confirmation and the "new application" email).
 *
 * @package KDNA_Launchpad
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email design and the HTML wrapper around every email.
 */
class KDNA_Launchpad_Email_Design {

	/**
	 * Admin action that shows a preview of the design in the browser.
	 */
	const PREVIEW_ACTION = 'kdna_launchpad_email_preview';

	/**
	 * Fallback font stacks. Outlook on Windows can't use web fonts, so it always uses these.
	 *
	 * @return array Key => array( label, stack ).
	 */
	public static function fallbacks() {
		return array(
			'sans'  => array( __( 'Sans serif (Arial, Helvetica)', 'kdna-launchpad' ), 'Arial, Helvetica, sans-serif' ),
			'serif' => array( __( 'Serif (Georgia, Times New Roman)', 'kdna-launchpad' ), 'Georgia, \'Times New Roman\', Times, serif' ),
		);
	}

	/**
	 * Default design settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'logo_id'    => 0,
			'logo_width' => 100,
			'logo_align' => 'center',
			'font'       => 'Inter',
			'fallback'   => 'sans',
			'background' => '#f4f4f5',
			'text'       => '#111111',
			'link'       => '#111111',
			'footer'     => "Krull Design & Advertising\nkrulldna.com",
			'heading_ls' => 0,
			'body_ls'    => 0,
		);
	}

	/**
	 * The saved design, with defaults for anything not set.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = kdna_launchpad_get_setting( 'email_design', array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Cleans the design fields posted from the Settings screen.
	 *
	 * @param array $posted Raw posted values (already unslashed).
	 * @return array
	 */
	public static function sanitize( $posted ) {
		$defaults = self::defaults();
		$posted   = is_array( $posted ) ? $posted : array();
		$colour   = function ( $key ) use ( $posted, $defaults ) {
			$value = isset( $posted[ $key ] ) ? sanitize_hex_color( $posted[ $key ] ) : '';
			return $value ? $value : $defaults[ $key ];
		};

		$font = isset( $posted['font'] ) ? preg_replace( '/[^A-Za-z0-9 ]/', '', (string) $posted['font'] ) : '';

		return array(
			'logo_id'    => isset( $posted['logo_id'] ) ? absint( $posted['logo_id'] ) : 0,
			'logo_width' => isset( $posted['logo_width'] ) ? min( 560, max( 40, absint( $posted['logo_width'] ) ) ) : $defaults['logo_width'],
			'logo_align' => isset( $posted['logo_align'] ) && 'left' === $posted['logo_align'] ? 'left' : 'center',
			'font'       => trim( preg_replace( '/\s+/', ' ', $font ) ),
			'fallback'   => isset( $posted['fallback'] ) && array_key_exists( $posted['fallback'], self::fallbacks() ) ? $posted['fallback'] : 'sans',
			'background' => $colour( 'background' ),
			'text'       => $colour( 'text' ),
			'link'       => $colour( 'link' ),
			'footer'     => isset( $posted['footer'] ) ? wp_kses_post( $posted['footer'] ) : '',
			'heading_ls' => isset( $posted['heading_ls'] ) && is_numeric( $posted['heading_ls'] ) ? round( min( 10, max( -5, (float) $posted['heading_ls'] ) ), 2 ) : 0,
			'body_ls'    => isset( $posted['body_ls'] ) && is_numeric( $posted['body_ls'] ) ? round( min( 10, max( -5, (float) $posted['body_ls'] ) ), 2 ) : 0,
		);
	}

	/**
	 * The logo's address: the logo chosen here, otherwise the site logo from the theme. '' for none.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	public static function logo_url( $design ) {
		$id = absint( $design['logo_id'] );

		if ( ! $id ) {
			$id = absint( get_theme_mod( 'custom_logo' ) );
		}

		if ( ! $id ) {
			return '';
		}

		// The original upload, so the logo stays sharp on high-resolution screens. The email sets its display width.
		return (string) wp_get_attachment_url( $id );
	}

	/**
	 * The full font list for CSS: the chosen font first, then the fallbacks.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	public static function font_stack( $design ) {
		$fallbacks = self::fallbacks();
		$stack     = $fallbacks[ isset( $fallbacks[ $design['fallback'] ] ) ? $design['fallback'] : 'sans' ][1];

		return '' !== $design['font'] ? "'" . $design['font'] . "', " . $stack : $stack;
	}

	/**
	 * Google Fonts address for the chosen font, regular and bold. '' for none.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	public static function font_url( $design ) {
		if ( '' === $design['font'] ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $design['font'] ) . ':wght@400;700&display=swap';
	}

	/**
	 * Google Fonts address for the medium weight (500) used by headings. '' for none.
	 *
	 * Kept separate, so a font without a medium weight still loads its regular and bold.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	public static function font_url_medium( $design ) {
		if ( '' === $design['font'] ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $design['font'] ) . ':wght@500&display=swap';
	}

	/**
	 * Wraps an email body in the full designed email.
	 *
	 * @param string $body    The email content as HTML.
	 * @param string $subject Subject line (used as the page title).
	 * @param string $heading Heading shown above the text, or '' for none.
	 * @return string Full HTML document.
	 */
	public static function wrap( $body, $subject, $heading = '' ) {
		$heading = trim( (string) $heading );

		if ( '' !== $heading ) {
			$body = '<h1>' . esc_html( $heading ) . '</h1>' . $body;
		}

		return strtr(
			self::template(),
			array(
				'{subject}' => esc_html( $subject ),
				'{message}' => self::inline_styles( $body ),
			)
		);
	}

	/**
	 * The designed email with {subject} and {message} placeholders, for KDNA Forms.
	 *
	 * @return string
	 */
	public static function template() {
		$design = self::settings();
		$font   = self::font_stack( $design );
		$url    = self::font_url( $design );
		$logo   = self::logo_url( $design );
		$align  = 'center' === $design['logo_align'] ? 'center' : 'left';
		$muted  = '#6b7280';

		$font_link = '';
		if ( $url ) {
			// Hidden from Outlook on Windows, which can't use web fonts and may otherwise fall back to Times New Roman.
			$medium    = self::font_url_medium( $design );
			$font_link = '<!--[if !mso]><!--><link href="' . esc_url( $url ) . '" rel="stylesheet" type="text/css" /><link href="' . esc_url( $medium ) . '" rel="stylesheet" type="text/css" /><style type="text/css">@import url(' . esc_url( $url ) . '); @import url(' . esc_url( $medium ) . ');</style><!--<![endif]-->'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Inside an email, not a page.
		}

		$fallbacks = self::fallbacks();
		$mso_font  = $fallbacks[ isset( $fallbacks[ $design['fallback'] ] ) ? $design['fallback'] : 'sans' ][1];

		if ( $logo ) {
			$header = sprintf(
				'<img src="%1$s" width="%2$d" alt="%3$s" style="display: block; width: %2$dpx; max-width: 100%%; height: auto; border: 0; outline: none; text-decoration: none;%4$s" />',
				esc_url( $logo ),
				absint( $design['logo_width'] ),
				esc_attr( get_bloginfo( 'name' ) ),
				'center' === $align ? ' margin: 0 auto;' : ''
			);
		} else {
			$header = '<span style="font-family: ' . esc_attr( $font ) . '; font-size: 20px; font-weight: 700; color: ' . esc_attr( $design['text'] ) . ';">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}

		$footer = '';
		if ( '' !== trim( $design['footer'] ) ) {
			$footer = '<tr><td style="padding: 24px 8px 0; font-family: ' . esc_attr( $font ) . '; font-size: 13px; line-height: 1.5; color: ' . $muted . '; text-align: center;">'
				. self::footer_html( $design['footer'], $muted )
				. '</td></tr>';
		}

		return '<!DOCTYPE html>'
			. '<html lang="en-AU" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">'
			. '<head>'
			. '<meta charset="UTF-8" />'
			. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
			. '<meta name="x-apple-disable-message-reformatting" />'
			. '<meta name="color-scheme" content="light" />'
			. '<meta name="supported-color-schemes" content="light" />'
			. '<title>{subject}</title>'
			. '<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->'
			. $font_link
			. '<!--[if mso]><style type="text/css">body, table, td, p, a, li, h1, h2, h3, span { font-family: ' . $mso_font . ' !important; }</style><![endif]-->'
			. '<style type="text/css">'
			. 'body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }'
			. 'table { border-collapse: collapse; }'
			. 'img { border: 0; line-height: 100%; outline: none; text-decoration: none; }'
			. '.kdna-email-content p { margin: 0 0 16px; }'
			. '.kdna-email-content a { color: ' . $design['link'] . '; text-decoration: underline; }'
			. '.kdna-email-content h1, .kdna-email-content h2 { margin: 0 0 20px; font-size: 24px; line-height: 1.3; font-weight: 500; letter-spacing: ' . self::letter_spacing( $design ) . '; }'
			. '.kdna-email-content h3 { margin: 0 0 12px; line-height: 1.3; }'
			. '@media only screen and (max-width: 620px) { .kdna-email-card { padding: 28px 22px !important; } }'
			. '</style>'
			. '</head>'
			. '<body style="margin: 0; padding: 0; background-color: ' . esc_attr( $design['background'] ) . ';">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: ' . esc_attr( $design['background'] ) . ';">'
			. '<tr><td align="center" style="padding: 32px 16px;">'
			. '<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px;">'
			. '<tr><td align="' . $align . '" style="padding: 0 8px 24px;">' . $header . '</td></tr>'
			. '<tr><td class="kdna-email-card kdna-email-content" style="background-color: #ffffff; border-radius: 12px; padding: 40px; font-family: ' . esc_attr( $font ) . '; font-size: 16px; line-height: 1.6; letter-spacing: ' . esc_attr( self::letter_spacing( $design, 'body_ls' ) ) . '; color: ' . esc_attr( $design['text'] ) . ';">'
			. '{message}'
			. '</td></tr>'
			. $footer
			. '</table>'
			. '<!--[if mso]></td></tr></table><![endif]-->'
			. '</td></tr></table>'
			. '</body></html>';
	}

	/**
	 * The footer as HTML: simple HTML such as links and bold is allowed, and line breaks are kept.
	 * Links are styled inline to match the footer text.
	 *
	 * @param string $footer Footer text or HTML.
	 * @param string $colour Footer text colour.
	 * @return string
	 */
	private static function footer_html( $footer, $colour ) {
		$html = nl2br( trim( wp_kses_post( (string) $footer ) ) );

		return (string) preg_replace( '/<a (?![^>]*\sstyle=)/i', '<a style="color: ' . esc_attr( $colour ) . '; text-decoration: underline;" ', $html );
	}

	/**
	 * Inline style for headings: 24px, medium weight. Arial (Gmail and Outlook) shows medium as regular.
	 *
	 * @param array $design Design settings.
	 * @return string
	 */
	public static function heading_style( $design ) {
		return 'margin: 0 0 20px; font-family: ' . self::font_stack( $design ) . '; font-size: 24px; line-height: 1.3; font-weight: 500; letter-spacing: ' . self::letter_spacing( $design ) . '; color: ' . $design['text'] . ';';
	}

	/**
	 * Letter spacing for CSS, in pixels, for example "-0.5px". Outlook supports pixels.
	 *
	 * @param array  $design Design settings.
	 * @param string $key    'heading_ls' for headings or 'body_ls' for the body text.
	 * @return string
	 */
	public static function letter_spacing( $design, $key = 'heading_ls' ) {
		$value = isset( $design[ $key ] ) && is_numeric( $design[ $key ] ) ? (float) $design[ $key ] : 0;

		return 0.0 === $value ? 'normal' : rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . 'px';
	}

	/**
	 * Adds inline styles to paragraphs and links, for email apps that ignore the style block.
	 *
	 * @param string $body Email body HTML.
	 * @return string
	 */
	private static function inline_styles( $body ) {
		$design = self::settings();

		$body = preg_replace( '/<p>/i', '<p style="margin: 0 0 16px; letter-spacing: ' . esc_attr( self::letter_spacing( $design, 'body_ls' ) ) . ';">', (string) $body );
		$body = preg_replace( '/<(h1|h2)>/i', '<$1 style="' . esc_attr( self::heading_style( $design ) ) . '">', $body );
		$body = preg_replace( '/<a (?![^>]*\sstyle=)/i', '<a style="color: ' . esc_attr( $design['link'] ) . '; text-decoration: underline;" ', $body );

		return $body;
	}

	/**
	 * Shows a preview of the winner email in the browser, with sample details.
	 */
	public static function preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'kdna-launchpad' ) );
		}

		check_admin_referer( self::PREVIEW_ACTION );

		$type  = isset( $_GET['type'] ) && array_key_exists( sanitize_key( wp_unslash( $_GET['type'] ) ), KDNA_Launchpad_Emails::types() ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'winner';
		$email = KDNA_Launchpad_Emails::render( KDNA_Launchpad_Emails::get_template( $type ), KDNA_Launchpad_Emails::sample_values() );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo self::wrap( $email['body'], $email['subject'], $email['heading'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in wrap().
		exit;
	}
}
