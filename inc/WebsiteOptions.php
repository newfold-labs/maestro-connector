<?php

namespace Bluehost\Maestro;

/**
 * Class for managing Plugins
 */
class WebsiteOptions {

	/**
	 * Last updated timestamp
	 *
	 * @since 1.1.1
	 *
	 * @var date
	 */
	public $last_updated;

	/**
	 * Version of WordPress installed
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $wordpress_version;

	/**
	 * Latest available version of WordPress
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $wordpress_version_latest;

	/**
	 * Latest available version of WordPress
	 *
	 * @since 1.1.1
	 *
	 * @var UpdatesAvailable
	 */
	public $updates_available;

	/**
	 * Constructor
	 *
	 * @since 1.1.1
	 *
	 */
	public function __construct() {
        // Trigger Update check
		wp_update_plugins();
		wp_update_themes();

		// Import necessary files
        if (!function_exists('get_option')) {
            include_once ABSPATH.'wp-includes/option.php';
        }


        $core_update = get_site_transient('update_core');
        $last_updated = $core_update->last_checked;
        $wordpress_version = $core_update->version_checked;
        $wordpress_latest_version = $core_update->updates[0]->current;

        $themes_update_count = sizeof(get_site_transient('update_themes')->response);
        $plugins_update_count = sizeof(get_site_transient('update_plugins')->response);
        $core_update_count = sizeof($core_update->updates) - 1;
        
        $updates_available = new UpdatesAvailable($core_update_count, $themes_update_count, $plugins_update_count);
		
		$this->last_updated = $last_updated;
		$this->wordpress_version = $wordpress_version;
		$this->wordpress_version_latest = $wordpress_latest_version;
		$this->updates_available = $updates_available;

	}
}

class UpdatesAvailable {
    /**
	 * Number of updates available for the Core
	 *
	 * @since 1.1.1
	 *
	 * @var int
	 */
	public $core;

	/**
	 * Number of updatable themes
	 *
	 * @since 1.1.1
	 *
	 * @var int
	 */
	public $themes;
    

	/**
	 * Number of updatable plugins
	 *
	 * @since 1.1.1
	 *
	 * @var int
	 */
	public $plugins;

	/**
	 * Total Updates Available on the website
	 *
	 * @since 1.1.1
	 *
	 * @var int
	 */
	public $total;

	/**
	 * Constructor
	 *
	 * @since 1.1.1
	 *
	 */
	public function __construct($core, $themes, $plugins) {
		$this->core = $core;
		$this->themes = $themes;
		$this->plugins = $plugins;
		$this->total = $core + $themes + $plugins;
    }

}