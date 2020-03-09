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
		$user = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$webpro   = $this->prepare_item_for_response( $user, $request );
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

		$maestro_info = $this->verify_maestro_key( $request['maestro_key'], $request['email'] );
		if ( is_wp_error( $maestro_info ) ) {
			return $maestro_info;
		}

		// Check for an existing user
		$user = get_user_by( 'email', $request['email'] );

		// If user doesn't exist, we need to create one
		if ( ! $user ) {
			// We need a username. If one is not provided, we'll create one from the email
			if ( ! isset( $request['username'] ) ) {
				$user_login = substr( $request['email'], 0, strrpos( $request['email'], '@' ) );
			} else {
				$user_login = $request['username'];
			}

			$name = ( isset( $request['name'] ) ) ? $request['name'] : $maestro_info['name'];

			$userdata = array(
				// We generate a password because the user has to have one,
				// but it is never shown anywhere, so it can't be used.
				'user_pass'    => wp_generate_password( 20, true ),
				'user_login'   => sanitize_user( $user_login ),
				'user_email'   => $request['email'],
				'display_name' => $name,
				'role'         => 'administrator',
			);

			$id   = wp_insert_user( $userdata );
			$user = get_userdata( $id );
		}

		// Make sure they are an administrator
		$user->set_role( 'administrator' );

		// Store the supplied Maestro key
		add_maestro_key( $user->ID, $request['maestro_key'] );

		// Save information about who approved the connection and when
		add_user_meta( $user->ID, 'bh_maestro_added_by', wp_get_current_user()->user_login, true );
		add_user_meta( $user->ID, 'bh_maestro_added_date', time(), true );

		$this->send_access_token( $user, $request );

		$response = $this->prepare_item_for_response( $user, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $user->ID ) ) );

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
		$user = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$email = ( isset( $request['email'] ) ) ? $request['email'] : $user->user_email;

		$maestro_info = $this->verify_maestro_key( $request['maestro_key'], $email );
		if ( is_wp_error( $maestro_info ) ) {
			return $maestro_info;
		}

		// Replace the existing Maestro key
		// This invalidates any previously issued tokens
		update_maestro_key( $user->ID, $request['maestro_key'] );

		// Generate a new token and send it to the platform
		$this->send_access_token( $user, $request );

		$response = $this->prepare_item_for_response( $user, $request );
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
		$user = $this->get_webpro( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$deleted = delete_maestro_key( $user->ID );

		// Kick an error if we failed to delete the key for some reason
		if ( ! $deleted ) {
			return new WP_Error(
				'maestro_revoke_failed',
				__( 'Failed to revoke Maestro status' ),
				array( 'status' => 500 ),
			);
		}

		// If we successfully deleted a key, then let's also demote the user
		$user = get_userdata( $user->ID );
		$user->set_role( 'subscriber' );

		// @todo Notify platform that connection is revoked
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
		$user = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $user->ID;

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
		$user = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $user->ID;

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
		$user = $this->get_webpro( wp_get_current_user()->ID );
		if ( is_wp_error( $user ) ) {
			return new WP_Error(
				'maestro_rest_not_webpro',
				__( 'You are not an authorized web pro.' ),
				array( 'status' => 401 ),
			);
		}

		// Manually set id parameter to the ID of the current user
		$request['id'] = $user->ID;

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

	/**
	 * Validates a key and email against the Maestro platform
	 *
	 * @since 1.0
	 *
	 * @param string $key   The maestro key generated on the platform
	 * @param string $email The email address that should be associated with the key
	 *
	 * @return WP_Error|array An array of information about the web pro provide by the platform, or an error message on failure
	 */
	public function verify_maestro_key( $key, $email ) {
		// Verify the key with the platform before continuing
		$maestro_info = get_maestro_info( $key );

		if ( is_wp_error( $maestro_info ) ) {
			return new WP_Error(
				'maestro_rest_invalid_key',
				__( 'Invalid maestro key.', 'bluehost-maestro' ),
				array( 'status' => 400 ),
			);
		}

		// The email provided in the request has to match the response from the platform
		// This ensures we're deliberate about who is being granted access and not blindly adding a key
		// without knowing or confirming the associated email address.
		if ( $email !== $maestro_info['email'] ) {
			return new WP_Error(
				'maestro_rest_invalid_email',
				__( 'Email does not match provided key.', 'bluehost-maestro' ),
				array( 'status' => 400 ),
			);
		}

		return $maestro_info;
	}

	/**
	 * Generates and sends an access token to the Maestro platform
	 *
	 * @since 1.0
	 *
	 * @param WP_User         $user    The WP User the JWT is associated with
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function send_access_token( $user, $request ) {

		// Generate the access token
		$jwt          = new Token();
		$access_token = $jwt->generate_token( $request['maestro_key'], $user->ID );

		// Send the token to the Maestro Platform
		$body = array(
			'otp'        => $request['maestro_key'],
			'wp-secret'  => $access_token,
			'websiteUrl' => get_option( 'siteurl' ),
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'timeout'     => 10,
			'data_format' => 'body',
		);

		// $response = wp_remote_post( 'https://webpro.test/wp-json/bluehost/token', $args );

		// Save the token returned from Maestro
		// This token only allows the site to notify the platform when a Maestro user's access has been revoked
		$maestro_token = 'maestro_token';
		// @todo Remove placeholder token and use platform response
		// $maestro_token = json_decode( $response['body'] )->token;
		if ( $maestro_token ) {
			$encryption      = new Encryption();
			$encrypted_token = $encryption->encrypt( $maestro_token );
			// @todo Check for existing revoke token before trying to add or it will fail
			add_user_meta( $user->ID, 'bh_maestro_token', $encrypted_token, true );
		}
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
					'required'    => true,
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
