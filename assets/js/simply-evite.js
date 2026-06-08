( function () {
	'use strict';

	function initEvite( evite ) {

		var stage      = evite.querySelector( '.se-stage' );
		var back       = evite.querySelector( '.se-env-back' );
		var flap       = evite.querySelector( '.se-env-flap' );
		var front      = evite.querySelector( '.se-env-front' );
		var card       = evite.querySelector( '.se-card' );
		var cardWrap   = evite.querySelector( '.se-card-wrap' );
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

			var targetCardW   = Math.round( innerH * 0.675 );           // cardW * 4/3 = 90vh
			var maxCardWfromW = Math.round( ( stageW - pad * 2 ) * 0.75 ); // envW fits horizontally
			var cardW         = Math.min( targetCardW, maxCardWfromW );
			cardW             = Math.max( cardW, 120 );
			card.style.width  = cardW + 'px';
			cardWStore        = cardW;

			var cardH = cardW * ( 4 / 3 );
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
			var scaleByH  = openCardH / cardH;
			var scaleByW  = ( stageW * 0.97 ) / cardW;  // prevent horizontal clip
			cardScale     = Math.min( scaleByH, scaleByW );
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

		// ── Open — 3-phase animation ─────────────────────────────────────
		//
		// Envelope never moves. Card does all the work.
		//
		// Phase 1 (t=0):     Card slides UP — bottom edge 20px above envelope top.
		//                    Z-index raised once card clears the front rectangle.
		// Phase 2 (t=550ms): Card rotates 90° (landscape → portrait) + scales up.
		// Phase 3 (t=1450ms):Card slides back DOWN to viewport center.
		//
		// After animation (t≈2400ms): sidebar slides in.

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

			// ── Post-animation: reveal sidebar ───────────────────────────
			setTimeout( function () {
				if ( toggleBtn ) toggleBtn.classList.add( 'is-visible' );
				if ( window.innerWidth > 768 ) openSidebar();
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

	// ── Countdown clocks ────────────────────────────────────────────────

	function initCountdown( el ) {
		var target  = new Date( el.dataset.target ).getTime();
		var daysEl  = el.querySelector( '.se-cd-days' );
		var hoursEl = el.querySelector( '.se-cd-hours' );
		var minsEl  = el.querySelector( '.se-cd-mins' );
		var secsEl  = el.querySelector( '.se-cd-secs' );

		function pad( n ) { return String( n ).padStart( 2, '0' ); }

		function tick() {
			var diff = target - Date.now();

			if ( diff <= 0 ) {
				daysEl.textContent  = '0';
				hoursEl.textContent = '0';
				minsEl.textContent  = '00';
				secsEl.textContent  = '00';
				return;
			}

			daysEl.textContent  = Math.floor( diff / 86400000 );
			hoursEl.textContent = Math.floor( ( diff % 86400000 ) / 3600000 );
			minsEl.textContent  = pad( Math.floor( ( diff % 3600000 ) / 60000 ) );
			secsEl.textContent  = pad( Math.floor( ( diff % 60000 )   / 1000  ) );

			setTimeout( tick, 1000 );
		}

		tick();
	}

	// ── iCal download ────────────────────────────────────────────────────

	function initIcsLinks() {
		document.querySelectorAll( '.se-cal-ics' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var t     = link.dataset.title       || '';
				var start = link.dataset.start       || '';
				var end   = link.dataset.end         || '';
				var loc   = link.dataset.location    || '';
				var desc  = link.dataset.description || '';

				var ics = [
					'BEGIN:VCALENDAR',
					'VERSION:2.0',
					'PRODID:-//Simply Design//Simply Evite//EN',
					'BEGIN:VEVENT',
					'DTSTART:'  + start,
					'DTEND:'    + end,
					'SUMMARY:'  + t.replace( /,/g, '\\,' ),
					'LOCATION:' + loc.replace( /,/g, '\\,' ),
					'DESCRIPTION:' + desc.replace( /,/g, '\\,' ),
					'END:VEVENT',
					'END:VCALENDAR'
				].join( '\r\n' );

				var blob = new Blob( [ ics ], { type: 'text/calendar' } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );
				a.href     = url;
				a.download = 'event.ics';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.se-evite' ).forEach( initEvite );
		document.querySelectorAll( '.se-countdown' ).forEach( initCountdown );
		initIcsLinks();
	} );

} )();
