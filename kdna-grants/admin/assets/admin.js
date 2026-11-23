/**
 * KDNA Grants admin script.
 *
 * On the Settings screen:
 * - When a different form is picked, the field dropdowns update straight away
 *   with that form's fields, and any unmatched ones are guessed from their labels.
 * - Ticking "Delete all data when the plugin is deleted" asks for confirmation.
 *
 * On every KDNA Grants screen:
 * - The round picker shows the chosen round straight away.
 * - Delete buttons ask for confirmation first.
 *
 * Settings: tabs, the criteria list and "Send test email".
 * Single application: the scorecard saves itself as you work.
 * Applications list: the Choose winner confirmation box.
 */
( function () {
	'use strict';

	var data = window.kdnaGrantsSettings;

	if ( ! data ) {
		return;
	}

	// Words used to guess which form field matches each KDNA Grants field.
	var GUESSES = {
		first_name: [ '(first)', 'first name', 'first' ],
		last_name: [ '(last)', 'last name', 'surname', 'last' ],
		email: [ 'email', 'e-mail' ],
		business_name: [ 'business name', 'company name', 'business', 'company', 'organisation' ],
		industry: [ 'industry', 'sector', 'type of business' ],
		abn: [ 'abn', 'australian business number' ],
		staff: [ 'staff', 'employees', 'team size', 'team' ],
		logo: [ 'logo' ]
	};

	// Round picker: show the chosen round straight away.
	document.querySelectorAll( '.kdna-grants-autosubmit' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			select.form.submit();
		} );
	} );

	// Ask before deleting.
	document.querySelectorAll( 'form[data-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var type = form.getAttribute( 'data-confirm' );
			var messages = {
				test: data.i18n.confirmDeleteTest,
				round: data.i18n.confirmDeleteRound,
				reopen: data.i18n.confirmReopen,
				reassess: data.i18n.confirmReassess,
				decline: data.i18n.confirmDecline,
				undo: data.i18n.confirmUndo
			};
			var message = messages[ type ] || data.i18n.confirmDeleteRound;

			// eslint-disable-next-line no-alert
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	} );

	var formSelect = document.getElementById( 'kdna-grants-form-id' );
	var mapTable = document.getElementById( 'kdna-grants-map' );
	var mapEmpty = document.getElementById( 'kdna-grants-map-empty' );
	var editLink = document.getElementById( 'kdna-grants-edit-form' );
	var selects = document.querySelectorAll( '.kdna-grants-map__select' );
	var deleteBox = document.getElementById( 'kdna-grants-delete-data' );

	/**
	 * Returns the fields of a form that suit a KDNA Grants field.
	 */
	function fieldsFor( formId, target ) {
		var fields = data.fields[ formId ] || [];
		var types = ( data.targets[ target ] && data.targets[ target ].types ) || [];

		return fields.filter( function ( field ) {
			return ! types.length || types.indexOf( field.type ) !== -1;
		} );
	}

	/**
	 * Picks the best guess for a KDNA Grants field from a list of form fields, or ''.
	 */
	function guess( target, fields ) {
		var words = GUESSES[ target ] || [];

		for ( var w = 0; w < words.length; w++ ) {
			for ( var f = 0; f < fields.length; f++ ) {
				if ( fields[ f ].label.toLowerCase().indexOf( words[ w ] ) !== -1 ) {
					return fields[ f ].value;
				}
			}
		}

		return '';
	}

	/**
	 * Marks unmatched dropdowns so they stand out.
	 */
	function markEmpty( select ) {
		select.classList.toggle( 'is-empty', select.value === '' );
	}

	/**
	 * Rebuilds every field dropdown for the chosen form.
	 */
	function rebuild( formId, keepCurrent ) {
		var hasForm = !! data.fields[ formId ];

		mapTable.hidden = ! hasForm;
		mapEmpty.hidden = hasForm;

		if ( editLink ) {
			editLink.hidden = ! hasForm;
			editLink.href = data.editUrl + encodeURIComponent( formId );
		}

		selects.forEach( function ( select ) {
			var target = select.getAttribute( 'data-target' );
			var fields = fieldsFor( formId, target );
			var current = keepCurrent ? select.value : '';

			select.innerHTML = '';

			var placeholder = document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = fields.length ? data.i18n.choose : data.i18n.noFields;
			select.appendChild( placeholder );

			fields.forEach( function ( field ) {
				var option = document.createElement( 'option' );
				option.value = field.value;
				option.textContent = field.label;
				select.appendChild( option );
			} );

			var stillThere = fields.some( function ( field ) {
				return field.value === current;
			} );

			select.value = stillThere ? current : guess( target, fields );
			markEmpty( select );
		} );
	}

	if ( formSelect ) {
		formSelect.addEventListener( 'change', function () {
			rebuild( formSelect.value, false );
		} );

		// On load, keep saved choices and only guess the ones still empty.
		if ( data.fields[ formSelect.value ] ) {
			selects.forEach( function ( select ) {
				if ( select.value === '' ) {
					var target = select.getAttribute( 'data-target' );
					select.value = guess( target, fieldsFor( formSelect.value, target ) );
				}
			} );
		}
	}

	selects.forEach( function ( select ) {
		markEmpty( select );
		select.addEventListener( 'change', function () {
			markEmpty( select );
		} );
	} );

	if ( deleteBox ) {
		deleteBox.addEventListener( 'change', function () {
			// eslint-disable-next-line no-alert
			if ( deleteBox.checked && ! window.confirm( data.i18n.confirmUninstall ) ) {
				deleteBox.checked = false;
			}
		} );
	}
	/*
	 * -------------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------------
	 */

	/**
	 * Sends a form-style request to admin-ajax.php and returns the JSON.
	 */
	function post( params ) {
		var body = new URLSearchParams();
		Object.keys( params ).forEach( function ( key ) {
			body.append( key, params[ key ] );
		} );

		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/*
	 * -------------------------------------------------------------------
	 * Settings tabs
	 * -------------------------------------------------------------------
	 */

	var tabs = document.querySelectorAll( '.kdna-grants-tabs .nav-tab' );
	var tabField = document.getElementById( 'kdna-grants-tab-field' );

	function showTab( name ) {
		var found = false;

		tabs.forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-tab' ) === name;
			tab.classList.toggle( 'nav-tab-active', active );
			found = found || active;
		} );

		if ( ! found ) {
			return showTab( 'form' );
		}

		document.querySelectorAll( '.kdna-grants-tab-panel' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-panel' ) !== name;
		} );

		if ( tabField ) {
			tabField.value = name;
		}
	}

	if ( tabs.length ) {
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				showTab( tab.getAttribute( 'data-tab' ) );
				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#' + tab.getAttribute( 'data-tab' ) );
				}
			} );
		} );

		showTab( window.location.hash.replace( '#', '' ) || 'form' );
	}

	/*
	 * -------------------------------------------------------------------
	 * Criteria list
	 * -------------------------------------------------------------------
	 */

	var criteriaList = document.getElementById( 'kdna-grants-criteria' );
	var tiebreak = document.getElementById( 'kdna-grants-tiebreak' );

	/**
	 * Renumbers the rows so they save in the order shown, and rebuilds the tie-break dropdown.
	 */
	function refreshCriteria() {
		var rows = criteriaList.querySelectorAll( '.kdna-grants-criteria__row' );
		var chosenLabel = tiebreak.selectedIndex >= 0 ? tiebreak.options[ tiebreak.selectedIndex ].getAttribute( 'data-row-label' ) || tiebreak.options[ tiebreak.selectedIndex ].textContent : '';

		tiebreak.innerHTML = '';

		rows.forEach( function ( row, index ) {
			var key = row.querySelector( '.kdna-grants-criteria__key' );
			var label = row.querySelector( '.kdna-grants-criteria__label' );

			key.name = 'criteria[' + index + '][key]';
			label.name = 'criteria[' + index + '][label]';
			label.id = 'kdna-grants-criterion-' + index;

			var option = document.createElement( 'option' );
			option.value = index;
			option.textContent = label.value || data.i18n.newCriterion;
			option.setAttribute( 'data-row-label', label.value );
			if ( label.value === chosenLabel ) {
				option.selected = true;
			}
			tiebreak.appendChild( option );

			row.querySelector( '.kdna-grants-criteria__remove' ).hidden = rows.length <= 1;
		} );
	}

	if ( criteriaList && tiebreak ) {
		criteriaList.addEventListener( 'click', function ( event ) {
			var row = event.target.closest( '.kdna-grants-criteria__row' );
			if ( ! row ) {
				return;
			}

			if ( event.target.closest( '.kdna-grants-criteria__up' ) && row.previousElementSibling ) {
				criteriaList.insertBefore( row, row.previousElementSibling );
			} else if ( event.target.closest( '.kdna-grants-criteria__down' ) && row.nextElementSibling ) {
				criteriaList.insertBefore( row.nextElementSibling, row );
			} else if ( event.target.closest( '.kdna-grants-criteria__remove' ) ) {
				row.remove();
			} else {
				return;
			}

			refreshCriteria();
		} );

		// Keep the tie-break choice when a criterion is renamed.
		criteriaList.addEventListener( 'focusin', function ( event ) {
			if ( event.target.classList.contains( 'kdna-grants-criteria__label' ) ) {
				event.target.setAttribute( 'data-before', event.target.value );
			}
		} );

		criteriaList.addEventListener( 'input', function ( event ) {
			if ( ! event.target.classList.contains( 'kdna-grants-criteria__label' ) ) {
				return;
			}
			var index = Array.prototype.indexOf.call( criteriaList.children, event.target.closest( '.kdna-grants-criteria__row' ) );
			var option = tiebreak.options[ index ];
			if ( option ) {
				option.textContent = event.target.value || data.i18n.newCriterion;
				option.setAttribute( 'data-row-label', event.target.value );
			}
		} );

		document.getElementById( 'kdna-grants-add-criterion' ).addEventListener( 'click', function () {
			var rows = criteriaList.querySelectorAll( '.kdna-grants-criteria__row' );
			if ( rows.length >= 10 ) {
				return;
			}

			var row = rows[ rows.length - 1 ].cloneNode( true );
			row.querySelector( '.kdna-grants-criteria__key' ).value = '';
			row.querySelector( '.kdna-grants-criteria__label' ).value = '';
			criteriaList.appendChild( row );
			refreshCriteria();
			row.querySelector( '.kdna-grants-criteria__label' ).focus();
		} );

		refreshCriteria();
	}

	/*
	 * -------------------------------------------------------------------
	 * Send test email
	 * -------------------------------------------------------------------
	 */

	document.querySelectorAll( '.kdna-grants-send-test' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var type = button.getAttribute( 'data-type' );
			var card = button.closest( '.kdna-grants-email-card' );
			var result = card.querySelector( '.kdna-grants-email-card__result' );
			var editorId = 'kdna_grants_email_' + type;

			// Copy the visual editor's content into its textarea first.
			if ( window.tinymce && window.tinymce.get( editorId ) && ! window.tinymce.get( editorId ).isHidden() ) {
				window.tinymce.get( editorId ).save();
			}

			result.textContent = data.i18n.sending;
			result.className = 'kdna-grants-email-card__result';
			button.disabled = true;

			post( {
				action: 'kdna_grants_test_email',
				nonce: data.testNonce,
				type: type,
				subject: card.querySelector( '.kdna-grants-email-card__subject' ).value,
				body: document.getElementById( editorId ).value
			} ).then( function ( response ) {
				result.textContent = response.data && response.data.message ? response.data.message : data.i18n.saveFailed;
				result.classList.add( response.success ? 'is-ok' : 'is-error' );
			} ).catch( function () {
				result.textContent = data.i18n.saveFailed;
				result.classList.add( 'is-error' );
			} ).finally( function () {
				button.disabled = false;
			} );
		} );
	} );

	/*
	 * -------------------------------------------------------------------
	 * Scorecard autosave
	 * -------------------------------------------------------------------
	 */

	var scorecard = document.querySelector( '.kdna-grants-scorecard' );

	if ( scorecard && ! scorecard.classList.contains( 'is-locked' ) ) {
		var applicationId = scorecard.getAttribute( 'data-application' );
		var savedLabel = scorecard.querySelector( '.kdna-grants-scorecard__saved' );
		var totalLabel = scorecard.querySelector( '[data-total]' );
		var notes = scorecard.querySelector( '.kdna-grants-scorecard__notes' );
		var shortlist = scorecard.querySelector( '.kdna-grants-scorecard__shortlist' );
		var pending = 0;
		var notesTimer = null;
		var savedTimer = null;

		var save = function ( field, value, key ) {
			pending++;
			savedLabel.textContent = data.i18n.saving;
			savedLabel.className = 'kdna-grants-scorecard__saved';
			clearTimeout( savedTimer );

			return post( {
				action: 'kdna_grants_save_score',
				nonce: data.scoreNonce,
				application_id: applicationId,
				field: field,
				key: key || '',
				value: value
			} ).then( function ( response ) {
				pending--;
				if ( ! response.success ) {
					throw new Error( response.data && response.data.message ? response.data.message : data.i18n.saveFailed );
				}
				totalLabel.textContent = response.data.total;
				if ( ! pending ) {
					savedLabel.textContent = data.i18n.saved;
					savedLabel.classList.add( 'is-ok' );
					savedTimer = setTimeout( function () {
						savedLabel.textContent = '';
					}, 2500 );
				}
				return response.data;
			} ).catch( function ( error ) {
				pending = Math.max( 0, pending - 1 );
				savedLabel.textContent = error && error.message ? error.message : data.i18n.saveFailed;
				savedLabel.classList.add( 'is-error' );
			} );
		};

		scorecard.querySelectorAll( '.kdna-grants-criterion' ).forEach( function ( group ) {
			var key = group.getAttribute( 'data-key' );

			group.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.kdna-grants-score' );
				if ( ! button ) {
					return;
				}

				// Clicking the chosen score again clears it.
				var value = button.classList.contains( 'is-selected' ) ? 0 : parseInt( button.getAttribute( 'data-score' ), 10 );

				group.querySelectorAll( '.kdna-grants-score' ).forEach( function ( other ) {
					var selected = other === button && value > 0;
					other.classList.toggle( 'is-selected', selected );
					other.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
				} );

				save( 'score', value, key );
			} );
		} );

		notes.addEventListener( 'input', function () {
			clearTimeout( notesTimer );
			savedLabel.textContent = '';
			notesTimer = setTimeout( function () {
				save( 'notes', notes.value );
			}, 800 );
		} );

		notes.addEventListener( 'blur', function () {
			if ( notesTimer ) {
				clearTimeout( notesTimer );
				notesTimer = null;
				save( 'notes', notes.value );
			}
		} );

		shortlist.addEventListener( 'change', function () {
			save( 'shortlisted', shortlist.checked ? 1 : 0 );
		} );

		// Don't lose notes typed in the last moment before leaving the page.
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( notesTimer || pending ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	}

	/*
	 * -------------------------------------------------------------------
	 * Choose winner confirmation box
	 * -------------------------------------------------------------------
	 */

	var dialog = document.getElementById( 'kdna-grants-confirm-winner' );

	if ( dialog ) {
		var dialogForm = dialog.querySelector( 'form' );
		var reasonBox = dialog.querySelector( '.kdna-grants-dialog__reason' );
		var reason = dialog.querySelector( 'textarea[name="reason"]' );

		document.querySelectorAll( '.kdna-grants-choose' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var isTop = '1' === button.getAttribute( 'data-top' );

				dialogForm.elements.application_id.value = button.getAttribute( 'data-application' );
				dialog.querySelector( '[data-fill="business"]' ).textContent = button.getAttribute( 'data-business' );
				dialog.querySelector( '[data-fill="email"]' ).textContent = button.getAttribute( 'data-email' );
				reasonBox.hidden = isTop;
				reason.required = ! isTop;
				reason.value = '';

				dialog.showModal();
			} );
		} );

		dialog.querySelector( '[data-close]' ).addEventListener( 'click', function () {
			dialog.close();
		} );

		dialogForm.addEventListener( 'submit', function ( event ) {
			if ( reason.required && '' === reason.value.trim() ) {
				event.preventDefault();
				reason.setCustomValidity( data.i18n.reasonNeeded );
				reason.reportValidity();
				return;
			}
			dialogForm.querySelector( '[type="submit"]' ).disabled = true;
		} );

		reason.addEventListener( 'input', function () {
			reason.setCustomValidity( '' );
		} );
	}
	/*
	 * -------------------------------------------------------------------
	 * Repeating rows (email templates in Settings)
	 * -------------------------------------------------------------------
	 */

	function renumberRepeat( container ) {
		var name = container.getAttribute( 'data-name' );

		container.querySelectorAll( ':scope > .kdna-grants-repeat__row' ).forEach( function ( row, index ) {
			row.querySelectorAll( '[data-field]' ).forEach( function ( field ) {
				field.name = name + '[' + index + '][' + field.getAttribute( 'data-field' ) + ']';
			} );
		} );
	}

	document.querySelectorAll( '.kdna-grants-repeat' ).forEach( function ( container ) {
		container.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.kdna-grants-repeat__remove' ) ) {
				event.target.closest( '.kdna-grants-repeat__row' ).remove();
				renumberRepeat( container );
			}
		} );
	} );

	document.querySelectorAll( '.kdna-grants-repeat__add' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var container = document.getElementById( button.getAttribute( 'data-target' ) );
			var template = container.querySelector( '.kdna-grants-repeat__template' );
			var row = template.content.firstElementChild.cloneNode( true );

			container.insertBefore( row, template );
			renumberRepeat( container );
			row.querySelector( 'input' ).focus();
		} );
	} );

	/*
	 * -------------------------------------------------------------------
	 * Close buttons on every KDNA Grants dialog
	 * -------------------------------------------------------------------
	 */

	document.querySelectorAll( '.kdna-grants-dialog' ).forEach( function ( box ) {
		box.querySelectorAll( '[data-close]' ).forEach( function ( button ) {
			if ( box.id === 'kdna-grants-confirm-winner' ) {
				return; // Has its own handler above.
			}
			button.addEventListener( 'click', function () {
				box.dispatchEvent( new Event( 'kdna-close-request' ) );
			} );
		} );

		box.addEventListener( 'kdna-close-request', function () {
			box.close();
		} );
	} );

	/*
	 * -------------------------------------------------------------------
	 * Email window
	 * -------------------------------------------------------------------
	 */

	var compose = document.getElementById( 'kdna-grants-compose' );

	if ( compose ) {
		var composeForm = compose.querySelector( 'form' );
		var subject = compose.querySelector( '#kdna-grants-compose-subject' );
		var body = compose.querySelector( '#kdna-grants-compose-body' );
		var status = compose.querySelector( '.kdna-grants-compose__status' );
		var templatePicker = compose.querySelector( '#kdna-grants-compose-template' );
		var draftBox = compose.querySelector( '.kdna-grants-compose__draft' );
		var sendButton = compose.querySelector( '.kdna-grants-compose__send' );
		var currentButton = null;

		var setStatus = function ( text, type ) {
			status.textContent = text || '';
			status.className = 'kdna-grants-compose__status' + ( type ? ' is-' + type : '' );
		};

		var busy = function ( on ) {
			compose.querySelectorAll( 'button, input, textarea, select' ).forEach( function ( el ) {
				if ( ! el.hasAttribute( 'data-close' ) ) {
					el.disabled = on;
				}
			} );
		};

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.kdna-grants-contact' );
			if ( ! button ) {
				return;
			}

			currentButton = button;
			composeForm.elements.application_id.value = button.getAttribute( 'data-application' );
			compose.querySelector( '[data-fill="to"]' ).textContent = ( button.getAttribute( 'data-name' ) ? button.getAttribute( 'data-name' ) + ' ' : '' ) + '<' + button.getAttribute( 'data-email' ) + '>';
			compose.querySelector( '[data-fill="business"]' ).textContent = button.getAttribute( 'data-business' );
			subject.value = '';
			body.value = '';
			if ( templatePicker ) {
				templatePicker.value = '';
			}
			if ( draftBox ) {
				draftBox.hidden = true;
				draftBox.querySelector( 'textarea' ).value = '';
			}
			setStatus( '' );
			busy( false );
			compose.showModal();
			subject.focus();
		} );

		// Ask before throwing away a written message.
		compose.addEventListener( 'kdna-close-request', function ( event ) {
			event.stopImmediatePropagation();
			// eslint-disable-next-line no-alert
			if ( '' === body.value.trim() || window.confirm( data.i18n.confirmCloseDraft ) ) {
				compose.close();
			}
		}, true );

		compose.addEventListener( 'cancel', function ( event ) {
			// eslint-disable-next-line no-alert
			if ( '' !== body.value.trim() && ! window.confirm( data.i18n.confirmCloseDraft ) ) {
				event.preventDefault();
			}
		} );

		if ( templatePicker ) {
			templatePicker.addEventListener( 'change', function () {
				if ( '' === templatePicker.value ) {
					return;
				}
				setStatus( data.i18n.loading );
				post( {
					action: 'kdna_grants_contact_template',
					nonce: data.contactNonce,
					application_id: composeForm.elements.application_id.value,
					template: templatePicker.value
				} ).then( function ( response ) {
					if ( response.success ) {
						subject.value = response.data.subject;
						body.value = response.data.body;
						setStatus( '' );
					} else {
						setStatus( response.data.message, 'error' );
					}
				} ).catch( function () {
					setStatus( data.i18n.saveFailed, 'error' );
				} );
			} );
		}

		if ( draftBox ) {
			compose.querySelector( '.kdna-grants-compose__draft-toggle' ).addEventListener( 'click', function () {
				draftBox.hidden = ! draftBox.hidden;
				if ( ! draftBox.hidden ) {
					draftBox.querySelector( 'textarea' ).focus();
				}
			} );

			compose.querySelector( '.kdna-grants-compose__draft-go' ).addEventListener( 'click', function () {
				setStatus( data.i18n.drafting );
				busy( true );
				post( {
					action: 'kdna_grants_contact_draft',
					nonce: data.contactNonce,
					application_id: composeForm.elements.application_id.value,
					purpose: draftBox.querySelector( 'textarea' ).value
				} ).then( function ( response ) {
					busy( false );
					if ( response.success ) {
						subject.value = response.data.subject;
						body.value = response.data.body;
						draftBox.hidden = true;
						setStatus( '' );
						body.focus();
					} else {
						setStatus( response.data.message, 'error' );
					}
				} ).catch( function () {
					busy( false );
					setStatus( data.i18n.saveFailed, 'error' );
				} );
			} );
		}

		sendButton.addEventListener( 'click', function () {
			setStatus( data.i18n.sending );
			busy( true );
			post( {
				action: 'kdna_grants_contact_send',
				nonce: data.contactNonce,
				application_id: composeForm.elements.application_id.value,
				subject: subject.value,
				body: body.value
			} ).then( function ( response ) {
				busy( false );
				if ( ! response.success ) {
					setStatus( response.data.message, 'error' );
					return;
				}
				setStatus( response.data.message, 'ok' );

				// Show "Contacted" next to the button straight away.
				if ( currentButton ) {
					var marker = currentButton.parentNode.querySelector( '.kdna-grants-contacted' );
					if ( ! marker ) {
						marker = document.createElement( 'span' );
						marker.className = 'kdna-grants-contacted';
						currentButton.insertAdjacentText( 'afterend', ' ' );
						currentButton.parentNode.insertBefore( marker, currentButton.nextSibling.nextSibling );
					}
					marker.textContent = response.data.label;
				}

				body.value = '';
				setTimeout( function () {
					compose.close();
				}, 1200 );
			} ).catch( function () {
				busy( false );
				setStatus( data.i18n.saveFailed, 'error' );
			} );
		} );
	}

	/*
	 * -------------------------------------------------------------------
	 * Accept offer box
	 * -------------------------------------------------------------------
	 */

	var acceptBox = document.getElementById( 'kdna-grants-accept-offer' );

	if ( acceptBox ) {
		document.querySelectorAll( '.kdna-grants-offer-accept' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				acceptBox.querySelector( 'input[name="offer_id"]' ).value = button.getAttribute( 'data-offer' );
				acceptBox.querySelector( '[data-fill="business"]' ).textContent = button.getAttribute( 'data-business' );
				acceptBox.querySelector( '[data-fill="code"]' ).textContent = button.getAttribute( 'data-code' );
				acceptBox.querySelector( 'textarea' ).value = '';
				acceptBox.showModal();
				acceptBox.querySelector( 'textarea' ).focus();
			} );
		} );
	}

	/*
	 * -------------------------------------------------------------------
	 * "Ask Claude now" on the judging screen
	 * -------------------------------------------------------------------
	 */

	document.querySelectorAll( '.kdna-grants-assess' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var label = button.parentNode.querySelector( '.kdna-grants-assess__status' );

			button.disabled = true;
			label.textContent = data.i18n.assessing;
			label.className = 'kdna-grants-assess__status';

			post( {
				action: 'kdna_grants_claude_assess',
				nonce: data.assessNonce,
				application_id: button.getAttribute( 'data-application' )
			} ).then( function ( response ) {
				if ( response.success ) {
					window.location.reload();
					return;
				}
				button.disabled = false;
				label.textContent = response.data.message;
				label.classList.add( 'is-error' );
			} ).catch( function () {
				button.disabled = false;
				label.textContent = data.i18n.saveFailed;
				label.classList.add( 'is-error' );
			} );
		} );
	} );
} )();
