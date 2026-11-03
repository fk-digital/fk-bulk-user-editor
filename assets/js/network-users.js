/**
 * FK Bulk User Editor - Network Admin › Users.
 *
 * Core omits the bulk-action checkbox for super admins. This adds it back
 * (same `allusers[]` name/value core uses) so super admins can be selected
 * for "Add to Sites…". Core's own destructive bulk actions independently
 * refuse to touch super admins, so this is safe.
 */
( function () {
	'use strict';

	document.querySelectorAll( '#the-list > tr' ).forEach( function ( row ) {
		var cell = row.querySelector( 'th.check-column, td.check-column' );
		if ( ! cell || cell.querySelector( 'input[type="checkbox"]' ) ) {
			return; // Not a super admin row - it already has one.
		}

		var link = row.querySelector( 'a[href*="user-edit.php"], a[href*="profile.php"]' );
		var match = link ? link.href.match( /[?&]user_id=(\d+)/ ) : null;
		var userId = match
			? match[ 1 ]
			: link && /profile\.php/.test( link.href ) && window.userSettings
				? window.userSettings.uid
				: null;

		if ( ! userId ) {
			return;
		}

		var input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.name = 'allusers[]';
		input.value = userId;
		input.id = 'fk-bulk-user-editor-user-' + userId;

		var label = document.createElement( 'label' );
		label.className = 'screen-reader-text';
		label.htmlFor = input.id;
		label.textContent = row.querySelector( '.username strong, .column-username strong' )
			? row.querySelector( '.username strong, .column-username strong' ).textContent.trim()
			: userId;

		cell.appendChild( label );
		cell.appendChild( input );
	} );
} )();
