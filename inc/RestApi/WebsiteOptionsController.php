<?php

namespace Bluehost\Maestro\RestApi;

use Exception;
use WP_REST_Server;
use WP_REST_Response;

use Bluehost\Maestro\WebsiteOptions;
use Bluehost\Maestro\WebPro;

/**
 * Class WebsiteOptionsController
 */
class WebsiteOptionsController extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	protected $namespace = 'bluehost/maestro/v1';

	/**
	 * The current Web Pro accessing the endpoint
	 *
	 * @since 1.1.1
	 *
	 * @var WebPro
	 */
	private $webpro;

	/**
	 * Registers the Plugins routes
	 *
	 * @since 1.1.1
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/website-options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_website_options' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

	}

	/**
	 * Callback for the plugins get endpoint
	 *
	 * Returns a list of installed plugins with details and updates
	 *
	 * @since 1.1.1
	 *
	 * @return WP_Rest_Response Returns a standard rest response with a list of plugins
	 */
	public function get_website_options() {
		$website_options = new WebsiteOptions();
		return new WP_Rest_Response( $website_options );
	}

	/**
	 * Verify permission to access this endpoint
	 *
	 * Authenticating a WebPro user via token
	 *
	 * @since 1.1.1
	 *
	 * @return boolean Whether to allow access to endpoint.
	 */
	public function check_permission() {

		// We want to SSO into the same user making the current request
		// User is also already verified as a Maestro using the permission callback
		$user_id = get_current_user_id();

		try {
			$this->webpro = new WebPro( $user_id );
		} catch ( Exception $e ) {
			return false;
		}

		return $this->webpro->is_connected();

	}
}
