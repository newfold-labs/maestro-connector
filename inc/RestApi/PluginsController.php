<?php

namespace Bluehost\Maestro\RestApi;

use Exception;
use WP_REST_Server;
use WP_REST_Response;

use Bluehost\Maestro\Plugin;
use Bluehost\Maestro\WebPro;

/**
 * Class PluginsController
 */
class PluginsController extends \WP_REST_Controller {

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
			'/plugins',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugins' ),
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
	public function get_plugins() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_option' ) ) {
			include_once ABSPATH . 'wp-includes/options.php';
		}

		// Make sure we populate the plugins updates transient
		wp_update_plugins();

		$installed_plugins = get_plugins();
		$updates           = get_site_transient( 'update_plugins' );
		$auto_updates      = get_option( 'auto_update_plugins' );
		$plugins           = array();

		foreach ( $installed_plugins as $plugin_slug => $plugin_details ) {
			$plugin = new Plugin( $plugin_slug, $updates, $plugin_details, $auto_updates );
			array_push( $plugins, $plugin );
		}

		return new WP_Rest_Response(
			array(
				'plugins'            => $plugins,
				'auto_update_global' => get_option( 'auto_update_plugin' ),
				'last_checked'       => $updates->last_checked,
			)
		);
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
