<?php

namespace Bluehost\Maestro;

/**
 * Class REST_SSO_Controller
 */
class REST_SSO_Controller extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $namespace = 'bluehost/maestro/v1';

	/**
	 * Registers the SSO route
	 *
	 * @since 1.0
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/sso',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'new_sso' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

	}

	/**
	 * Callback for the SSO Endpoint
	 *
	 * Returns a short-lived JWT that can be passed to wp-login for instant authentication
	 *
	 * @since 1.0
	 *
	 * @return WP_Rest_Response Returns a standard rest response with the SSO link included
	 */
	public function new_sso() {

		// We want to SSO into the same user making the current request
		// User is also already verified as a Maestro using the permission callback
		$user = wp_get_current_user();

		$webpro = new Web_Pro( $user->ID );

		// Create a temporary single-use JWT; expires in 30 seconds
		$token = new Token();
		$jwt   = $token->generate_token( $webpro, 30, true, array( 'type' => 'sso' ) );

		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		// Parms for the auto-login URL
		$params   = array(
			'action' => 'bh-maestro-sso',
			'token'  => $jwt,
		);
		$link     = add_query_arg( $params, admin_url( 'admin-ajax.php' ) );
		$response = array( 'link' => $link );

		return new \WP_Rest_Response( $response );
	}

	/**
	 * Verify permission to access this endopint
	 *
	 * By registering a permission callback, we already limit the endpoint to authenticated users,
	 * but we should also verify the actual current user making the request is a connected Web Pro.
	 * Regular admins shouldn't be able to use this endpoint. They should log in like normal.
	 *
	 * @since 1.0
	 *
	 * @return boolean Whether to allow access to endpoint.
	 */
	public function check_permission() {

		$user   = wp_get_current_user();
		$webpro = new Web_Pro( $user->ID );

		return $webpro->is_connected();

	}

}
