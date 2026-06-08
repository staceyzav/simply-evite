( function () {
	'use strict';

	function initEvite( evite ) {

		var stage      = evite.querySelector( '.se-stage' );
		var back       = evite.querySelector( '.se-env-back' );
		var flap       = evite.querySelector( '.se-env-flap' );
		var front      = evite.querySelector( '.se-env-front' );
		var card       = evite.querySelector( '.se-card' );
		var cardWrap   = evite.querySelector( '.se-card-wrap' );
		var scrollHint = stage.querySelector( '.se-scroll-hint' );
		var toggleBtn  = evite.querySelector( '.se-sidebar-toggle' );
		var sidePanel  = evite.querySelector( '.se-sidebar-panel' );
		var trigger    = evite.dataset.trigger || 'auto';
		var delay      = parseInt( evite.dataset.delay, 10 ) || 1000;
		var opened     = false;

		// Stored by layout() for use in open()
		var backTopStore = 0;
		var cyStore      = 0;
		var cardWStore   = 260;
		var cardScale    = 1;

		// ── Geometry ────────────────────────────────────────────────────

		function layout() {
			var innerH = window.innerHeight;
			var stageW = stage.offsetWidth;
			var pad    = 18;

			var targetCardW   = Math.round( innerH * 0.6 );
			var maxCardWfromW = Math.round( ( stageW - pad * 2 ) / 1.5 );
			var cardW         = Math.min( targetCardW, maxCardWfromW );
			cardW             = Math.max( cardW, 120 );
			card.style.width  = cardW + 'px';
			cardWStore        = cardW;

			var cardH = cardW * 1.5;
			var flapH = Math.round( cardW * 0.32 );
			var envW  = cardH + pad * 2;
			var envH  = cardW + pad * 2;

			stage.style.height = innerH + 'px';

			var cx       = Math.round( stageW / 2 );
			var cy       = Math.round( innerH  / 2 ) + 50;  // shift envelope 50px below center
			cyStore      = cy;
			cardWrap.style.top = cy + 'px'; // keep card aligned with envelope center

			var backLeft = cx - Math.round( envW / 2 );
			var backTop  = cy - Math.round( envH / 2 );
			backTopStore = backTop;

			place( back,  backLeft, backTop,         envW, envH  );
			place( flap,  backLeft, backTop - flapH, envW, flapH );
			place( front, backLeft, backTop,          envW, envH  );

			var openCardH = innerH * 0.9;
			cardScale     = openCardH / cardH;
			if ( cardScale < 1 ) cardScale = 1;
			evite.style.setProperty( '--se-card-scale', cardScale );
		}

		function place( el, left, top, w, h ) {
			el.style.left   = left + 'px';
			el.style.top    = top  + 'px';
			el.style.width  = w    + 'px';
			el.style.height = h    + 'px';
		}

		// ── Sidebar ──────────────────────────────────────────────────────

		function openSidebar() {
			if ( ! sidePanel ) return;
			sidePanel.classList.add( 'is-open' );
			if ( toggleBtn ) {
				toggleBtn.classList.add( 'is-open' );
				toggleBtn.setAttribute( 'aria-expanded', 'true' );
			}
		}

		function closeSidebar() {
			if ( ! sidePanel ) return;
			sidePanel.classList.remove( 'is-open' );
			if ( toggleBtn ) {
				toggleBtn.classList.remove( 'is-open' );
				toggleBtn.setAttribute( 'aria-expanded', 'false' );
			}
		}

		if ( toggleBtn ) {
			toggleBtn.addEventListener( 'click', function () {
				if ( sidePanel.classList.contains( 'is-open' ) ) {
					closeSidebar();
				} else {
					openSidebar();
				}
			} );
		}

		// ── Scroll hint ──────────────────────────────────────────────────

		function initScrollHint() {
			if ( ! scrollHint ) return;
			window.addEventListener( 'scroll', function () {
				if ( window.scrollY > 50 ) {
					scrollHint.classList.remove( 'is-visible' );
					scrollHint.classList.add( 'is-hidden' );
				} else {
					scrollHint.classList.remove( 'is-hidden' );
					scrollHint.classList.add( 'is-visible' );
				}
			}, { passive: true } );
		}

		// ── Open — 3-phase animation ─────────────────────────────────────
		//
		// Envelope never moves. Card does all the work.
		//
		// Phase 1 (t=0):     Card slides UP — bottom edge 20px above envelope top.
		//                    Z-index raised once card clears the front rectangle.
		// Phase 2 (t=550ms): Card rotates 90° (landscape → portrait) + scales up.
		// Phase 3 (t=1450ms):Card slides back DOWN to viewport center.
		//
		// After animation (t≈2400ms): sidebar slides in, scroll hint appears.

		function open() {
			if ( opened ) return;
			opened = true;

			// upPx: how far the card must travel upward so its bottom edge
			// sits 20px above the envelope front's top edge (backTopStore).
			var upPx = ( cyStore + cardWStore / 2 ) - ( backTopStore - 20 );
			upPx = Math.max( upPx, 10 );

			// ── Phase 1: slide card up (z-index unchanged) ───────────────
			card.style.transition = 'transform 0.5s cubic-bezier( 0.4, 0, 0.2, 1 )';
			card.style.transform  = 'translateY(-' + upPx + 'px) rotate(90deg)';

			// Raise z-index the moment the card's bottom clears the
			// envelope front's top edge — proportional point in the 500ms slide.
			var clearPx     = cyStore + cardWStore / 2 - backTopStore;
			var zIndexDelay = Math.round( 500 * clearPx / upPx );
			setTimeout( function () {
				cardWrap.style.zIndex = '20';
			}, zIndexDelay );

			// ── Phase 2: rotate card (landscape → portrait) ──────────────
			setTimeout( function () {
				card.style.transition = 'transform 0.85s cubic-bezier( 0.22, 1, 0.36, 1 )';
				card.style.transform  = 'translateY(-' + upPx + 'px) rotate(0deg) scale(' + cardScale + ')';
			}, 550 );

			// ── Phase 3: slide card back down to viewport center ─────────
			setTimeout( function () {
				evite.classList.add( 'is-open' );

				card.style.transition = 'transform 0.65s cubic-bezier( 0.22, 1, 0.36, 1 )';
				card.style.transform  = 'rotate(0deg) scale(' + cardScale + ')';
			}, 1450 );

			// ── Post-animation: reveal sidebar + scroll hint ─────────────
			setTimeout( function () {
				if ( toggleBtn ) toggleBtn.classList.add( 'is-visible' );
				openSidebar();
				if ( scrollHint ) {
					scrollHint.classList.add( 'is-visible' );
					initScrollHint();
				}
			}, 2400 );
		}

		// ── Init ────────────────────────────────────────────────────────

		layout();
		window.addEventListener( 'resize', layout );

		if ( trigger === 'auto' ) {
			setTimeout( open, delay );
		} else {
			stage.addEventListener( 'click', open );
			stage.setAttribute( 'tabindex', '0' );
			stage.setAttribute( 'role', 'button' );
			stage.setAttribute( 'aria-label', 'Open invitation' );
			stage.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); open(); }
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.se-evite' ).forEach( initEvite );
	} );

} )();
