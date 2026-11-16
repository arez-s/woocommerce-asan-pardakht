( function () {
	'use strict';

	var settings = window.wc.wcSettings.getSetting( 'asan_pardakht_data', {} );
	var decodeEntities = window.wp.htmlEntities.decodeEntities;
	var createElement = window.wp.element.createElement;
	var label = decodeEntities( settings.title || 'پرداخت اینترنتی آپ' );

	var Content = function () {
		return createElement(
			'div',
			{ className: 'apgw-blocks-description' },
			decodeEntities( settings.description || '' )
		);
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'asan_pardakht',
		label: label,
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: function () { return true; },
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ]
		}
	} );
}() );

