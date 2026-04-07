( function ( $ ) {
	function normalizeAttributeKey( key ) {
		key = String( key || '' );

		if ( key.indexOf( 'attribute_' ) === 0 ) {
			key = key.substring( 10 );
		}

		key = key.toLowerCase().replace( /['"]/g, '' ).replace( /\s+/g, '-' ).replace( /[^a-z0-9_-]/g, '' );

		return key ? 'attribute_' + key : '';
	}

	function normalizeAttributes( attributes ) {
		var normalized = {};

		$.each( attributes || {}, function ( key, value ) {
			var normalizedKey = normalizeAttributeKey( key );

			if ( normalizedKey ) {
				normalized[ normalizedKey ] = value;
			}
		} );

		return normalized;
	}

	function getVariations( container ) {
		var data = container.attr( 'data-product-variations' ) || '[]';

		try {
			return JSON.parse( data );
		} catch ( error ) {
			return [];
		}
	}

	function getSelectedAttributes( container ) {
		var values = {};

		container.find( '.wcpb-variation-select' ).each( function () {
			var select = $( this );
			var attributeName = select.data( 'attribute_name' ) || '';
			var name = select.attr( 'name' ) || '';
			var match;

			if ( ! attributeName ) {
				match = name.match( /\[([^\]]+)\]$/ );
				attributeName = match ? match[1] : '';
			}

			values[ attributeName ] = select.val() || '';
		} );

		return normalizeAttributes( values );
	}

	function findMatchingVariation( variations, selectedAttributes ) {
		var matched = null;

		$.each( variations || [], function ( _, variation ) {
			var attributes = normalizeAttributes( variation.attributes || {} );
			var isMatch = true;

			$.each( selectedAttributes, function ( key, value ) {
				if ( ! value ) {
					isMatch = false;
					return false;
				}

				if ( typeof attributes[ key ] === 'undefined' ) {
					isMatch = false;
					return false;
				}

				if ( attributes[ key ] && attributes[ key ] !== value ) {
					isMatch = false;
					return false;
				}
			} );

			if ( isMatch ) {
				matched = variation;
				return false;
			}
		} );

		return matched;
	}

	function updateVariationState( container ) {
		var variations = getVariations( container );
		var selectedAttributes = getSelectedAttributes( container );
		var hiddenInput = container.find( '.wcpb-selected-variation-id' );
		var match = findMatchingVariation( variations, selectedAttributes );
		var hasEmpty = false;

		$.each( selectedAttributes, function ( _, value ) {
			if ( ! value ) {
				hasEmpty = true;
				return false;
			}
		} );

		if ( match && match.variation_id ) {
			hiddenInput.val( match.variation_id );
			container.removeClass( 'wcpb-variation-unresolved wcpb-variation-invalid' ).addClass( 'wcpb-variation-resolved' );
			return;
		}

		hiddenInput.val( '' );

		if ( hasEmpty ) {
			container.removeClass( 'wcpb-variation-resolved wcpb-variation-invalid' ).addClass( 'wcpb-variation-unresolved' );
			return;
		}

		if ( ! variations.length ) {
			container.removeClass( 'wcpb-variation-unresolved wcpb-variation-invalid' ).addClass( 'wcpb-variation-resolved' );
			return;
		}

		container.removeClass( 'wcpb-variation-resolved' ).addClass( 'wcpb-variation-unresolved wcpb-variation-invalid' );
	}

	function updateBundleButtonState( form ) {
		var button = form.find( '.single_add_to_cart_button' );
		var unresolvedRequired = form.find( '.wcpb-child-variations[data-required="yes"].wcpb-variation-unresolved' ).length > 0;
		var invalidRequired = form.find( '.wcpb-child-variations[data-required="yes"].wcpb-variation-invalid' ).length > 0;
		var cannotSubmit = unresolvedRequired || invalidRequired;

		button.prop( 'disabled', cannotSubmit );
		button.attr( 'aria-hidden', cannotSubmit ? 'true' : 'false' );
		button.toggle( ! cannotSubmit );
		button.toggleClass( 'disabled wc-variation-selection-needed', cannotSubmit );
	}

	$( function () {
		$( '.wcpb-bundle-form' ).each( function () {
			var form = $( this );

			form.find( '.wcpb-child-variations' ).each( function () {
				updateVariationState( $( this ) );
			} );

			updateBundleButtonState( form );
		} );

		$( document.body ).on( 'change', '.wcpb-variation-select', function () {
			var container = $( this ).closest( '.wcpb-child-variations' );
			var form = $( this ).closest( '.wcpb-bundle-form' );

			updateVariationState( container );
			updateBundleButtonState( form );
		} );
	} );
}( jQuery ) );
