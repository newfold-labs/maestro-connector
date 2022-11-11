<?php

namespace Bluehost\Maestro\RestApi;

use Exception;
use WP_REST_Server;
use WP_REST_Response;
use Plugin_Upgrader;

use Bluehost\Maestro\Plugin;
use Bluehost\Maestro\WebPro;
use Bluehost\Maestro\PluginUpgraderSkin;
use Bluehost\Maestro\Util;

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

		register_rest_route(
			$this->namespace,
			'/plugins/upgrade',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'upgrade_plugin' ),
					'args'                => array(
						'slug' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

	}

	/**
	 * Function to include the required classes and files
	 *
	 * @since 1.1.1
	 */
	private function load_wp_classes_and_functions() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'get_option' ) ) {
			include_once ABSPATH . 'wp-includes/options.php';
		}

		if ( ! function_exists( 'plugin_dir_path' ) ) {
			include_once ABSPATH . 'wp-includes/plugin.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}

		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		if ( ! class_exists( 'Plugin_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';
		}
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
		$this->load_wp_classes_and_functions();

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

		$util        = new Util();
		$is_bluehost = $util->is_bluehost();

		return new WP_Rest_Response(
			array(
				'plugins'            => $plugins,
				'auto_update_global' => $is_bluehost ? get_option( 'auto_update_plugin' ) : null,
				'last_checked'       => $updates->last_checked,
			)
		);
	}

	/**
	 * Callback to upgrade a plugin with it's slug
	 *
	 * Returns the plugin's version, status, slug
	 *
	 * @since 1.1.1
	 *
	 * @param WP_REST_Request $request details about the plugin slug
	 *
	 * @return WP_Rest_Response Returns a standard rest response with the plugin's information
	 */
	public function upgrade_plugin( $request ) {
		$this->load_wp_classes_and_functions();

		wp_update_plugins();

		$util              = new Util();
		$plugin_slug       = $request['slug'];
		$installed_plugins = get_plugins();
		$updates           = get_site_transient( 'update_plugins' );
		$plugin_file       = $util->get_plugin_file_from_slug( $installed_plugins, $plugin_slug );
		$plugin_details    = get_plugin_data( WP_PLUGIN_DIR . "/$plugin_file" );

		if ( array_key_exists( $plugin_file, $updates->response ) ) {
			$update_response = $updates->response[ $plugin_file ];
		}

		if ( ! isset( $update_response ) ) {
			return new WP_Rest_Response(
				array(
					'error' => 'Plugin already up to date',
					'code'  => 'alreadyUpdated',
				),
				400
			);
		} else {
			$plugin_upgrader = new Plugin_Upgrader( new PluginUpgraderSkin( array( '', '', '', '' ) ) );
			$upgraded        = $plugin_upgrader->upgrade( $update_response->plugin );
			// Get the upgraded version
			$plugin_details = get_plugin_data( WP_PLUGIN_DIR . "/$plugin_file" );
		}

		return new WP_Rest_Response(
			array(
				'slug'    => $plugin_slug,
				'version' => $plugin_details['Version'],
				'success' => $upgraded,
			)
		);
	}

	/**
	 * Verify permission to access this endpoint
	 *
	 * Authenticating a Webpro user via token
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
