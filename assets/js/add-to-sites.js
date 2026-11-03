/**
 * FK Bulk User Editor - "Add Users to Sites" screen.
 *
 * Wires up the "Select all" checkbox for the site list.
 */
( function () {
	'use strict';

	var selectAll = document.getElementById( 'fk-bulk-user-editor-select-all' );
	if ( ! selectAll ) {
		return;
	}

	var sites = document.querySelectorAll( '.fk-bulk-user-editor-site' );

	selectAll.addEventListener( 'change', function () {
		sites.forEach( function ( checkbox ) {
			checkbox.checked = selectAll.checked;
		} );
	} );

	// Keep "Select all" in sync when individual sites are toggled.
	sites.forEach( function ( checkbox ) {
		checkbox.addEventListener( 'change', function () {
			var checked = document.querySelectorAll( '.fk-bulk-user-editor-site:checked' ).length;
			selectAll.checked = checked === sites.length;
			selectAll.indeterminate = checked > 0 && checked < sites.length;
		} );
	} );
} )();
