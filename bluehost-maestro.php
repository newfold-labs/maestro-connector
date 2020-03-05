<?php
/**
 * Plugin Name: Bluehost Maestro
 * Description: Give trusted web professionals admin access to your WordPress account. Revoke anytime.
 * Version: 1.0
 * Requires at least: 4.7
 * Requires PHP: 5.4
 * Author: Bluehost
 * Author URI: https://www.bluehost.com/
 * Text Domain: bluehost-maestro
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Bluehost
 */

namespace Bluehost\Maestro;

define( 'BH_MAESTRO_VERSION', '1.0' );
define( 'BH_MAESTRO_FILE', __FILE__ );
define( 'BH_MAESTRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BH_MAESTRO_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Other required files
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/sso.php';

// Set up the activation redirect
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
add_action( 'admin_init', __NAMESPACE__ . '\\activation_redirect' );

// Initialization hooks
add_action( 'init', __NAMESPACE__ . '\\admin_init' );
add_action( 'rest_api_init', __NAMESPACE__ . '\\rest_init' );

/**
 * Plugin activation callback. Registers option to redirect on next admin load.
 *
 * @since 1.0
 */
function activate() {
	add_option( 'bh_maestro_activation_redirect', true );
}

/**
 * Redirects the user on plugin activation
 *
 * @since 1.0
 */
function activation_redirect() {
	// Make sure we're supposed to redirect
	if ( get_option( 'bh_maestro_activation_redirect', false ) ) {
		// Make sure we don't do it again
		delete_option( 'bh_maestro_activation_redirect' );
		wp_safe_redirect( admin_url( '/users.php?page=bluehost-maestro' ) );
		exit;
	}
}

/**
 * Initialize all admin functionality
 *
 * @since 1.0
 */
function admin_init() {
	if ( ! is_admin() ) {
		return;
	}
	$admin = new Admin();
}

/**
 * Initialize REST API functionality
 *
 * @since 1.0
 */
function rest_init() {
	$rest_api = new REST_API();
}
