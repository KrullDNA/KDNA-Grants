/**
 * KDNA Grants admin script.
 *
 * On the Settings screen:
 * - When a different form is picked, the field dropdowns update straight away
 *   with that form's fields, and any unmatched ones are guessed from their labels.
 * - Ticking "Delete all data when the plugin is deleted" asks for confirmation.
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
		abn: [ 'abn', 'australian business number' ],
		staff: [ 'staff', 'employees', 'team size', 'team' ],
		logo: [ 'logo' ]
	};

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
} )();
