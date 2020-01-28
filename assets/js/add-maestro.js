jQuery( document ).ready( function( $ ) {

	$( '.maestro-key-form .submit' ).click( function( e ) {
		e.preventDefault();
		$(this).html( '<img src="' + maestro.assetsDir + '/images/loading.svg" />' );
		var key = $( '.maestro-key-form .key' ).val();

		$.ajax( {
			type: 'POST',
			dataType: 'json',
			url: maestro.ajaxURL,
			data: { action: 'bh-maestro-key-check', key: key, nonce: maestro.nonce },
			success: maestro.handle_key_response
		} );

	} );

} );

maestro.handle_key_response = function ( response ) {
	maestro.key = response.key;
	maestro.name = response.name;
	maestro.email = response.email;
	maestro.location = response.location;
	var htmlString = '';
	if ( 'success' === response.status) {
		var htmlString = "<p style='font-size:150%;'>Let's double-check this: Make sure the name<br />below matches the name of your web pro.</p>\
			<div class='maestro-info' style='margin-bottom: 20px;'>\
				<strong>Name:</strong> " + response.name + " <br />\
				<strong>Email:</strong> " + response.email + "<br />\
				<strong>Location:</strong> " + response.location + "<br />\
			</div>\
			<div class='confirm-maestro'>\
				<button onclick='maestro.deny_maestro()' class='maestro-button secondary'>Don't give access</button>\
				<button onclick='maestro.confirm_maestro()' class='maestro-button primary'>Give access</button>\
			</div>";
	} else {

	}
	jQuery( '.maestro-content' ).html( htmlString );
}

maestro.confirm_maestro = function () {
	jQuery.ajax( {
		type: 'POST',
		dataType: 'json',
		url: maestro.ajaxURL,
		data: { action: 'bh-maestro-confirm', approve: true, key: maestro.key, nonce: maestro.nonce, email: maestro.email },
		success: maestro.handle_confirm_response
	} );
}

maestro.deny_maestro = function () {
	message = '<p class="thin">Got it. That web professional does not have access to your site.</p>';
	jQuery( '.maestro-content' ).html( message );
	maestro.add_buttons();
}

maestro.handle_confirm_response = function ( response ) {
	var message = '';
	if ( 'success' === response.status ) {
		message = "You've successfully given your web professional administrative access to your site.";
	} else {
		message = 'An error occured.';
	}
	jQuery( '.maestro-content' ).html( '<p class="thin">' + message + '</p>' );
	maestro.add_buttons();
}

maestro.add_buttons = function () {
	var buttons = '<div class="buttons">\
		<a href="' + maestro.siteURL + '/wp-admin/users.php" class="maestro-button secondary">View all Users</a>\
		<a href="' + window.location.href + '" class="maestro-button primary">Add a Web Pro</a>\
	</div>';
	jQuery( '.maestro-content p' ).after( buttons );
}
