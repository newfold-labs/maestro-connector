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
	if ( 'failed' === response.status ) {
		maestro.setMessage( response.message );
		maestro.setButtons();
	}
	if ( 'success' === response.status) {
		maestro.key = response.key;
		maestro.name = response.name;
		maestro.email = response.email;
		maestro.location = response.location;

		maestro.setMessage( maestro.strings.confirmMessage );
		var htmlString = "<div class='name'><span>" + maestro.strings.name + ":</span> <span>" + response.name + "</span></div>\
				<div class='email'><span>" + maestro.strings.email + ":</span> <span>" + response.email + "</span></div>\
				<div class='location'><span>" + maestro.strings.location + ":</span> <span>" + response.location + "</span></div>";
		maestro.setDetails( htmlString );
		maestro.setButtons( 'confirm' );
	}
}

maestro.confirmMaestro = function () {

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
	jQuery( '.message p' ).html( message );
}

maestro.setDetails = function ( message ) {
	jQuery( '.details').html( message );
}

maestro.setButtons = function ( type = '' ) {
	var buttons = '';
	if ( 'confirm' === type ) {
		buttons = "<button onclick='maestro.denyMaestro()' class='maestro-button secondary'>" + maestro.strings.dontGiveAccess + "</button>\
				<button onclick='maestro.confirmMaestro()' class='maestro-button primary'>" + maestro.strings.giveAccess + "</button>";
	} else {
		buttons = '<a href="' + maestro.urls.usersList + '" class="maestro-button secondary">' + maestro.strings.viewAllUsers + '</a>\
			<a href="' + maestro.urls.maestroPage + '" class="maestro-button primary">' + maestro.strings.addWebPro + '</a>';
	}
	jQuery( '.actions' ).html( buttons );
}
