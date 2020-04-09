<?php
/**
 * Plugin Name: Maestro Connector
 * Description: Give trusted web professionals admin access to your WordPress account. Revoke anytime.
 * Version: 1.0
 * Requires at least: 4.7
 * Requires PHP: 5.4
 * Author: Bluehost
 * Author URI: https://www.bluehost.com/
 * Text Domain: maestro-connector
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
if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'after_plugin_row_' . plugin_basename( __FILE__ ), __NAMESPACE__ . '\\vendor_notice' );
	return;
}
require __DIR__ . '/vendor/autoload.php';

// Other required files
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
	if ( is_user_logged_in() && intval( get_option( 'bh_maestro_activation_redirect', false ) ) === wp_get_current_user()->ID ) {
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

function vendor_notice() {
	?>
	<style type="text/css">
		.plugin-update-tr.active[data-slug="bluehost-maestro"] td,
		.plugins .active[data-slug="bluehost-maestro"] th.check-column {
			border-left-color: #dc3232;
		}
	</style>
	<tr class="active maestro-warning">
		<td colspan="3">
			<strong style="color:#dc3232;"><span class="dashicons dashicons-warning"></span> <?php _e( 'Maestro is missing critical files', 'maestro-connector' ); ?></strong>
			// <span><a href="https://www.bluehost.com/contact"><?php _e( 'Contact Bluehost Support', 'maestro-connector' ); ?> <span class="dashicons dashicons-external"></span> </a></span>
		</td>
	</tr>
	<?php
}
