( function () {
	'use strict';

	var wc = window.wc || {};
	var registry = wc.wcBlocksRegistry || {};
	var settingsApi = wc.wcSettings || {};
	var wp = window.wp || {};
	var element = wp.element || {};
	var htmlEntities = wp.htmlEntities || {};

	if (
		typeof registry.registerPaymentMethod !== 'function' ||
		typeof settingsApi.getSetting !== 'function'
	) {
		return;
	}

	var settings = settingsApi.getSetting( 'payisland_data', {} );
	var decodeEntities = htmlEntities.decodeEntities || function ( value ) {
		return value;
	};
	var createElement = element.createElement;
	var title = decodeEntities( settings.title || 'PayIsland' );
	var description = decodeEntities(
		settings.description || 'Pay securely using PayIsland.'
	);
	var supports = settings.supports || [ 'products' ];

	var Content = function () {
		if ( typeof createElement !== 'function' ) {
			return description;
		}

		return createElement(
			'div',
			{
				className: 'payisland-blocks-payment-method',
			},
			description
		);
	};

	registry.registerPaymentMethod( {
		name: 'payisland',
		label: title,
		content: createElement ? createElement( Content, null ) : description,
		edit: createElement ? createElement( Content, null ) : description,
		canMakePayment: function () {
			return true;
		},
		ariaLabel: 'PayIsland payment method',
		supports: {
			features: supports,
		},
	} );
}() );
