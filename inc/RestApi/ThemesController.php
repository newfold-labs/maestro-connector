<?php

namespace Bluehost\Maestro\RestApi;

use Exception;
use WP_REST_Server;
use WP_REST_Response;

use Bluehost\Maestro\Theme;
use Bluehost\Maestro\Webpro;

/**
 * Class REST_Themes_Controller
 */
class ThemesController extends \WP_REST_Controller {

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
     * Regusters the Themes routes
     * 
     * @since 1.1.1
     */
    public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/themes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_themes' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

	}

    /**
     * Callback for the themes get endpoint
     * 
     * Returns a list of installed themes with id, name, title
     * status, version, update, update_version and screenshot
     * 
     * @since 1.1.1
     * 
     * @return WP_Rest_Response Returns a standard rest response with a list of themes
     */
    public function get_themes() {
        $themes_list = array();
        $themes_installed = wp_get_themes();

        foreach ( $themes_installed as $theme_id => $theme_wp ) {
            $theme = new Theme( $theme_id, $theme_wp );
            array_push( $themes_list, $theme );
        }

        $response = array( $themes_list );

        return new WP_Rest_Response( $response );
    }

    /**
     * Verify permission to access this endpoint
     * 
     * Autheinticating a Webpro user via token 
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
