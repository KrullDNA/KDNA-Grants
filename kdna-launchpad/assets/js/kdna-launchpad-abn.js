/**
 * KDNA Launchpad: the live ABN check on the application form.
 *
 * As soon as 11 digits pass the official checksum, the ABN is looked up on the
 * Australian Business Register (when ABN Lookup is set up). The business name
 * fills itself in, registered trading names are offered for "Trading as", and a
 * short confirmation shows under the ABN. Anything the applicant typed into the
 * business name themselves is never replaced without asking.
 *
 * Works however the form is shown, including inside an Elementor template, and
 * again after KDNA Forms redraws the form (for example after a validation error).
 */
( function () {
	'use strict';

	var config = window.kdnaLaunchpadAbn;

	if ( ! config || ! config.abn ) {
		return;
	}

	var i18n = config.i18n || {};

	/**
	 * The official ABN checksum: take 1 from the first digit, weight the digits
	 * 10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19, and the total must divide by 89.
	 */
	function isValidAbn( digits ) {
		var weights = [ 10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19 ];
		var total = 0;

		if ( ! /^\d{11}$/.test( digits ) || '0' === digits.charAt( 0 ) ) {
			return false;
		}

		for ( var i = 0; i < 11; i++ ) {
			total += ( parseInt( digits.charAt( i ), 10 ) - ( 0 === i ? 1 : 0 ) ) * weights[ i ];
		}

		return 0 === total % 89;
	}

	/**
	 * Sets up one form's ABN field. Safe to call again: each field is only set up once.
	 */
	function setUp() {
		var abn = document.getElementById( config.abn );
		var business = config.business ? document.getElementById( config.business ) : null;
		var trading = config.trading ? document.getElementById( config.trading ) : null;

		if ( ! abn || abn.getAttribute( 'data-kdna-abn' ) ) {
			return;
		}
		abn.setAttribute( 'data-kdna-abn', '1' );

		// The message under the ABN field.
		var status = document.createElement( 'div' );
		status.className = 'kdna-launchpad-abn-status';
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		abn.insertAdjacentElement( 'afterend', status );

		// Registered trading names, offered as suggestions for "Trading as".
		var suggestions = null;
		if ( trading ) {
			suggestions = document.createElement( 'datalist' );
			suggestions.id = config.trading + '_suggestions';
			trading.insertAdjacentElement( 'afterend', suggestions );
			trading.setAttribute( 'list', suggestions.id );
		}

		var lastDigits = '';
		var filledByLookup = false;
		var request = 0;

		var setStatus = function ( text, type ) {
			status.textContent = text || '';
			status.className = 'kdna-launchpad-abn-status' + ( type ? ' is-' + type : '' );
		};

		var setPlaceholder = function ( text ) {
			if ( business ) {
				business.setAttribute( 'placeholder', text );
			}
		};

		var setBusy = function ( busy ) {
			if ( ! business ) {
				return;
			}
			business.readOnly = busy;
			business.parentNode.classList.toggle( 'kdna-launchpad-is-looking', busy );
		};

		// Remember when the applicant types their own business name.
		if ( business ) {
			business.addEventListener( 'input', function () {
				filledByLookup = false;
			} );
			if ( '' === abn.value.trim() && '' === business.value ) {
				setPlaceholder( config.lookup ? i18n.enterAbn : i18n.typeName );
			}
		}

		var check = function () {
			var digits = abn.value.replace( /\D/g, '' );

			if ( digits === lastDigits ) {
				return;
			}
			lastDigits = digits;
			request++;

			if ( digits.length < 11 ) {
				setStatus( '' );
				setBusy( false );
				if ( business && '' === business.value ) {
					setPlaceholder( config.lookup ? i18n.enterAbn : i18n.typeName );
				}
				return;
			}

			if ( ! isValidAbn( digits ) ) {
				setStatus( i18n.invalid, 'error' );
				setPlaceholder( i18n.typeName );
				return;
			}

			if ( ! config.lookup ) {
				setStatus( '✓ ' + i18n.valid, 'ok' );
				setPlaceholder( i18n.typeName );
				return;
			}

			var mine = request;
			setStatus( i18n.checking, 'busy' );
			setPlaceholder( i18n.looking );
			setBusy( true );

			var body = new URLSearchParams();
			body.append( 'action', 'kdna_launchpad_abn' );
			body.append( 'nonce', config.nonce );
			body.append( 'abn', digits );

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( response ) {
				if ( mine !== request ) {
					return; // The ABN changed while we were looking.
				}
				setBusy( false );

				if ( ! response.success ) {
					var code = response.data && response.data.code;
					if ( 'invalid' === code || 'not_found' === code ) {
						setStatus( response.data.message, 'error' );
					} else {
						// The lookup isn't available: the checksum passed, so carry on.
						setStatus( '✓ ' + i18n.valid, 'ok' );
					}
					setPlaceholder( i18n.typeName );
					return;
				}

				var result = response.data;

				if ( 'active' !== result.status ) {
					setStatus( i18n.cancelled, 'error' );
					setPlaceholder( i18n.typeName );
					return;
				}

				setStatus( '✓ ' + result.summary, 'ok' );

				if ( business && result.entity_name ) {
					var typed = business.value.trim();
					// eslint-disable-next-line no-alert
					if ( '' === typed || filledByLookup || ( typed !== result.entity_name && window.confirm( i18n.replace ) ) ) {
						business.value = result.entity_name;
						filledByLookup = true;
						business.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				}

				if ( suggestions ) {
					suggestions.innerHTML = '';
					( result.business_names || [] ).forEach( function ( name ) {
						var option = document.createElement( 'option' );
						option.value = name;
						suggestions.appendChild( option );
					} );
				}
			} ).catch( function () {
				if ( mine === request ) {
					setBusy( false );
					setStatus( '✓ ' + i18n.valid, 'ok' );
					setPlaceholder( i18n.typeName );
				}
			} );
		};

		abn.addEventListener( 'input', check );
		abn.addEventListener( 'blur', check );

		if ( abn.value ) {
			check();
		}
	}

	/**
	 * Adds a live word count under the longer answers, for example "120 of 250 words".
	 */
	function setUpWordCounts() {
		Object.keys( config.words || {} ).forEach( function ( id ) {
			var field = document.getElementById( id );
			var limit = config.words[ id ];

			if ( ! field || field.getAttribute( 'data-kdna-words' ) ) {
				return;
			}
			field.setAttribute( 'data-kdna-words', '1' );

			var counter = document.createElement( 'div' );
			counter.className = 'kdna-launchpad-word-count';
			counter.setAttribute( 'aria-live', 'polite' );
			field.insertAdjacentElement( 'afterend', counter );

			var update = function () {
				var text = field.value.trim();
				var count = '' === text ? 0 : text.split( /\s+/ ).length;
				counter.textContent = ( i18n.words || '%1$d of %2$d words' ).replace( '%1$d', count ).replace( '%2$d', limit );
				counter.classList.toggle( 'is-over', count > limit );
			};

			field.addEventListener( 'input', update );
			update();
		} );
	}

	function start() {
		setUp();
		setUpWordCounts();

		// KDNA Forms redraws the form after an AJAX submission; set up the new fields too.
		new MutationObserver( function () {
			setUp();
			setUpWordCounts();
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
