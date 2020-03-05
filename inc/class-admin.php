<?php

namespace Bluehost\Maestro;

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
	private $partials = BH_MAESTRO_PATH . 'inc/partials/';

	/**
	 * Constructor
	 *
	 * Registers all our required hooks for the wp-admin
	 *
	 * @since 1.0
	 */
	public function __construct() {

		// Set up required JS/CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Register the primary admin page
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Ajax hooks for adding maestros
		add_action( 'wp_ajax_bh-maestro-key-check', array( $this, 'check_key' ) );
		add_action( 'wp_ajax_bh-maestro-confirm', array( $this, 'add_maestro' ) );

		// Hooks for the user list table
		add_filter( 'manage_users_columns', array( $this, 'add_user_column' ) );
		add_action( 'manage_users_custom_column', array( $this, 'user_column_details' ), 10, 3 );

		// Hooks for the user profile section
		add_action( 'edit_user_profile', array( $this, 'user_profile_section' ) );
		add_action( 'show_user_profile', array( $this, 'user_profile_section' ) );
	}

	/**
	 * Register and enqueue the required admin scripts
	 *
	 * @since 1.0
	 *
	 * @param string $hook The hook for the current page being loaded
	 */
	public function add_scripts( $hook ) {

		wp_register_script( 'bluehost-add-maestro', BH_MAESTRO_URL . 'assets/js/add-maestro.js', array( 'jquery' ) );
		$data = array(
			'siteURL'   => get_option( 'siteurl' ),
			'assetsDir' => BH_MAESTRO_URL . '/assets',
			'ajaxURL'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'bluehost-add-maestro' ),
		);
		wp_localize_script( 'bluehost-add-maestro', 'maestro', $data );

		// Only add specific assets to the add-maestro page
		if ( 'users_page_bluehost-maestro' === $hook ) {
			wp_enqueue_style( 'google-open-sans', 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,600&display=swap', array() );
			wp_enqueue_style( 'bluehost-maestro', BH_MAESTRO_URL . 'assets/css/bh-maestro.css', array( 'google-open-sans' ) );
			wp_enqueue_script( 'bluehost-add-maestro' );
		}

	}

	/**
	 * Register the top-level admin menu page
	 *
	 * @since 1.0
	 */
	public function admin_menu() {

		$title = __( 'Bluehost Maestro', 'bluehost-maestro' );
		add_submenu_page(
			'users.php',
			$title,
			$title,
			'manage_options',
			'bluehost-maestro',
			array( $this, 'admin_page' ),
			4,
		);
	}

	/**
	 * Wrapped call to wp_verify_nonce to simplify nonce checking
	 *
	 * @since 1.0
	 *
	 * @param string $nonce  The nonce to check
	 * @param string $action The action used when creating the nonce
	 */
	public function verify_nonce( $nonce, $action ) {
		return ( 1 === wp_verify_nonce( $nonce, $action ) );
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
				<img class="logo" src="<?php echo BH_MAESTRO_URL . 'assets/images/bh-maestro-logo.svg'; ?>" />
				<div class="maestro-content">
					<?php
					$compatible = $this->check_requirements();
					if ( true !== $compatible ) {
						include $this->partials . 'requirements.php';
					} elseif ( isset( $_GET['action'] ) && 'revoke' === $_GET['action'] ) {
						include $this->partials . 'confirm-revoke.php';
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
	 * Handles the logic and actions of the admin page
	 *
	 * @since 1.0
	 */
	public function parse_action() {
		// Without a supplied action, we'll show the default Add Maestro form
		if ( ! $_GET['action'] ) {
			include $this->partials . 'add.php';
			return;
		}

		// Sanitize the paramaters
		$nonce   = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING );
		$action  = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING );
		$user_id = filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT );

		// Check the nonce. We'll do it here becuase we use the same generic error message for all action types.
		switch ( $action ) {
			case 'confirm-add':
			case 'add':
				$valid_nonce = $this->verify_nonce( $nonce, 'bluehost-add-maestro' );
				break;
			case 'confirm-revoke':
			case 'revoke':
				$valid_nonce = $this->verify_nonce( $nonce, 'bluehost-revoke-maestro' );
				break;
		}

		// For troubleshooting
		$valid_nonce = true;

		// Invalid or missing nonce. Show the error message and return.
		if ( ! $valid_nonce ) {
			?>
			<p class="thin"><?php _e( 'Maestro was unable to comlete your request.', 'bluehost-maestro' ); ?></p>
			<?php
			return;
		}

		// Valid nonce and an action, let's run the logic
		switch ( $action ) {
			case 'confirm-add':
				include $this->partials . 'confirm-add.php';
				break;
			case 'add':
				include $this->partials . 'added.php';
				break;
			case 'confirm-revoke':
				include $this->partials . 'confirm-revoke.php';
				break;
			case 'revoke':
				include $this->partials . 'revoked.php';
				break;
		}

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
			return new WP_Error( 'https-not-detected', __( 'Maestro requires HTTPS.', 'bluehost-maestro' ) );
		}

		// Requires WordPress 4.7 or higher
		global $wp_version;
		if ( version_compare( $wp_version, '4.7', '<' ) ) {
			return new WP_Error( 'wp-version-incompatibile', __( 'Maestro requires WordPress version 4.7 or higher.', 'bluehost-maestro' ) );
		}

		// Requires PHP version 5.3 or higher
		if ( version_compare( phpversion(), '5.3', '<' ) ) {
			return new WP_Error( 'php-version-incompatible', __( 'Maestro requires PHP version 5.3 or higher.', 'bluehost-maestro' ) );
		}

		// Make sure the site has secure keys configured
		if ( ! defined( 'SECURE_AUTH_KEY' ) || '' === SECURE_AUTH_KEY ) {
			return new WP_Error( 'secure-auth-key-not-set', __( 'You must have a SECURE_AUTH_KEY configured in wp-config.php', 'bluehost-maestro' ) );
		}

		return true;
	}


	/**
	 * Ajax callback for getting Maestro info from a key
	 *
	 * @since 1.0
	 */
	public function check_key() {

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		$key   = filter_input( INPUT_POST, 'key', FILTER_SANITIZE_STRING );

		// Make sure we have a valid nonce and a key
		if ( 1 !== wp_verify_nonce( $nonce, 'bluehost-add-maestro' ) || ! $key ) {
			return;
		}

		// Reach out to the Maestro platform to determine Maestro to grant access to
		$info = get_maestro_info( $key );

		$info['status'] = 'success';

		echo wp_json_encode( $info );
		wp_die();
	}

	/**
	 * Ajax callback for adding a maestro based off of key and email
	 *
	 * @since 1.0
	 */
	public function add_maestro() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		$key   = filter_input( INPUT_POST, 'key', FILTER_SANITIZE_STRING );
		$email = filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL );
		$name  = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_STRING );

		// Make sure we have a valid nonce and a key
		if ( 1 !== wp_verify_nonce( $nonce, 'bluehost-add-maestro' ) || ! $key ) {
			return;
		}

		// Check for an existing user
		$user = get_user_by( 'email', $email );

		// If user doesn't exist, we need to create one
		if ( ! $user ) {
			$userdata = array(
				'user_pass'    => wp_generate_password( 20, true ),
				// Create a username using the username for the email address
				'user_login'   => sanitize_user( substr( $email, 0, strrpos( $email, '@' ) ) ),
				'user_email'   => $email,
				'display_name' => $name, // @fix display name isn't coming in correctly
				'role'         => 'administrator',
			);
			$id       = wp_insert_user( $userdata );
			$user     = get_userdata( $id );
		}

		// Make sure they are an administrator
		$user->set_role( 'administrator' );

		// Store the supplied Maestro key
		add_maestro_key( $user->ID, $key );
		// Save information about who approved the connection and when
		add_user_meta( $user->ID, 'bh_maestro_added_by', wp_get_current_user()->user_login, true );
		add_user_meta( $user->ID, 'bh_maestro_added_date', time(), true );

		// Generate our access token
		$jwt          = new Token();
		$access_token = $jwt->generate_token( $key, $user->ID );

		// Send the token to the Maestro Platform
		$body = array(
			'otp'        => $key,
			'wp-secret'  => $access_token,
			'websiteUrl' => get_option( 'siteurl' ),
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'timeout'     => 10,
			'data_format' => 'body',
		);

		$response = wp_remote_post( 'https://webpro.test/wp-json/bluehost/token', $args );

		// Save the token returned from Maestro
		// This token only allows the site to notify the platform when a Maestro user's access has been revoked
		$maestro_token = 'maestro_token';
		// $maestro_token = json_decode( $response['body'] )->token;
		if ( $maestro_token ) {
			$encryption      = new Encryption();
			$encrypted_token = $encryption->encrypt( $maestro_token );
			add_user_meta( $user->ID, 'bh_maestro_token', $encrypted_token, true );
		}

		$response = array(
			'status' => 'success',
		);

		echo wp_json_encode( $response );
		wp_die();

	}

	/**
	 * Adds a column for indicating Maestro status to the Users list table
	 *
	 * @since 1.0
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
		if ( 'maestro' === $column_name && is_user_maestro( $user_id ) ) {
			$logo_url   = BH_MAESTRO_URL . '/assets/images/bh-maestro-logo.svg';
			$revoke_url = admin_url();

			$value  = '<img style="max-width: 80%;" src="' . esc_url( $logo_url ) . '" />';
			$value .= '<div class="row-actions"><a href="' . esc_url( $revoke_url ) . '">Revoke Access</a></div>';
		}
		return $value;
	}

	/**
	 * Outputs a section for the Edit User page for managing Maestro status
	 *
	 * @since 1.0
	 *
	 * @param WP_User $user The WP_User object for the user who's profile that is being viewed
	 */
	public function user_profile_section( $user ) {
		if ( is_user_maestro( $user->ID ) ) {
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
		return array();
	}
}
