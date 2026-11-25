/**
 * KDNA Launchpad front end: keeps the "Applications close in ..." countdown, and any
 * time left shown with [launchpad_info] or the Elementor dynamic tag, up to date.
 *
 * Shows days, then hours, then minutes. When the round closes, the countdown
 * says so. The form itself also refuses late submissions on the server.
 */
( function () {
	'use strict';

	var words = window.kdnaLaunchpadCountdown || {};

	/**
	 * Fills a "%d day(s)" style phrase.
	 */
	function phrase( singular, plural, count ) {
		var template = 1 === count ? singular : plural;
		return ( template || '%d' ).replace( '%d', count );
	}

	/**
	 * Time left in plain English.
	 */
	function timeLeft( seconds ) {
		if ( seconds >= 86400 ) {
			return phrase( words.day, words.days, Math.floor( seconds / 86400 ) );
		}
		if ( seconds >= 3600 ) {
			return phrase( words.hour, words.hours, Math.floor( seconds / 3600 ) );
		}
		if ( seconds >= 60 ) {
			return phrase( words.minute, words.minutes, Math.floor( seconds / 60 ) );
		}
		return words.soon || '';
	}

	/**
	 * Updates every countdown on the page.
	 */
	function tick() {
		var now = Math.floor( Date.now() / 1000 );

		document.querySelectorAll( '.kdna-launchpad-countdown[data-closes]' ).forEach( function ( countdown ) {
			var left = parseInt( countdown.getAttribute( 'data-closes' ), 10 ) - now;
			var time = countdown.querySelector( '.kdna-launchpad-countdown__time' );

			if ( left <= 0 ) {
				countdown.classList.add( 'is-closed' );
				countdown.textContent = words.closed || '';
				countdown.removeAttribute( 'data-closes' );
				return;
			}

			if ( time ) {
				time.textContent = timeLeft( left );
			}
		} );

		// The time left on its own, placed anywhere with the [launchpad_info] shortcode or the Elementor dynamic tag.
		document.querySelectorAll( '.kdna-launchpad-time-left[data-closes]' ).forEach( function ( span ) {
			var left = parseInt( span.getAttribute( 'data-closes' ), 10 ) - now;

			if ( left <= 0 ) {
				span.classList.add( 'is-closed' );
				span.textContent = words.closedShort || '';
				span.removeAttribute( 'data-closes' );
				return;
			}

			span.textContent = timeLeft( left );
		} );
	}

	function start() {
		tick();
		setInterval( tick, 15000 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	// Elementor editor: run again when the widget is re-rendered.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/kdna-launchpad-application.default', tick );
		}
	} );
} )();
