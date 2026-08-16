/**
 * PiviGames Gallery — Nintendo-style lightbox with full-size zoom.
 *
 * - Click a grid image -> lightbox opens with that image framed on top and a
 *   thumbnail strip below.
 * - Click a thumbnail or use arrows/keyboard to switch images.
 * - Click the large image -> zoom to full (natural) size; click again to zoom out.
 */
( function () {
	'use strict';

	var L10n = window.pivigamesGalleryL10n || {
		close: 'Cerrar',
		prev: 'Anterior',
		next: 'Siguiente',
		zoom: 'Clic para ampliar'
	};

	var lb, stage, img, thumbs, current = [], index = 0, zoomed = false;

	function buildLightbox() {
		lb = document.createElement( 'div' );
		lb.className = 'pivigames-lb';
		lb.setAttribute( 'hidden', '' );
		lb.setAttribute( 'role', 'dialog' );
		lb.setAttribute( 'aria-modal', 'true' );

		lb.innerHTML =
			'<button type="button" class="pivigames-lb__close" aria-label="' + L10n.close + '">&times;</button>' +
			'<button type="button" class="pivigames-lb__nav pivigames-lb__prev" aria-label="' + L10n.prev + '">&#8249;</button>' +
			'<div class="pivigames-lb__stage"><img class="pivigames-lb__img" src="" alt="" title="' + L10n.zoom + '" /></div>' +
			'<button type="button" class="pivigames-lb__nav pivigames-lb__next" aria-label="' + L10n.next + '">&#8250;</button>' +
			'<div class="pivigames-lb__thumbs"></div>';

		document.body.appendChild( lb );

		stage = lb.querySelector( '.pivigames-lb__stage' );
		img = lb.querySelector( '.pivigames-lb__img' );
		thumbs = lb.querySelector( '.pivigames-lb__thumbs' );

		lb.querySelector( '.pivigames-lb__close' ).addEventListener( 'click', close );
		lb.querySelector( '.pivigames-lb__prev' ).addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			show( index - 1 );
		} );
		lb.querySelector( '.pivigames-lb__next' ).addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			show( index + 1 );
		} );

		// Click on the big image toggles full zoom.
		img.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			toggleZoom();
		} );

		// Click on the backdrop (not the image) closes.
		lb.addEventListener( 'click', function ( e ) {
			if ( e.target === lb || e.target === stage ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', onKey );
	}

	function renderThumbs() {
		thumbs.innerHTML = '';
		current.forEach( function ( item, i ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'pivigames-lb__thumb' + ( i === index ? ' is-active' : '' );
			var t = document.createElement( 'img' );
			t.src = item.thumb;
			t.alt = item.alt || '';
			t.loading = 'lazy';
			b.appendChild( t );
			b.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				show( i );
			} );
			thumbs.appendChild( b );
		} );
	}

	function updateActiveThumb() {
		var all = thumbs.querySelectorAll( '.pivigames-lb__thumb' );
		for ( var i = 0; i < all.length; i++ ) {
			all[ i ].classList.toggle( 'is-active', i === index );
		}
		if ( all[ index ] ) {
			all[ index ].scrollIntoView( { block: 'nearest', inline: 'center' } );
		}
	}

	function show( i ) {
		if ( ! current.length ) {
			return;
		}
		// Wrap around.
		index = ( i + current.length ) % current.length;
		if ( zoomed ) {
			setZoom( false );
		}
		img.src = current[ index ].full;
		img.alt = current[ index ].alt || '';
		updateActiveThumb();
	}

	function setZoom( on ) {
		zoomed = on;
		lb.classList.toggle( 'is-zoomed', on );
		if ( on ) {
			// Center the scroll on the image once it is at natural size.
			requestAnimationFrame( function () {
				stage.scrollLeft = ( stage.scrollWidth - stage.clientWidth ) / 2;
				stage.scrollTop = ( stage.scrollHeight - stage.clientHeight ) / 2;
			} );
		}
	}

	function toggleZoom() {
		setZoom( ! zoomed );
	}

	function open( items, start ) {
		if ( ! lb ) {
			buildLightbox();
		}
		current = items;
		index = start || 0;
		renderThumbs();
		lb.removeAttribute( 'hidden' );
		document.body.classList.add( 'pivigames-lb-open' );
		// Force reflow then fade in.
		void lb.offsetWidth;
		lb.classList.add( 'is-open' );
		show( index );
	}

	function close() {
		if ( ! lb ) {
			return;
		}
		setZoom( false );
		lb.classList.remove( 'is-open' );
		document.body.classList.remove( 'pivigames-lb-open' );
		window.setTimeout( function () {
			lb.setAttribute( 'hidden', '' );
			img.src = '';
		}, 200 );
	}

	function onKey( e ) {
		if ( ! lb || lb.hasAttribute( 'hidden' ) ) {
			return;
		}
		if ( e.key === 'Escape' ) {
			if ( zoomed ) {
				setZoom( false );
			} else {
				close();
			}
		} else if ( e.key === 'ArrowLeft' ) {
			show( index - 1 );
		} else if ( e.key === 'ArrowRight' ) {
			show( index + 1 );
		}
	}

	function collect( gallery ) {
		var links = gallery.querySelectorAll( '.pivigames-gallery__item' );
		var items = [];
		links.forEach( function ( a ) {
			var im = a.querySelector( 'img' );
			items.push( {
				full: a.getAttribute( 'data-full' ) || a.getAttribute( 'href' ),
				thumb: a.getAttribute( 'data-thumb' ) || ( im ? im.src : '' ),
				alt: im ? im.alt : ''
			} );
		} );
		return items;
	}

	function init() {
		var galleries = document.querySelectorAll( '[data-pivigames-gallery]' );
		galleries.forEach( function ( gallery ) {
			var items = collect( gallery );
			var links = gallery.querySelectorAll( '.pivigames-gallery__item' );
			links.forEach( function ( a, i ) {
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					open( items, i );
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
