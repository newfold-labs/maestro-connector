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
	 */
	public function __construct() {
		// Trigger Update check
		wp_update_plugins();
		wp_update_themes();

		$core_update              = get_site_transient( 'update_core' );
		$last_updated             = $core_update->last_checked;
		$wordpress_version        = $core_update->version_checked;
		$wordpress_latest_version = $core_update->updates[0]->current;
		$themes_update_count      = count( get_site_transient( 'update_themes' )->response );
		$plugins_update_count     = count( get_site_transient( 'update_plugins' )->response );
		$core_update_count        = count( $core_update->updates ) - 1;

		$updates_available              = array(
			'core'    => $core_update_count,
			'themes'  => $themes_update_count,
			'plugins' => $plugins_update_count,
			'total'   => $core_update_count + $themes_update_count + $plugins_update_count,
		);
		$this->last_updated             = $last_updated;
		$this->wordpress_version        = $wordpress_version;
		$this->wordpress_version_latest = $wordpress_latest_version;
		$this->updates_available        = $updates_available;

	}
}
