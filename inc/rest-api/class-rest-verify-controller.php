<?php

namespace Bluehost\Maestro;

/**
 * Class REST_SSO_Controller
 */
class REST_Verify_Controller extends \WP_REST_Controller {

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
			'/verify',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify_maestro' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

	}

	/**
	 * Verify whether the user accessing the endpoint is a Maestro
	 *
	 * @since 1.0
	 *
	 * @return WP_Rest_Response Returns a standard rest response with the Maestro status included
	 */
	public function verify_maestro() {

		$user = wp_get_current_user();

		$response = array(
			'email'      => $user->user_email,
			'is_maestro' => is_user_maestro(),
		);

		return new \WP_Rest_Response( $response );

	}

	/**
	 * Limit access to only those who can manage users
	 *
	 * Since we include email address in the response, we want to further limit who
	 * can view the details of this endpoint to avoid leaking private data
	 *
	 * @since 1.0
	 *
	 * @return boolean Whether the user can manage users or not
	 */
	public function check_permission() {

		return current_user_can( 'edit_users' );

	}

}
