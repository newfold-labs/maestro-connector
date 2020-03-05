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
			maestro.handle_key_response( response );
		} );

	} );

} );

maestro.handle_key_response = function ( response ) {
	response = JSON.parse( response );
	if ( 'failed' === response.status ) {
		maestro.set_message( response.message );
		maestro.set_buttons();
	}
	if ( 'success' === response.status) {
		maestro.key = response.key;
		maestro.name = response.name;
		maestro.email = response.email;
		maestro.location = response.location;

		maestro.set_message( maestro.strings.confirmMessage );
		var htmlString = "<div class='name'><span>" + maestro.strings.name + ":</span> <span>" + response.name + "</span></div>\
				<div class='email'><span>" + maestro.strings.email + ":</span> <span>" + response.email + "</span></div>\
				<div class='location'><span>" + maestro.strings.location + ":</span> <span>" + response.location + "</span></div>";
		jQuery( '.details' ).html( htmlString );
		maestro.set_buttons( 'confirm' );
	}
}

maestro.confirm_maestro = function () {

	jQuery.ajax( {
		url: maestro.urls.restAPI + '/webpros',
		method: 'POST',
		beforeSend: function ( xhr ) {
			xhr.setRequestHeader( 'X-WP-Nonce', maestro.nonces.rest );
		},
		data: {
			maestro_key: maestro.key,
			email: maestro.email,
			name: maestro.name,
		},
	} ).done( function ( response ) {
		console.log( response );
	} );
}

maestro.deny_maestro = function () {
	maestro.set_message( maestro.strings.accessDeclined );
	maestro.set_buttons();
}

maestro.handle_confirm_response = function ( response ) {
	var message = '';
	if ( 'success' === response.status ) {
		message = maestro.strings.accessGranted;
	} else {
		message = maestro.strings.genericError;
	}
	maestro.set_message( message );
	maestro.set_buttons();
}

maestro.set_message = function( message ) {
	jQuery( '.message p' ).html( message );
}

maestro.set_buttons = function ( type ) {
	var buttons = '';
	if ( 'confirm' === type ) {
		buttons = "<button onclick='maestro.deny_maestro()' class='maestro-button secondary'>" + maestro.strings.giveAccess + "</button>\
				<button onclick='maestro.confirm_maestro()' class='maestro-button primary'>" + maestro.strings.dontGiveAccess + "</button>";
	} else {
		buttons = '<a href="' + maestro.urls.usersList + '" class="maestro-button secondary">' + maestro.strings.viewAllUsers + '</a>\
			<a href="' + maestro.urls.maestroPage + '" class="maestro-button primary">' + maestro.strings.addWebPro + '</a>';
	}
	jQuery( '.maestro-content .actions' ).html( buttons );
}
