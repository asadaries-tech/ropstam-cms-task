/**
 * <bundle-builder>: "Build your 3-pack" section behaviour.
 *
 * Vanilla JS with no dependencies. As a custom element, it sets itself up when
 * the section renders and again when the theme editor reloads the section
 * (connectedCallback / disconnectedCallback), with no shopify:section:load wiring.
 *
 * - Prices are integer cents read from data attributes rendered by Liquid.
 * - One delegated `change` and one `submit` listener per section.
 * - DOM writes are limited to text that actually changed and a few attributes.
 * - Clicking, tapping or pressing Space on the already-selected item deselects
 *   it; native radios can't be unchecked, so this is added on top.
 * - All 3 items are added in ONE /cart/add.js request. Every line carries the
 *   same `_bundle_id` property (hidden from customers by the leading underscore)
 *   plus a visible `Bundle` property.
 */
( () => {
	'use strict';

	if ( customElements.get( 'bundle-builder' ) ) {
		return;
	}

	const REQUEST_TIMEOUT_MS = 15000;

	// Shopify money format placeholders: [decimals, thousands separator, decimal separator].
	const MONEY_FORMATS = {
		amount: [ 2, ',', '.' ],
		amount_no_decimals: [ 0, ',', '.' ],
		amount_with_comma_separator: [ 2, '.', ',' ],
		amount_no_decimals_with_comma_separator: [ 0, '.', ',' ],
		amount_with_apostrophe_separator: [ 2, "'", '.' ],
		amount_no_decimals_with_space_separator: [ 0, ' ', '.' ],
		amount_with_space_separator: [ 2, ' ', ',' ],
		amount_with_period_and_space_separator: [ 2, ' ', '.' ],
	};

	/**
	 * Format cents with the shop's money format (matches Liquid's `money` filter).
	 *
	 * @param {number} cents  Amount in cents.
	 * @param {string} format Shop money format, e.g. "${{amount}}".
	 * @return {string} Formatted price.
	 */
	function formatMoney( cents, format ) {
		return ( format || '${{amount}}' ).replace( /\{\{\s*(\w+)\s*\}\}/, ( match, key ) => {
			const [ decimals, thousands, decimal ] = MONEY_FORMATS[ key ] || MONEY_FORMATS.amount;
			const [ whole, fraction ] = ( cents / 100 ).toFixed( decimals ).split( '.' );
			const grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, thousands );
			return fraction ? grouped + decimal + fraction : grouped;
		} );
	}

	/**
	 * Unique id that links the bundle's line items together.
	 *
	 * @return {string} Bundle id.
	 */
	function createBundleId() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return window.crypto.randomUUID();
		}
		return `${ Date.now().toString( 36 ) }-${ Math.random().toString( 36 ).slice( 2, 10 ) }`;
	}

	/**
	 * Set text only when it differs, so live regions aren't re-announced and the DOM isn't touched needlessly.
	 *
	 * @param {Element|null} el   Target element.
	 * @param {string}       text New text.
	 */
	function setText( el, text ) {
		if ( el && el.textContent !== text ) {
			el.textContent = text;
		}
	}

	class CartRequestError extends Error {}

	class BundleBuilder extends HTMLElement {
		connectedCallback() {
			this.form = this.querySelector( '[data-bundle-form]' );
			if ( ! this.form ) {
				return; // Section has no usable groups.
			}

			this.groups = Array.from( this.querySelectorAll( '[data-bundle-group]' ) );
			this.picks = Array.from( this.querySelectorAll( '[data-bundle-pick]' ) );
			this.totalEl = this.querySelector( '[data-bundle-total]' );
			this.statusEl = this.querySelector( '[data-bundle-status]' );
			this.errorEl = this.querySelector( '[data-bundle-error]' );
			this.successEl = this.querySelector( '[data-bundle-success-text]' );
			this.cartLink = this.querySelector( '[data-bundle-cart-link]' );
			this.button = this.querySelector( '[data-bundle-submit]' );
			this.buttonLabel = this.querySelector( '[data-bundle-button-label]' );
			this.defaultLabel = this.buttonLabel ? this.buttonLabel.textContent : '';
			this.busy = false;
			// Group fieldset -> radio that was checked before the current click.
			this.lastChecked = new WeakMap();

			this.onClick = this.onClick.bind( this );
			this.onKeydown = this.onKeydown.bind( this );
			this.onChange = this.onChange.bind( this );
			this.onSubmit = this.onSubmit.bind( this );
			this.form.addEventListener( 'click', this.onClick );
			this.form.addEventListener( 'keydown', this.onKeydown );
			this.form.addEventListener( 'change', this.onChange );
			this.form.addEventListener( 'submit', this.onSubmit );

			this.update();
		}

		disconnectedCallback() {
			if ( this.form ) {
				this.form.removeEventListener( 'click', this.onClick );
				this.form.removeEventListener( 'keydown', this.onKeydown );
				this.form.removeEventListener( 'change', this.onChange );
				this.form.removeEventListener( 'submit', this.onSubmit );
			}
		}

		/**
		 * Read the customer's choice for one group.
		 *
		 * @param {HTMLFieldSetElement} group Group fieldset.
		 * @return {{variantId: number, price: number, title: string, available: boolean}|null} Selection.
		 */
		getSelection( group ) {
			const radio = group.querySelector( '[data-bundle-option]:checked' );
			if ( ! radio ) {
				return null;
			}

			const select = radio.closest( '[data-bundle-card]' ).querySelector( '[data-bundle-variant]' );
			if ( select ) {
				const option = select.options[ select.selectedIndex ];
				return {
					variantId: Number( option.value ),
					price: Number( option.dataset.price ) || 0,
					title: `${ radio.dataset.title } \u2013 ${ option.dataset.variantTitle }`,
					available: ! option.disabled && ! radio.disabled,
				};
			}

			return {
				variantId: Number( radio.dataset.variantId ),
				price: Number( radio.dataset.price ) || 0,
				title: radio.dataset.title,
				available: ! radio.disabled,
			};
		}

		/**
		 * Deselect on second click. A radio's `click` fires after it becomes checked
		 * but before `change`, so lastChecked still holds the previous choice here.
		 * Label clicks and arrow keys dispatch this click; arrow keys always move to
		 * a different radio, so they never deselect. Space is handled in onKeydown().
		 *
		 * @param {MouseEvent} event Click event.
		 */
		onClick( event ) {
			const radio = event.target.closest( '[data-bundle-option]' );
			if ( ! radio || this.busy ) {
				return;
			}

			const group = radio.closest( '[data-bundle-group]' );
			if ( this.lastChecked.get( group ) === radio ) {
				radio.checked = false;
				this.lastChecked.delete( group );
				this.clearMessages();
				this.update();
			}
		}

		/**
		 * Keyboard deselect: Space on the already-selected radio clears it.
		 * Browsers don't fire `click` for Space on a checked radio, so handle it here.
		 *
		 * @param {KeyboardEvent} event Keydown event.
		 */
		onKeydown( event ) {
			const radio = event.target;
			if ( event.key !== ' ' || this.busy || ! radio.matches( '[data-bundle-option]:checked' ) ) {
				return;
			}
			event.preventDefault();
			radio.checked = false;
			this.lastChecked.delete( radio.closest( '[data-bundle-group]' ) );
			this.clearMessages();
			this.update();
		}

		/**
		 * Variant change: update that card's price and select its product.
		 * Any change: recalculate the total and status.
		 *
		 * @param {Event} event Change event.
		 */
		onChange( event ) {
			const select = event.target.closest( '[data-bundle-variant]' );

			if ( select ) {
				const card = select.closest( '[data-bundle-card]' );
				const option = select.options[ select.selectedIndex ];
				const radio = card.querySelector( '[data-bundle-option]' );

				setText( card.querySelector( '[data-card-price]' ), formatMoney( Number( option.dataset.price ) || 0, this.dataset.moneyFormat ) );
				if ( radio && ! radio.disabled && ! radio.checked ) {
					radio.checked = true;
				}
			}

			// Remember each group's current choice for the deselect-on-second-click check.
			const group = event.target.closest( '[data-bundle-group]' );
			if ( group ) {
				const checked = group.querySelector( '[data-bundle-option]:checked' );
				if ( checked ) {
					this.lastChecked.set( group, checked );
				} else {
					this.lastChecked.delete( group );
				}
			}

			this.clearMessages();
			this.update();
		}

		/**
		 * Recalculate total, summary and button state from the current selections.
		 *
		 * @return {{selections: Array, missing: HTMLElement[], blocked: HTMLElement[]}} State.
		 */
		update() {
			const selections = this.groups.map( ( group ) => this.getSelection( group ) );
			const missing = this.groups.filter( ( group, i ) => ! selections[ i ] );
			const blocked = this.groups.filter( ( group ) => group.hasAttribute( 'data-bundle-unavailable' ) );
			const total = selections.reduce( ( sum, s ) => sum + ( s ? s.price : 0 ), 0 );
			const ready = missing.length === 0 && blocked.length === 0 && selections.every( ( s ) => s && s.available );

			selections.forEach( ( s, i ) => setText( this.picks[ i ], s ? s.title : 'Not selected' ) );
			setText( this.totalEl, formatMoney( total, this.dataset.moneyFormat ) );

			if ( ! this.busy ) {
				this.button.setAttribute( 'aria-disabled', String( ! ready ) );
			}

			setText( this.statusEl, this.getStatusText( selections, missing, blocked ) );

			return { selections, missing, blocked, ready };
		}

		/**
		 * Human-readable progress / validation message.
		 *
		 * @param {Array}         selections Current selections.
		 * @param {HTMLElement[]} missing    Groups without a selection.
		 * @param {HTMLElement[]} blocked    Groups that are entirely sold out.
		 * @return {string} Status text.
		 */
		getStatusText( selections, missing, blocked ) {
			if ( blocked.length ) {
				return `"${ blocked[ 0 ].dataset.groupTitle }" is sold out, so this bundle can't be completed right now.`;
			}
			if ( missing.length ) {
				const done = this.groups.length - missing.length;
				return `Choose 1 item from each group (${ done } of ${ this.groups.length } selected).`;
			}
			if ( selections.some( ( s ) => ! s.available ) ) {
				return 'One of your choices is sold out. Please pick another option.';
			}
			return 'Your bundle is complete.';
		}

		/**
		 * Validate, then add all items in a single /cart/add.js request.
		 *
		 * @param {SubmitEvent} event Submit event.
		 */
		async onSubmit( event ) {
			event.preventDefault();
			if ( this.busy ) {
				return; // Prevent double submits.
			}

			this.clearMessages();
			const { selections, missing, blocked, ready } = this.update();

			if ( ! ready ) {
				this.showError( this.getStatusText( selections, missing, blocked ) );
				const target = missing.length ? missing[ 0 ].querySelector( '[data-bundle-option]:not(:disabled)' ) : null;
				if ( target ) {
					target.focus();
				}
				return;
			}

			const bundleId = createBundleId();
			const items = selections.map( ( s ) => ( {
				id: s.variantId,
				quantity: 1,
				properties: {
					Bundle: this.dataset.bundleName,
					_bundle_id: bundleId,
				},
			} ) );

			this.setLoading( true );

			const controller = new AbortController();
			const timer = window.setTimeout( () => controller.abort(), REQUEST_TIMEOUT_MS );

			try {
				const response = await fetch( `${ this.dataset.cartAddUrl }.js`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					body: JSON.stringify( {
						items,
						// Section Rendering API: ask for the theme's cart icon so the count updates (Dawn-style themes).
						sections: 'cart-icon-bubble',
						sections_url: window.location.pathname,
					} ),
					signal: controller.signal,
				} );

				const data = await response.json().catch( () => null );

				if ( ! response.ok ) {
					// Shopify returns 422 with a readable `description`, e.g. not enough stock.
					throw new CartRequestError( ( data && ( data.description || data.message ) ) || this.dataset.labelGenericError );
				}

				this.onAdded( data, bundleId );
			} catch ( error ) {
				let message = this.dataset.labelNetworkError;
				if ( error.name === 'AbortError' ) {
					message = this.dataset.labelTimeout;
				} else if ( error instanceof CartRequestError ) {
					message = error.message;
				}
				this.showError( message );
			} finally {
				window.clearTimeout( timer );
				this.setLoading( false );
			}
		}

		/**
		 * Success: announce it, refresh the theme's cart UI and notify other scripts.
		 *
		 * @param {Object|null} data     /cart/add.js response.
		 * @param {string}      bundleId Bundle id.
		 */
		onAdded( data, bundleId ) {
			setText( this.successEl, 'Bundle added to your cart.' );
			if ( this.cartLink ) {
				this.cartLink.hidden = false;
			}

			const bubbleHtml = data && data.sections && data.sections[ 'cart-icon-bubble' ];
			const bubble = document.getElementById( 'cart-icon-bubble' );
			if ( bubbleHtml && bubble ) {
				const parsed = new DOMParser().parseFromString( bubbleHtml, 'text/html' );
				const inner = parsed.querySelector( '.shopify-section' );
				if ( inner ) {
					bubble.innerHTML = inner.innerHTML;
				}
			}

			// Dawn's pub/sub cart event, when present.
			if ( typeof window.publish === 'function' && window.PUB_SUB_EVENTS ) {
				window.publish( window.PUB_SUB_EVENTS.cartUpdate, { source: 'bundle-builder', cartData: data } );
			}

			this.dispatchEvent( new CustomEvent( 'bundle-builder:added', {
				bubbles: true,
				detail: { bundleId, items: data && data.items },
			} ) );
		}

		/**
		 * Toggle the loading state. The button keeps focus (aria-disabled, not disabled).
		 *
		 * @param {boolean} loading Whether a request is in flight.
		 */
		setLoading( loading ) {
			this.busy = loading;
			this.button.setAttribute( 'aria-busy', String( loading ) );
			this.button.classList.toggle( 'is-loading', loading );
			setText( this.buttonLabel, loading ? this.dataset.labelLoading : this.defaultLabel );
			if ( loading ) {
				this.button.setAttribute( 'aria-disabled', 'true' );
			} else {
				this.update();
			}
		}

		showError( message ) {
			setText( this.errorEl, message );
		}

		clearMessages() {
			setText( this.errorEl, '' );
			setText( this.successEl, '' );
			if ( this.cartLink ) {
				this.cartLink.hidden = true;
			}
		}
	}

	customElements.define( 'bundle-builder', BundleBuilder );
} )();
