<?php

namespace Bluehost\Maestro\RestApi;

use Exception;
use WP_REST_Server;
use WP_REST_Response;

use Bluehost\Maestro\WebPro;

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

        // Make sure we populate the themes updates transient
        wp_update_themes();

        // Get the installed themes and the current one
        $themes_installed = wp_get_themes();
        $current_theme = wp_get_theme();

        // Include theme functions
        require_once( ABSPATH . 'wp-admin/includes/theme.php' );

        $theme_updates = get_site_trasient( 'update_themes' );

        foreach ( $themes_wp as $theme_id => $theme ) {
            $stylesheet = $theme->get_stylesheet();
            $update = 'none';
            $update_version = '(undef)';
            if ( array_key_exists( $stylesheet, $theme_updates->response ) ) {
                $update = 'available';
                $update_version = $themes_updates->response[ $stylesheet ]['new_version'];
            }

            $screenshot_url = $theme->get_screenshot() ? $theme->get_screenshot() : 'none';
            $screenshot_url_array = $screenshot_url != 'none' ? explode( '/', $screenshot_url ) : array( 'none' );
            $filename = end( $screenshot_url_array );
            $screenshot = array(
                'url'  => $screenshot_url,
                'file' => $filename
            );

            // Get theme values
            $theme_values = array(
                'id'            => $theme_id,
                'name'          => $theme_id,
                'title'         => $theme->display( 'Name' ),
                'status'        => $theme->display( 'Name' ) == $current_theme->display( 'Name' ) ? 'active' : 'inactive',
                'version'       => $theme->get( 'Version' ),
                'update'        => $update,
                'update_vesion' => $update_version,
                'screenshot'    => $screenshot
            );
            array_push( $themes_list, $theme_values );
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
