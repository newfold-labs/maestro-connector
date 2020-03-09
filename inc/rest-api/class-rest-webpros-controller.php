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
				__( 'Sorry, you are not allowed to access this endpoint.' ),
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
				__( 'Sorry, you are not allowed to grant access to web pros.' ),
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
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$webpro = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $webpro ) ) {
			return $webpro;
		}

		$webpro   = $this->prepare_item_for_response( $webpro, $request );
		$response = rest_ensure_response( $webpro );

		return $response;
	}

	/**
	 * Create a new Maestro platform connection
	 *
	 * Verifies a supplied maestro_key & email against the Maestro platform,
	 * generates a JWT, and sends the access token to the platform.
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {

		$webpro = new Web_Pro( $request['maestro_key'] );

		$webpro->connect();

		$response = $this->prepare_item_for_response( $webpro, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $webpro->user->ID ) ) );

		return $response;
	}

	/**
	 * Update maestro key for an existing web pro
	 *
	 * This endpoint is used to invalidate a previously issued JWT and generate a new one.
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		// Make sure we're working on a web pro
		$webpro = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $webpro ) ) {
			return $webpro;
		}

		$webpro->set_key( $request['maestro_key'] );

		$response = $this->prepare_item_for_response( $webpro, $request );
		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Revokes a maestro platform connection
	 *
	 * This does a few things:
	 *   - Deletes the stored maestro_key, which invalidates previously issued tokens
	 *   - Demotes the user to a subscriber role
	 *   - Sends a request to the Maestro platform to notify that the old token is now invalid
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		// Make sure we're working on a web pro
		$webpro = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $webpro ) ) {
			return $webpro;
		}

		$deleted = $webpro->disconnect();

		if ( ! $deleted ) {
			return new WP_Error(
				'maestro_revoke_failed',
				__( 'Failed to revoke Maestro status' ),
				array( 'status' => 500 ),
			);
		}

		// @todo Need response for successful REST delete

	}

	/**
	 * Retrieves web pro details about the user accessing the endpoint
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_current_item( $request ) {
		// Ensure the current user is a webpro before continuing
		$webpro = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $webpro ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $webpro->user->ID;

		return $this->get_item( $request );
	}

	/**
	 * Update maestro key for an existing web pro
	 *
	 * This endpoint is used to invalidate a previously issued JWT and generate a new one.
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_current_item( $request ) {
		// Ensure current user is a webpro before continuing
		$webpro = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $webpro ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $webpro->user->ID;

		return $this->update_item( $request );
	}

	/**
	 * Revokes a maestro platform connection for the current user
	 *
	 * Calls delete_item() method on the current user
	 *
	 * @since 1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_current_item( $request ) {
		// Ensure current user is a webpro before continuing
		$webpro = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $webpro ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $webpro->user->ID;

		return $this->delete_item( $request );
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
			$webpro = new Web_Pro( $user->ID );
			// Skip and do not return user if it isn't a valid connection
			if ( ! $webpro->is_connected() ) {
				continue;
			}
			$data[] = $this->prepare_item_for_response( $webpro, $request );
		}

		return rest_ensure_response( $data );
	}

	public function get_collection_params() {
		// @todo Build out get_collection_parms() method
	}

	/**
	 * Prepares a single webpro's details for response.
	 *
	 * @since 1.0
	 *
	 * @param Web_Pro         $webpro  Web Pro user object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $webpro, $request ) {

		$response = array(
			'id'         => $webpro->user->ID,
			'username'   => $webpro->user->user_login,
			'first_name' => $webpro->first_name,
			'last_name'  => $webpro->last_name,
			'email'      => $webpro->email,
			'location'   => $webpro->location,
			'added_by'   => $webpro->added_by,
			'added_time' => $webpro->added_time,
		);

		return $response;

	}

	/**
	 * Get the Web_Pro object for the webpro, if the ID is valid.
	 *
	 * @since 1.0
	 *
	 * @param int $id Supplied ID.
	 *
	 * @return Web_Pro|WP_Error Web_Pro object if ID is valid, WP_Error otherwise.
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

		$webpro = new Web_Pro( $id );

		if ( ! $webpro->is_connected() ) {
			return $error;
		}

		return $webpro;
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
					'readonly'    => true, // Either exists, or auto-generated from email
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_username' ),
					),
				),
				'name'        => array(
					'description' => __( 'Display name for the user.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true, // Either exists, or is supplied from the platform based on key
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'email'       => array(
					'description' => __( 'The email address for the user.' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
				),
				'added_by'    => array(
					'description' => __( 'The user who approved the Maestro connection.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true, // This can only be set programmatically
				),
				'added_time'  => array(
					'description' => __( 'Time when the Maestro connection was approved.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true, // This can only be set programmatically
				),
				'maestro_key' => array(
					'description' => __( 'The maestro identifier key for the webpro.' ),
					'type'        => 'string',
					'context'     => array(), // Maestro key doesn't get displayed
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);

		$this->schema = $schema;

		return $this->schema;
	}
}
