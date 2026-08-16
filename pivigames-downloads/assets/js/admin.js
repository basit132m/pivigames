/**
 * PiviGames Downloads — repeatable rows in the post editor.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'pivigames-downloads-rows' );
		var addBtn = document.getElementById( 'pivigames-add-download' );
		var tpl = document.getElementById( 'pivigames-download-template' );

		if ( ! wrap || ! addBtn || ! tpl ) {
			return;
		}

		// Next index continues after any rows already rendered by PHP.
		var index = wrap.querySelectorAll( '.pivigames-download-row' ).length;

		// Add a new row.
		addBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var html = tpl.innerHTML.replace( /__index__/g, index );
			var temp = document.createElement( 'div' );
			temp.innerHTML = html.trim();
			var row = temp.querySelector( '.pivigames-download-row' );
			if ( row ) {
				wrap.appendChild( row );
				index++;
			}
		} );

		// Remove a row (event delegation).
		wrap.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.pivigames-remove-download' );
			if ( ! btn ) {
				return;
			}
			e.preventDefault();
			var rows = wrap.querySelectorAll( '.pivigames-download-row' );
			var row = btn.closest( '.pivigames-download-row' );
			if ( ! row ) {
				return;
			}
			// Keep at least one row present for usability; clear it instead of deleting.
			if ( rows.length <= 1 ) {
				row.querySelectorAll( 'input' ).forEach( function ( input ) {
					input.value = '';
				} );
			} else {
				row.parentNode.removeChild( row );
			}
		} );
	} );
} )();
