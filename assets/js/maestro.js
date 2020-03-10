jQuery( document ).ready( function( $ ) {

	$( '.maestro-key-form .submit' ).click( function( e ) {
		e.preventDefault();

		var key = $( '.maestro-key-form .key' ).val();

		$.ajax( {
			url: maestro.urls.ajax,
			method: 'POST',
			data: {
				action: 'bh-maestro-key-check',
				key: key,
				nonce: maestro.nonces.ajax
			},
			beforeSend: function() {
				$( '.maestro-key-form .submit' ).html( '<img src="' + maestro.urls.assets + '/images/loading.svg" />' );
			},
			complete: function() {
				$( '.maestro-key-form .submit' ).html( maestro.strings.next );
			}
		} ).done( function ( response ) {
			maestro.verifyWebPro( response );
		} );

	} );

} );

maestro.verifyWebPro = function ( response ) {
	response = JSON.parse( response );
	maestro.setMessage( response.message );
	if ( 'invalid_key' !== response.status ) {
		maestro.webpro = response;
		maestro.setMessage( response.message );
		var details = "<div class='name'><span>" + maestro.strings.name + ":</span> <span>" + response.name + "</span></div>\
				<div class='email'><span>" + maestro.strings.email + ":</span> <span>" + response.email + "</span></div>\
				<div class='location'><span>" + maestro.strings.location + ":</span> <span>" + response.location + "</span></div>";
		maestro.setDetails( details );
	}
	var buttons = ( 'success' === response.status ) ? 'confirm' : '';
	maestro.setButtons( buttons );
}

maestro.confirmMaestro = function () {
	jQuery.ajax( {
		url: maestro.urls.restAPI + '/webpros',
		method: 'POST',
		beforeSend: function ( xhr ) {
			xhr.setRequestHeader( 'X-WP-Nonce', maestro.nonces.rest );
		},
		data: {
			reference_id: maestro.webpro.reference_id,
			magic_key: maestro.webpro.key,
			email: maestro.webpro.email,
		},
	} ).done( function ( response ) {
		maestro.setMessage( maestro.strings.accessGranted );
		maestro.setDetails( '' );
		maestro.setButtons();
	} );
}

maestro.denyMaestro = function () {
	maestro.setMessage( maestro.strings.accessDeclined );
	maestro.setDetails( '' );
	maestro.setButtons();
}

maestro.setMessage = function( message ) {
	jQuery( '.maestro-content .message p' ).html( message );
}

maestro.setDetails = function ( details ) {
	jQuery( '.maestro-content .details' ).html( details );
}

maestro.getButton = function ( text, type = 'button', action = '', classes = '' ) {
	var btn;
	if ( 'link' === type ) {
		btn = jQuery( '<a/>' ).attr( 'href', action );
	} else {
		btn = jQuery( '<button/>' ).attr( 'onclick', action );
	}
	return btn.addClass( 'maestro-button ' + classes ).text( text );
}

maestro.setButtons = function ( type = '' ) {
	var primary, secondary;
	if ( 'confirm' === type ) {
		secondary = maestro.getButton( maestro.strings.dontGiveAccess, 'button', 'maestro.denyMaestro()', 'secondary' );
		primary = maestro.getButton( maestro.strings.giveAccess, 'button', 'maestro.confirmMaestro()' + '', 'primary' );
	} else {
		secondary = maestro.getButton( maestro.strings.viewAllUsers, 'link', maestro.urls.usersList, 'secondary' );
		primary = maestro.getButton( maestro.strings.addWebPro, 'link', maestro.urls.maestroPage, 'primary' );
	}
	jQuery( '.maestro-content .actions' ).html('').prepend( [secondary, primary] );
}
