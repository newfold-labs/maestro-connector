<?php

namespace Bluehost\Maestro;

use Exception;
use WP_Error;

/**
 * Class for handling admin pages and functionality for the plugin
 *
 * @since 1.0
 */
class Admin {

	/**
	 * Directory path for partials
	 *
	 * @since 1.0
	 * @var string;
	 */
	private $partials = MAESTRO_PATH . 'inc/partials/';

	/**
	 * The admin page hook suffix
	 *
	 * @since 1.0
	 * @var string;
	 */
	protected $page_hook = '';

	/**
	 * Constructor
	 *
	 * Registers all our required hooks for the wp-admin
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// Register the primary admin page
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Set up required JS/CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Ajax hooks for adding maestros
		add_action( 'wp_ajax_bh-maestro-key-check', array( $this, 'check_key' ) );

		// Hooks for the user list table
		add_filter( 'manage_users_columns', array( $this, 'add_user_column' ) );
		add_action( 'manage_users_custom_column', array( $this, 'user_column_details' ), 10, 3 );

		// Hooks for the user profile section
		add_action( 'edit_user_profile', array( $this, 'user_profile_section' ) );
		add_action( 'show_user_profile', array( $this, 'user_profile_section' ) );
	}

	/**
	 * Register the admin page
	 *
	 * @since 1.0
	 */
	public function admin_menu() {
		$this->page_hook = add_submenu_page(
			'users.php',
			__( 'Bluehost Maestro' ),
			__( 'Bluehost Maestro' ),
			'manage_options',
			'bluehost-maestro',
			array( $this, 'admin_page' ),
			4,
		);
	}

	/**
	 * Register and enqueue the required admin scripts
	 *
	 * @since 1.0
	 *
	 * @param string $hook The hook for the current page being loaded
	 */
	public function add_scripts( $hook ) {

		wp_register_script( 'maestro', MAESTRO_URL . 'assets/js/maestro.js', array( 'jquery' ), MAESTRO_VERSION );
		$data = array(
			'urls'    => array(
				'site'        => get_option( 'siteurl' ),
				'assets'      => MAESTRO_URL . '/assets',
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'restAPI'     => rest_url( '/bluehost/maestro/v1' ),
				'usersList'   => admin_url( 'users.php' ),
				'maestroPage' => add_query_arg( 'page', 'bluehost-maestro', admin_url( 'users.php' ) ),
			),
			'nonces'  => array(
				'ajax' => wp_create_nonce( 'maestro_check_key' ),
				'rest' => wp_create_nonce( 'wp_rest' ),
			),
			'strings' => $this->get_translated_strings(),
		);
		wp_localize_script( 'maestro', 'maestro', $data );

		// Only add specific assets to the add-maestro page
		if ( $this->page_hook === $hook ) {
			wp_enqueue_style( 'google-open-sans', 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,600&display=swap', array() );
			wp_enqueue_style( 'maestro', MAESTRO_URL . 'assets/css/bh-maestro.css', array( 'google-open-sans' ), MAESTRO_VERSION );
			wp_enqueue_script( 'maestro' );
		}

	}

	/**
	 * Callback to render the admin page
	 *
	 * @since 1.0
	 */
	public function admin_page() {
		?>
		<div class="wrap maestro-container">
			<div class="maestro-page">
				<img class="logo" src="<?php echo MAESTRO_URL . 'assets/images/bh-maestro-logo.svg'; ?>" />
				<div class="maestro-content">
					<?php
					$compatible = $this->check_requirements();
					if ( true !== $compatible ) {
						include $this->partials . 'requirements.php';
					} elseif ( isset( $_GET['action'] ) && 'revoke' === $_GET['action'] ) {
						$this->handle_revoke();
					} else {
						include $this->partials . 'add.php';
					}
					?>
				</div>
				<div class="boat-bg"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle a revoke action on the admin page
	 *
	 * @since 1.0
	 */
	public function handle_revoke() {
		include $this->partials . 'confirm-revoke.php';
	}

	/**
	 * Checks the plugin minimum requirements to ensure it will operate
	 *
	 * @since 1.0
	 *
	 * @return true|WP_Error True if the current environment meets the requirements, WP_Error with details about why it does not
	 */
	public function check_requirements() {

		// Requires HTTPS
		if ( ! is_ssl() ) {
			return new WP_Error( 'https-not-detected', __( 'Maestro requires HTTPS.' ) );
		}

		// Requires WordPress 4.7 or higher
		global $wp_version;
		if ( version_compare( $wp_version, '4.7', '<' ) ) {
			return new WP_Error( 'wp-version-incompatibile', __( 'Maestro requires WordPress version 4.7 or higher.' ) );
		}

		// Requires PHP version 5.3 or higher
		if ( version_compare( phpversion(), '5.3', '<' ) ) {
			return new WP_Error( 'php-version-incompatible', __( 'Maestro requires PHP version 5.3 or higher.' ) );
		}

		// Make sure the site has secure keys configured
		if ( ! defined( 'SECURE_AUTH_KEY' ) || '' === SECURE_AUTH_KEY ) {
			return new WP_Error( 'secure-auth-key-not-set', __( 'You must have a SECURE_AUTH_KEY configured in wp-config.php' ) );
		}

		return true;
	}


	/**
	 * Ajax callback for getting Maestro info from a key
	 *
	 * We do this via ajax to avoid sending the platform request in JS since we
	 * also have to check the DB to ensure the Web Pro isn't already connected.
	 *
	 * @since 1.0
	 */
	public function check_key() {

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		$key   = filter_input( INPUT_POST, 'key', FILTER_SANITIZE_STRING );

		// Make sure we have a valid nonce and a key
		if ( 1 !== wp_verify_nonce( $nonce, 'maestro_check_key' ) || ! $key ) {
			return;
		}

		$response = array();

		try {
			$webpro = new Web_Pro( array( 'key' => $key ) );
		} catch ( Exception $e ) {
			$response['status']  = 'invalid_key';
			$response['message'] = __( 'This Maestro key is not valid.' );
			echo wp_json_encode( $response );
			wp_die();
		}

		if ( $webpro->is_connected() ) {
			$response['status']  = 'user_exists';
			$response['message'] = __( 'You have already added this web pro to your site.' );
		} else {
			$response['status']  = 'success';
			$response['message'] = __( "Let's double-check this: Make sure the name below matches the name of your web pro." );
		}

		$response['name']     = $webpro->first_name . ' ' . $webpro->last_name;
		$response['email']    = $webpro->email;
		$response['location'] = $webpro->location;

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * Adds a column for indicating Maestro status to the Users list table
	 *
	 * @since 1.0
	 *
	 * @param array $columns The currently visible columns
	 *
	 * @return array The columns
	 */
	public function add_user_column( $columns ) {

		$old_columns = $columns;
		// Check the last value.
		// We only want to move ahead of the Posts column if it's visible.
		$last = array_pop( $columns );
		if ( 'Posts' === $last ) {
			$columns['maestro'] = 'Maestro';
			$columns['posts']   = 'Posts';
			return $columns;
		} else {
			$old_columns['maestro'] = 'Maestro';
			return $old_columns;
		}
	}

	/**
	 * Inserts the content into the Maestro status column on the Users list table
	 *
	 * @since 1.0
	 *
	 * @param string $value       The value of the current column being processed
	 * @param string $column_name The name of the current column being processed
	 * @param int    $user_id     The ID of the user being shown in the current row
	 *
	 * @return string The new value to be used in the column
	 */
	public function user_column_details( $value, $column_name, $user_id ) {

		$webpro = new Web_Pro( array( 'user_id' => $user_id ) );

		if ( 'maestro' === $column_name && $webpro->is_connected() ) {
			$logo_url   = MAESTRO_URL . '/assets/images/bh-maestro-logo.svg';
			$revoke_url = $this->get_revoke_url( $user_id );

			$value  = '<img style="max-width: 80%;" src="' . esc_url( $logo_url ) . '" />';
			$value .= '<div class="row-actions"><a href="' . esc_url( $revoke_url ) . '">Revoke Access</a></div>';
		}

		return $value;
	}

	/**
	 * Returns an admin page URL to revoke connection for a user
	 *
	 * @since 1.0
	 *
	 * @param int $user_id The ID of the user to revoke Maestro connection
	 *
	 * @return string The URL to the admin page with required query parameters
	 */
	public function get_revoke_url( $user_id ) {

		$query_args = array(
			'id'       => $user_id,
			'action'   => 'revoke',
			'_wpnonce' => wp_create_nonce( 'revoke-webpro' ),
		);

		return add_query_arg( $query_args, admin_url( 'users.php?page=bluehost-maestro' ) );
	}

	/**
	 * Outputs a section for the Edit User page for managing Maestro status
	 *
	 * @since 1.0
	 *
	 * @param WP_User $user The WP_User object for the user who's profile that is being viewed
	 */
	public function user_profile_section( $user ) {

		$webpro = new Web_Pro( array( $user->ID ) );

		if ( $webpro->is_connected() ) {
			$revoke_url = $this->get_revoke_url( $user->ID );
			require $this->partials . 'user-profile-section.php';
		}
	}

	/**
	 * Returns an array of translated strings to be used in JavaScript
	 *
	 * @since 1.0
	 *
	 * @return array List of translated strings
	 */
	public function get_translated_strings() {
		return array(
			'name'           => __( 'Name' ),
			'email'          => __( 'Email' ),
			'location'       => __( 'Location' ),
			'next'           => __( 'Next' ),
			'viewAllUsers'   => __( 'View all Users' ),
			'addWebPro'      => __( 'Add a Web Pro' ),
			'giveAccess'     => __( 'Give access' ),
			'dontGiveAccess' => __( "Don't give access" ),
			'confirmMessage' => __( "Let's double-check this: Make sure the name below matches the name of your web pro." ),
			'accessGranted'  => __( "You've successfully given your web professional administrative access to your site." ),
			'accessDeclined' => __( 'Got it. That web professional does not have access to your site.' ),
			'genericError'   => __( 'An error occured.' ),
		);
	}
}
