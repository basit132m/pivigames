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

		function refresh() {
			var ids = [];
			$list.children( 'li' ).each( function () {
				ids.push( $( this ).data( 'id' ) );
			} );
			$input.val( ids.join( ',' ) );
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
