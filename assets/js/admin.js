( function ( $, wp ) {
	function initSearch( context ) {
		context.find( '.wc-product-search' ).filter( ':not(.enhanced)' ).each( function () {
			if ( $.fn.wc_product_search ) {
				$( this ).wc_product_search();
			} else {
				$( this ).trigger( 'wc-enhanced-select-init' );
			}
		} );
	}

	function bindVariationSync( context ) {
		context.find( '.wcpb-product-search' ).off( 'change.wcpb' ).on( 'change.wcpb', function () {
			var value = String( $( this ).val() || '' );
			var parts = value.split( ':' );
			var row = $( this ).closest( '.wcpb-item-row' );

			if ( parts.length > 1 ) {
				row.find( '.wcpb-variation-id' ).val( parts[1] );
				$( this ).val( parts[0] ).trigger( 'change.select2' );
			} else {
				row.find( '.wcpb-variation-id' ).val( 0 );
			}
		} );
	}

	function nextIndex( group ) {
		var indexes = group.find( '.wcpb-item-row' ).map( function () {
			return parseInt( $( this ).data( 'index' ), 10 ) || 0;
		} ).get();

		return indexes.length ? Math.max.apply( null, indexes ) + 1 : 0;
	}

	$( function () {
		var bundleTemplate = wp.template( 'wcpb-item-row' );
		var productType = $( '#product-type' );

		function togglePanels() {
			$( 'body' ).toggleClass( 'wcpb-is-bundle', productType.val() === 'bundle' );
		}

		togglePanels();
		productType.on( 'change', togglePanels );

		$( document.body ).on( 'click', '.wcpb-add-row', function ( event ) {
			event.preventDefault();

			var button = $( this );
			var role = button.data( 'role' );
			var group = button.closest( '.wcpb-item-group' );
			var index = nextIndex( group );
			var html = bundleTemplate( { index: index } );
			var row = $( html ).attr( 'data-index', index );

			row.attr( 'data-role', role );
			row.find( 'select, input' ).each( function () {
				$( this ).attr( 'name', $( this ).attr( 'name' ).replace( 'wcpb_bundle_items', role === 'optional' ? 'wcpb_optional_items' : 'wcpb_bundle_items' ) );
			} );

			if ( role === 'optional' ) {
				row.attr( 'data-role', 'optional' );
			}

			group.find( '.wcpb-item-list' ).append( row );
			initSearch( row );
			bindVariationSync( row );
		} );

		$( document.body ).on( 'click', '.wcpb-remove-row', function ( event ) {
			event.preventDefault();
			$( this ).closest( '.wcpb-item-row' ).remove();
		} );

		initSearch( $( document.body ) );
		bindVariationSync( $( document.body ) );
	} );
}( jQuery, window.wp ) );
