<?php

namespace Bluehost\Maestro;

use Firebase\JWT\JWT;
use WP_Error;

/**
 * Class for creating and validating BH Maestro JSON web tokens for authentication
 */
class Token {

	/**
	 * Secret key to be used for generating/validating tokens.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * String to use as the meta_key for storing a JWT ID in usermeta
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	protected $jti_meta_key = 'bh_maestro_jti';

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// SECURE_AUTH_KEY is should always be defined in wp-config.php or a user wouldn't
		// be able to log into a site normally. Furthermore, if it gets changed, then it
		// would invalidate all tokens, similar to passwords.
		// If it's missing for some reason, then not much we can safely do going forward.
		if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
			return;
		}

		$this->secret_key = SECURE_AUTH_KEY;
	}

	/**
	 * Generate a JWT token for a specific user that can be used for authentication
	 *
	 * @since 1.0
	 *
	 * @param string $maestro_key The key generated from the Bluehost Maestro dashboard to connect a site
	 * @param int    $user_id     The ID of the user the token is issued for
	 * @param int    $expires     Unix timestamp representing time the token expires (optional)
	 * @param bool   $jti         Generate a unique identifier which makes this a single-use token (optional)
	 * @param array  $data        Array of additional data to encode into the token (optional)
	 *
	 * @return string|WP_Error
	 */
	public function generate_token( $maestro_key, $user_id, $expires = YEAR_IN_SECONDS, $jti = false, $data = array() ) {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'User not found.' )
			);
		}

		// Generate JWT token.
		$payload = $this->generate_payload( $maestro_key, $user, $expires, $jti, $data );
		$token   = JWT::encode( $payload, $this->secret_key );

		// Save the maestro key as usermeta
		// Note this will overwrite any existing Maestro key, rendering any previously issued tokens invalid if the key is different
		update_maestro_key( $user_id, $maestro_key );

		// If this is a single-use token, we'll store a user meta value that gets deleted on validation
		if ( $jti ) {
			// @optimize Explore using a stronger random string generation for the JTI claim
			// This is ok for now because we have several layers of security on top of this,
			// but we could possibly use a better random string generation function here
			$jwt_id = wp_generate_password( 32, false );

			// Try to add it, while forcing it to be unique
			$response = add_user_meta( $user->ID, $this->jti_meta_key, $jwt_id, true );

			// If it exists, lets update the existing one to overwrite it. We should never have 2 active at one time
			if ( ! $response ) {
				$response = update_user_meta( $user->ID, $this->jti_meta_key, $jwt_id );
			}
		}

		// Returns the generated token
		return $token;

	}

	/**
	 * Compile information for the the JWT token.
	 *
	 * @param string          $key        The key generated from the Bluehost Maestro dashboard to connect a site
	 * @param WP_User|Object  $user       The WP_User object for the user the token is assigned to.
	 * @param int             $expires    The number of seconds until the token expires.
	 * @param string          $jti        Optional unique identifier string. Forces token to be single-use.
	 * @param array           $extra_data Optional array of additional data to encode into the data portion of the token
	 *
	 * @return array|WP_Error
	 */
	public function generate_payload( $key, $user, $expires, $jti = '', $extra_data = array() ) {

		$time = time();

		// JWT Reserved claims.
		$reserved = array(
			'iss' => get_bloginfo( 'url' ), // Token issuer.
			'iat' => $time, // Token issued at.
			'nbf' => $time, // Token accepted not before.
			'exp' => $time + $expires, // Token expiry.
		);

		// Only add the jti if one has been provided
		if ( ! empty( $jti ) ) {
			$reserved['jti'] = $jti;
		}

		$data = array(
			'maestro_key' => $key,
			'user'        => array(
				'id'         => $user->ID,
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
			),
		);

		$private = array(
			'data' => array_merge( $data, $extra_data ),
		);

		return array_merge( $reserved, $private );
	}


	/**
	 * Decode the JSON Web Token.
	 *
	 * @param string $token The encoded JWT.
	 *
	 * @return object|WP_Error Return the decoded JWT, or WP_Error on failure.
	 */
	public function decode_token( $token ) {
		try {
			return JWT::decode( $token, $this->secret_key, array( 'HS256' ) );
		} catch ( \Exception $e ) {
			// Return caught exception as a WP_Error.
			return new WP_Error(
				'token_error',
				__( 'Invalid token.' )
			);
		}
	}

	/**
	 * Determine if a provided token is valid.
	 *
	 * @param string  $token     The JSON Web Token to validate
	 * @param boolean $force_jti Whether to force the validation of a JWT ID
	 *
	 * @return object|WP_Error Return the JSON Web Token object, or WP_Error on failure.
	 */
	public function validate_token( $token, $force_jti = false ) {

		// Decode the token.
		$jwt = $this->decode_token( $token );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		// Determine if the Maestro Key is valid
		$maestro_key = $this->validate_key( $jwt );
		if ( is_wp_error( $maestro_key ) ) {
			return $maestro_key;
		}

		// Determine if the token issuer is valid.
		$issuer_valid = $this->validate_issuer( $jwt );
		if ( is_wp_error( $issuer_valid ) ) {
			return $issuer_valid;
		}

		// Determine if the token user is valid.
		$user_valid = $this->validate_user( $jwt );
		if ( is_wp_error( $user_valid ) ) {
			return $user_valid;
		}

		// Determine if the token has expired.
		$expiration_valid = $this->validate_expiration( $jwt );
		if ( is_wp_error( $expiration_valid ) ) {
			return $expiration_valid;
		}

		// Only do a JWT ID check if there is one in the token, or if it's specified as required.
		if ( isset( $jwt->jti ) || $force_jti ) {
			// Determine if this is a valid single-use token
			$jti_valid = $this->validate_jti( $jwt );
			if ( is_wp_error( $jti_valid ) ) {
				return $jti_valid;
			}
		}

		// If we make it here, then it's valid. Return the decoded token
		return $jwt;
	}

	/**
	 * Verify a suppliled Bluehost Maestro key matches one previously stored on the site
	 */
	public function validate_key( $token ) {

		// Match the key to the supplied user ID
		$key = get_user_meta( $token->data->user->id, 'bh_maestro_key', true );
		if ( $key !== $token->data->maestro_key ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine if the token issuer is valid.
	 *
	 * @since 1.0
	 *
	 * @param string $issuer The decoded token
	 *
	 * @return bool|WP_Error
	 */
	public function validate_issuer( $token ) {

		if ( get_bloginfo( 'url' ) !== $token->iss ) {
			return new WP_Error(
				'invalid_token_issuer',
				__( 'Token issuer is invalid.' )
			);
		}

		return true;
	}

	/**
	 * Determine if the user data included in the token is valid.
	 *
	 * @since 1.0
	 *
	 * @param object $token The decoded token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_user( $token ) {

		if ( ! isset( $token->data->user->id ) ) {
			return new WP_Error(
				'missing_token_user_id',
				__( 'Token user must have an ID.' )
			);
		}

		$userdata = get_userdata( $token->data->user->id );

		if ( false === $userdata ) {
			return new WP_Error(
				'invalid_token_wp_user',
				__( 'Token user is invalid.' )
			);
		}

		if ( $token->data->user->user_login !== $userdata->user_login ) {
			return new WP_Error(
				'invalid_token_user_login',
				__( 'Token user_login is invalid.' )
			);
		}

		if ( $token->data->user->user_email !== $userdata->user_email ) {
			return new WP_Error(
				'invalid_token_user_email',
				__( 'Token user_email is invalid.' )
			);
		}

		return true;
	}

	/**
	 * Determine if the token has expired.
	 *
	 * @since 1.0
	 *
	 * @param object $token The decoded token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_expiration( $token ) {

		if ( ! isset( $token->exp ) ) {
			return new WP_Error(
				'missing_token_expiration',
				__( 'Token must have an expiration.' )
			);
		}

		if ( time() > $token->exp ) {
			return new WP_Error(
				'token_expired',
				__( 'Token has expired.' )
			);
		}

		return true;
	}

	/**
	 * Checks for and validates a single-use token identifier.
	 *
	 * Removes the value from the database if valid, ensuring a single use.
	 *
	 * @since 1.0
	 *
	 * @param object  $token The decoded token.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_jti( $token ) {

		// If there is not one included in the token, then it is obviously invalid!
		if ( ! isset( $token->jti ) ) {
			return new WP_Error(
				'missing_token_jti',
				__( 'Token must have a unique identifier.' )
			);
		}

		// Compare to the stored usermeta value
		if ( get_user_meta( $token->data->user->id, $this->jti_meta_key, true ) !== $token->jti ) {
			return new WP_Error(
				'jti_not_valid',
				__( 'Token identifier is not valid.' )
			);
		}

		// If we get here, it's valid. Remove the JTI from the DB so it can't be used again.
		delete_user_meta( $token->data->user->id, $this->jti_meta_key );

		return true;
	}

}
