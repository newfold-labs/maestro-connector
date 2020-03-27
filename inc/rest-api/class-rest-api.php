<?php

namespace Bluehost\Maestro;

class REST_API {

	public function __construct() {

		$this->register_routes();

		add_action( 'rest_authentication_errors', array( $this, 'authenticate' ) );

	}

	/**
	 * Registers all custom REST API routes
	 *
	 * @since 1.0
	 */
	public function register_routes() {

		$controllers = array(
			'REST_SSO_Controller',
			'REST_Webpros_Controller',
		);

		foreach ( $controllers as $controller ) {
			$class    = __NAMESPACE__ . '\\' . $controller;
			$instance = new $class();
			$instance->register_routes();
		}

	}

	/**
	 * Attempt to authenticate the REST API request
	 *
	 * @since 1.0
	 *
	 * @param mixed $result Result of any other authentication attempts
	 *
	 * @return WP_Error|null|bool
	 */
	public function authenticate( $status ) {

		// Make sure there wasn't a different authentication method used before this
		if ( ! is_null( $status ) ) {
			return $status;
		}

		// Make sure this is a REST API request
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $status;
		}

		$jwt = $this->get_access_token();

		// If no auth header included, bail to allow a different auth method
		if ( is_null( $jwt ) ) {
			return null;
		}

		$token = new Token();
		if ( ! $token->validate_token( $jwt ) ) {
			// Return the WP_Error for why the token wansn't validated
			return $token;
		}
		$decoded_token = $token->decode_token( $jwt );

		// Token is valid, so let's set the current user
		wp_set_current_user( $decoded_token->data->user->id );

		return true;
	}

	/**
	 * Get the token from the Maestro-Authorization header
	 *
	 * @since 1.0
	 *
	 * @return null|string The token from the header or null
	 */
	function get_access_token() {
		$token = null;
		if ( ! empty( $_SERVER['HTTP_MAESTRO_AUTHORIZATION'] ) ) {
			$token = wp_unslash( $_SERVER['HTTP_MAESTRO_AUTHORIZATION'] );
		}

		// Use getallheaders in case the HTTP_MAESTRO_AUTHORIZATION header is stripped by a server configuration
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();

			// Check for the authorization header case-insensitively
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'maestro-authorization' ) {
					$token = $value;
				}
			}
		}

		return $token;
	}

}
