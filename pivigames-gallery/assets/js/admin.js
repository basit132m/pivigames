/**
 * PiviGames Gallery — admin image picker (WP media) + drag reorder.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $list = $( '#pivigames-gallery-list' );
		var $input = $( '#pivigames_gallery' );
		var $add = $( '#pivigames-gallery-add' );
		var frame;

		if ( ! $list.length || ! $input.length || ! $add.length ) {
			return;
		}

		var cfg = window.pivigamesGallery || {};
		var $status = $( '#pivigames-gallery-status' );
		var saveTimer;

		function doSave() {
			if ( ! cfg.ajaxUrl || ! cfg.nonce ) {
				return;
			}
			var postId = $list.data( 'post' );
			if ( ! postId ) {
				return;
			}
			$status.removeClass( 'is-error is-ok' ).text( cfg.saving || 'Saving…' );
			$.post( cfg.ajaxUrl, {
				action: 'pivigames_gallery_save',
				post_id: postId,
				ids: $input.val(),
				nonce: cfg.nonce
			} ).done( function ( r ) {
				if ( r && r.success ) {
					$status.addClass( 'is-ok' ).text( cfg.saved || 'Saved' );
				} else {
					$status.addClass( 'is-error' ).text( cfg.error || 'Error' );
				}
			} ).fail( function () {
				$status.addClass( 'is-error' ).text( cfg.error || 'Error' );
			} );
		}

		// Persist immediately (debounced) via AJAX so the gallery survives even
		// if the classic meta box form is not submitted on Update.
		function persist() {
			clearTimeout( saveTimer );
			saveTimer = setTimeout( doSave, 500 );
		}

		// Flush any pending save right away (e.g. when the user hits Update).
		function flush() {
			clearTimeout( saveTimer );
			doSave();
		}

		$( 'form#post' ).on( 'submit', flush );
		$( document ).on(
			'click',
			'#publish, #save-post, .editor-post-publish-button, .editor-post-save-draft',
			flush
		);

		function refresh() {
			var ids = [];
			$list.children( 'li' ).each( function () {
				ids.push( $( this ).data( 'id' ) );
			} );
			$input.val( ids.join( ',' ) );
			persist();
		}

		$add.on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: ( window.pivigamesGallery && pivigamesGallery.title ) || 'Select images',
				button: { text: ( window.pivigamesGallery && pivigamesGallery.button ) || 'Use images' },
				library: { type: 'image' },
				multiple: true
			} );

			frame.on( 'select', function () {
				var selection = frame.state().get( 'selection' );
				selection.each( function ( attachment ) {
					var a = attachment.toJSON();
					if ( $list.find( 'li[data-id="' + a.id + '"]' ).length ) {
						return;
					}
					var thumb = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
					var $li = $(
						'<li class="pivigames-gi" data-id="' + a.id + '">' +
							'<img src="' + thumb + '" alt="" />' +
							'<button type="button" class="pivigames-gi__remove" aria-label="Remove">&times;</button>' +
						'</li>'
					);
					$list.append( $li );
				} );
				refresh();
			} );

			frame.open();
		} );

		$list.on( 'click', '.pivigames-gi__remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( 'li' ).remove();
			refresh();
		} );

		if ( $.fn.sortable ) {
			$list.sortable( {
				items: '> li',
				cursor: 'move',
				update: refresh
			} );
		}
	} );
} )( jQuery );
