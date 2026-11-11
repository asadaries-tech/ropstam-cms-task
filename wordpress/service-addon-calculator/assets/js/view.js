/**
 * Service add-on calculator (front end).
 *
 * Vanilla JS with no dependencies. Progressive enhancement: the server renders
 * the base price and hides the add-on toggles, and this script reveals them and
 * keeps the total up to date.
 *
 * - Prices travel as integer cents in data attributes, so there are no float errors.
 * - One delegated click listener per block, not one per button.
 * - Each click changes only the pressed button's aria-pressed and the <output> text.
 */
( function () {
	'use strict';

	/**
	 * Format integer cents with the separators PHP used, so JS and PHP output match.
	 *
	 * @param {number} cents   Amount in cents.
	 * @param {Object} format  { symbol, decimal, thousands }.
	 * @return {string} Formatted amount.
	 */
	function formatMoney( cents, format ) {
		var whole = Math.floor( cents / 100 ).toString();
		var fraction = ( '0' + ( cents % 100 ) ).slice( -2 );
		var grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, format.thousands );

		return format.symbol + grouped + format.decimal + fraction;
	}

	/**
	 * Parse a non-negative integer from a data attribute (falls back to 0).
	 *
	 * @param {string|undefined} value Raw attribute value.
	 * @return {number} Parsed integer.
	 */
	function toCents( value ) {
		var n = parseInt( value, 10 );
		return isNaN( n ) || n < 0 ? 0 : n;
	}

	/**
	 * Recalculate one service card's total from its pressed add-ons.
	 *
	 * @param {HTMLElement} card   The [data-sacalc-calculator] article.
	 * @param {Object}      format Money format.
	 */
	function updateTotal( card, format ) {
		var output = card.querySelector( '[data-sacalc-total]' );
		var pressed = card.querySelectorAll( '[data-sacalc-addon][aria-pressed="true"]' );
		var total = toCents( card.getAttribute( 'data-sacalc-base' ) );
		var i;
		var text;

		if ( ! output ) {
			return;
		}

		for ( i = 0; i < pressed.length; i++ ) {
			total += toCents( pressed[ i ].getAttribute( 'data-sacalc-price' ) );
		}

		text = formatMoney( total, format );

		// Write only on change, so the aria-live region isn't re-announced.
		if ( output.textContent !== text ) {
			output.textContent = text;
		}
	}

	/**
	 * Set up one block instance.
	 *
	 * @param {HTMLElement} root The .sacalc-services wrapper.
	 */
	function initBlock( root ) {
		var format = {
			symbol: root.getAttribute( 'data-sacalc-symbol' ) || '',
			decimal: root.getAttribute( 'data-sacalc-decimal' ) || '.',
			thousands: root.getAttribute( 'data-sacalc-thousands' ) || ',',
		};
		var fieldsets = root.querySelectorAll( '[data-sacalc-addons][hidden]' );
		var i;

		if ( root.hasAttribute( 'data-sacalc-ready' ) ) {
			return; // Guard against double initialisation.
		}
		root.setAttribute( 'data-sacalc-ready', '' );

		// Reveal the toggles now that they work.
		for ( i = 0; i < fieldsets.length; i++ ) {
			fieldsets[ i ].hidden = false;
		}

		// <button> already handles Enter/Space and focus natively; listening for click is enough.
		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-sacalc-addon]' );
			var card;

			if ( ! button || ! root.contains( button ) ) {
				return;
			}

			card = button.closest( '[data-sacalc-calculator]' );
			if ( ! card ) {
				return;
			}

			button.setAttribute(
				'aria-pressed',
				button.getAttribute( 'aria-pressed' ) === 'true' ? 'false' : 'true'
			);
			updateTotal( card, format );
		} );
	}

	function init() {
		var roots = document.querySelectorAll( '.sacalc-services' );
		var i;

		for ( i = 0; i < roots.length; i++ ) {
			initBlock( roots[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
