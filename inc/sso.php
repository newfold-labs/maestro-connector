<?php

namespace Bluehost\Maestro;


// Ajax hooks for SSO
add_action( 'wp_ajax_nopriv_bh-maestro-sso', __NAMESPACE__ . '\\authenticate_sso' );
add_action( 'wp_ajax_bh-maestro-sso', __NAMESPACE__ . '\\authenticate_sso' );

/**
 * Admin-ajax callback to automatically log a user in
 *
 * Requires a valid token URL parameter generated from the plugin's REST SSO endpoint
 */
function authenticate_sso() {

	$jwt = filter_input( INPUT_GET, 'token', FILTER_SANITIZE_STRING );

	// Can't continue without a token
	if ( ! $jwt ) {
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	// Redirect to wp-login.php when there are 5 failed SSO login attempts in a 5 minute period
	$attempts = failed_sso_attempts();
	if ( $attempts > 4 ) {
		do_action( 'bh_maestro_sso_fail' );
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	// Validate the provided single-use token.
	$token = new Token();
	$data  = $token->validate_token( $jwt, true );

	// If token is invalid, or if somehow the token provided
	// was not registered as an sso token, then stop here.
	if ( is_wp_error( $data ) || 'sso' !== $data->data->type ) {
		failed_sso_attempts( 1 );
		do_action( 'bh_maestro_sso_fail' );
		wp_die(
			__( 'Invalid token.' ),
			403
		);
	}

	// Log in the user specified in the decoded token.
	wp_set_current_user( $data->user->id );
	wp_set_auth_cookie( $data->user->id, false, true );

	$user = get_userdata( $data->user->id );
	do_action( 'wp_login', $data->user->user_login, $user );

	// Add to a short log of SSO logins
	log_sso( $user );

	wp_safe_redirect( admin_url() );
	wp_die();

}

/**
 * Keeps a log of the last 10 SSO logins
 *
 * @since 1.0
 *
 * @param WP_User $user The user who is doing an SSO login
 */
function log_sso( $user ) {
	$log = maybe_unserialize( get_option( 'bh_maestro_sso_log', '' ) );

	if ( count( $log ) > 9 ) {
		array_pop( $log );
	}

	$log[] = array(
		'user' => $user->user_login,
		'time' => time(),
	);

	update_option( 'bh_maestro_sso_log', $log );

}

/**
 * Get and/or increment failed SSO attempts.
 *
 * @since 1.0
 *
 * @param int $increment The number of prior login attempts
 *
 * @return int The number of login attempts
 */
function failed_sso_attempts( $increment = 0 ) {
	static $attempts;

	$key = 'bh_maestro_sso_failures';
	if ( ! isset( $attempts ) ) {
		$attempts = absint( get_transient( $key ) );
	}
	if ( $increment ) {
		$attempts += $increment;
		set_transient( $key, $attempts, MINUTE_IN_SECONDS * 5 );
	}

	return $attempts;
}
