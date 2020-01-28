<?php

namespace Bluehost\Maestro;

use WP_REST_Server, WP_Error, WP_User_Query;

/**
 * Class REST_SSO_Controller
 */
class REST_Webpros_Controller extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $namespace = 'bluehost/maestro/v1';

	/**
	 * The base for each endpoint on this route
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $base = 'webpros';

	/**
	 * Registers the SSO route
	 *
	 * @since 1.0
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_users_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the user.' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_current_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_current_item' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

	}

	/**
	 * Permissions callback to ensure only users with ability to edit users can access the endpoint
	 *
	 * @since 1.0
	 *
	 * @return bool Whether user as edit_users capability
	 */
	public function edit_users_permissions_check() {
		if ( ! current_user_can( 'edit_users' ) ) {
			return new WP_Error(
				'rest_maestro_forbidden',
				__( 'Sorry, you are not allowed to access this endpoint.', 'bluehost-maestro' ),
				array( 'status' => rest_authorization_required_code() ),
			);
		}

		return true;
	}

	/**
	 * Permissions callback to ensure only users with ability to create users can grant new access
	 *
	 * We check this differently than edit_users because sometimes a new user must be created during the connection
	 *
	 * @since 1.0
	 *
	 * @return bool Whether user as create_users capability
	 */
	public function create_users_permissions_check() {
		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error(
				'rest_maestro_cannot_approve_connection',
				__( 'Sorry, you are not allowed to grant access to web pros.', 'bluehost-maestro' ),
				array( 'status' => rest_authorization_required_code() ),
			);
		}

		return true;
	}

	/**
	 * Retrieves information about a single webpro
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$user = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$webpro   = $this->prepare_item_for_response( $user, $request );
		$response = rest_ensure_response( $webpro );

		return $response;
	}

	public function create_item( $request ) {
		// @todo Build out create_item() method
	}

	public function update_item( $request ) {
		// @todo Build out update_item() method
	}

	public function delete_item( $request ) {
		// @todo Build out delete_item() method
	}

	public function get_current_item( $request ) {
		// @todo Build out get_current_item() method
		// Note: This will verify web pro status and return "You are not a webpro" or something similar
	}

	public function update_current_item( $request ) {
		// @todo Build out update_current_item() method
		// Note: Will need to verify webpro status before updating - must be a web pro already
	}

	public function delete_current_item( $request ) {
		// @todo Build out delete_current_item() method
		// Note: Will need to verify webpro status before updating - must be a web pro already
	}

	/**
	 * Retrieves all webpros
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$query = new WP_User_Query( array( 'meta_key' => 'bh_maestro_key' ) );

		foreach ( $query->results as $user ) {
			// Skip and do not return user if the maestro key is falsey
			if ( ! get_maestro_key( $user->ID ) ) {
				continue;
			}
			$data[] = $this->prepare_item_for_response( $user, $request );
		}

		return rest_ensure_response( $data );
	}

	public function get_collection_params() {
		// @todo Build out get_collection_parms() method
	}

	/**
	 * Prepares a single webpro's details response.
	 *
	 * @since 1.0
	 *
	 * @param WP_User         $user    User object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $user, $request ) {

		$response = array(
			'id'         => $user->ID,
			'username'   => $user->user_login,
			'name'       => $user->display_name,
			'email'      => $user->user_email,
			'added_by'   => get_user_meta( $user->ID, 'bh_maestro_added_by', true ),
			'added_time' => (int) get_user_meta( $user->ID, 'bh_maestro_added_date', true ),
		);

		return $response;

	}

	/**
	 * Get the WP_User object for the webpro, if the ID is valid.
	 *
	 * @since 1.0
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return WP_User|WP_Error True if ID is valid, WP_Error otherwise.
	 */
	protected function get_webpro( $id ) {
		$error = new WP_Error( 'rest_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
		if ( (int) $id <= 0 ) {
			return $error;
		}

		$user = get_userdata( (int) $id );
		if ( empty( $user ) || ! $user->exists() ) {
			return $error;
		}

		if ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) {
			return $error;
		}

		if ( ! is_user_maestro( $user->ID ) ) {
			return $error;
		}

		return $user;
	}

	/**
	 * Check a username for the REST API.
	 *
	 * This is duplicated from the WP_REST_Users_Controller class because we should follow the same rules
	 *
	 * @since 1.0
	 *
	 * @param mixed           $value   The username submitted in the request.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter name.
	 *
	 * @return WP_Error|string The sanitized username, if valid, otherwise an error.
	 */
	public function check_username( $value, $request, $param ) {
		$username = (string) $value;

		if ( ! validate_username( $username ) ) {
			return new WP_Error( 'rest_user_invalid_username', __( 'Username contains invalid characters.' ), array( 'status' => 400 ) );
		}

		/** This filter is documented in wp-includes/user.php */
		$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

		if ( in_array( strtolower( $username ), array_map( 'strtolower', $illegal_logins ), true ) ) {
			return new WP_Error( 'rest_user_invalid_username', __( 'Sorry, that username is not allowed.' ), array( 'status' => 400 ) );
		}

		return $username;
	}

	public function verify_maestro_key( $key ) {
		// @todo write method to verify maestro key against platform
	}

	/**
	 * Retrieves the webpro user schema, conforming to JSON Schema.
	 *
	 * @since 1.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {

		if ( $this->schema ) {
			return $this->schema;
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'webpro',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the user.' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'username'    => array(
					'description' => __( 'Login name for the user.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_username' ),
					),
				),
				'name'        => array(
					'description' => __( 'Display name for the user.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'email'       => array(
					'description' => __( 'The email address for the user.' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'added_by'    => array(
					'description' => __( 'The user who approved the Maestro connection.', 'bluehost-maestro' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true, // This can only be set programmatically
				),
				'added_time'  => array(
					'description' => __( 'Time when the Maestro connection was approved.', 'bluehost-maestro' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true, // This can only be set programmatically
				),
				'maestro_key' => array(
					'description' => __( 'The maestro identifier key for the webpro.', 'bluehost-maestro' ),
					'type'        => 'string',
					'context'     => array(), // Maestro key doesn't get displayed
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'verify_maestro_key' ),
					),
				),
			),
		);

		$this->schema = $schema;

		return $this->schema;
	}
}
