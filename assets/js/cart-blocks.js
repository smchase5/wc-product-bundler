( function () {
	if (
		! window.wc ||
		! window.wc.blocksCheckout ||
		'function' !== typeof window.wc.blocksCheckout.registerCheckoutFilters
	) {
		return;
	}

	var namespace = 'wc-product-bundler';
	var summaryMarkupByKey = {};
	var observerStarted = false;
	var refreshTimer = null;

	function isBundleChild( extensions ) {
		return !! (
			extensions &&
			extensions[ namespace ] &&
			extensions[ namespace ].is_bundle_child
		);
	}

	function isBundleParent( extensions ) {
		return !! (
			extensions &&
			extensions[ namespace ] &&
			extensions[ namespace ].is_bundle_parent
		);
	}

	function getBundleSummaryMarkup( extensions ) {
		if (
			! extensions ||
			! extensions[ namespace ] ||
			! extensions[ namespace ].bundle_summary_markup
		) {
			return '';
		}

		return extensions[ namespace ].bundle_summary_markup;
	}

	function getCartItemKeyClass( args ) {
		if ( ! args || ! args.cartItem || ! args.cartItem.key ) {
			return '';
		}

		return 'wcpb-cart-key-' + String( args.cartItem.key ).replace( /[^a-z0-9_-]/gi, '' );
	}

	function getSanitizedCartItemKey( args ) {
		var keyClass = getCartItemKeyClass( args );

		return keyClass ? keyClass.replace( 'wcpb-cart-key-', '' ) : '';
	}

	function hideMiniCartBundleFallbacks( metadataContainer ) {
		metadataContainer
			.querySelectorAll( '.wcpb-cart-summary-detail, .wc-block-components-product-details__bundle-contents' )
			.forEach( function ( node ) {
				var wrapper = node.closest( '.wc-block-components-product-details' ) || node;
				wrapper.style.display = 'none';
			} );
	}

	function applyMiniCartBundleSummaries() {
		var rows = document.querySelectorAll( '.wc-block-mini-cart__products-table .wcpb-bundle-parent-item' );

		rows.forEach( function ( row ) {
			var match = row.className.match( /wcpb-cart-key-([A-Za-z0-9_-]+)/ );
			var key = match ? match[ 1 ] : '';
			var markup = key ? summaryMarkupByKey[ key ] : '';
			var metadataContainer;
			var host;

			if ( ! markup ) {
				return;
			}

			metadataContainer = row.querySelector( '.wc-block-components-product-metadata' );

			if ( ! metadataContainer ) {
				return;
			}

			hideMiniCartBundleFallbacks( metadataContainer );

			host = metadataContainer.querySelector( '.wcpb-mini-cart-summary-host' );

			if ( ! host ) {
				host = document.createElement( 'div' );
				host.className = 'wcpb-mini-cart-summary-host';
				metadataContainer.appendChild( host );
			}

			if ( host.innerHTML !== markup ) {
				host.innerHTML = markup;
			}
		} );
	}

	function scheduleMiniCartSummaryRefresh() {
		if ( refreshTimer ) {
			window.clearTimeout( refreshTimer );
		}

		refreshTimer = window.setTimeout( applyMiniCartBundleSummaries, 30 );
	}

	function startMiniCartObserver() {
		if ( observerStarted || ! window.MutationObserver ) {
			return;
		}

		observerStarted = true;

		new window.MutationObserver( function () {
			scheduleMiniCartSummaryRefresh();
		} ).observe( document.body, {
			childList: true,
			subtree: true,
		} );

		scheduleMiniCartSummaryRefresh();
	}

	window.wc.blocksCheckout.registerCheckoutFilters( namespace, {
		cartItemClass: function ( defaultValue, extensions, args ) {
			var classes = [ defaultValue ];
			var keyClass = getCartItemKeyClass( args );
			var sanitizedKey = getSanitizedCartItemKey( args );

			if ( ! isBundleChild( extensions ) ) {
				if ( isBundleParent( extensions ) ) {
					if ( keyClass ) {
						classes.push( 'wcpb-bundle-parent-item', keyClass );
					}

						if ( sanitizedKey ) {
							summaryMarkupByKey[ sanitizedKey ] = getBundleSummaryMarkup( extensions );
						}

					startMiniCartObserver();
					scheduleMiniCartSummaryRefresh();
				}

				return classes.filter( Boolean ).join( ' ' );
			}

			return [ defaultValue, 'wcpb-hidden-child-item', keyClass ].filter( Boolean ).join( ' ' );
		},
		showRemoveItemLink: function ( defaultValue, extensions ) {
			return isBundleChild( extensions ) ? false : defaultValue;
		},
	} );
}() );
