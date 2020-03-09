<?php
/**
 * Plugin Name: Bluehost Maestro
 * Description: Give trusted web professionals admin access to your WordPress account. Revoke anytime.
 * Version: 1.0
 * Requires at least: 4.7
 * Requires PHP: 5.4
 * Author: Bluehost
 * Author URI: https://www.bluehost.com/
 * Text Domain: maestro
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Bluehost
 */

namespace Bluehost\Maestro;

define( 'MAESTRO_VERSION', '1.0' );
define( 'MAESTRO_FILE', __FILE__ );
define( 'MAESTRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAESTRO_URL', plugin_dir_url( __FILE__ ) );

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
 * Saves user ID to ensure it only redirects for the user who activated the plugin
 *
 * @since 1.0
 */
function activate() {
	// Don't do redirects when multiple plugins are bulk activated
	if (
		( isset( $_REQUEST['action'] ) && 'activate-selected' === $_REQUEST['action'] ) &&
		( isset( $_POST['checked'] ) && count( $_POST['checked'] ) > 1 ) ) {
		return;
	}
	add_option( 'bh_maestro_activation_redirect', wp_get_current_user()->ID );
}

/**
 * Redirects the user after plugin activation
 *
 * @since 1.0
 */
function activation_redirect() {
	// Make sure it's the correct user
	if ( intval( get_option( 'bh_maestro_activation_redirect', false ) ) === wp_get_current_user()->ID ) {
		// Make sure we don't redirect again after this one
		delete_option( 'bh_maestro_activation_redirect' );
		wp_safe_redirect( admin_url( 'users.php?page=bluehost-maestro' ) );
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
