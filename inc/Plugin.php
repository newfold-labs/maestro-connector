<?php

namespace Bluehost\Maestro;

/**
 * Class for managing plugins
 */
class Plugin {
	/**
	 * Plugin's slug
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Plugin's name
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $name;


	/**
	 * Plugin's version
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Plugin's author
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $author;

	/**
	 * Plugin's Author's URI
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $author_uri;

	/**
	 * Plugin's description
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Plugin's title
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Plugin's status, will either be active or inactive
	 *
	 * @since 1.1.1
	 *
	 * @var bool
	 */
	public $active;

	/**
	 * If the Plugin is uninstallable
	 *
	 * @since 1.1.1
	 *
	 * @var bool
	 */
	public $uninstallable;

	/**
	 * Plugin's auto-update toggle
	 *
	 * @since 1.1.1
	 *
	 * @var bool
	 */
	public $auto_updates_enabled;

	/**
	 * Plugin Updates, if any
	 *
	 * @since 1.1.1
	 *
	 * @var array
	 */
	public $update;

	/**
	 * Constructor
	 *
	 * @since 1.1.1
	 *
	 * @param string $slug           The plugin's slug
	 * @param array  $plugin_updates The plugin updates site transient
	 * @param array  $plugin_details The details for the plugin
	 * @param array  $auto_updates   The auto updates option
	 */
	public function __construct( $slug, $plugin_updates, $plugin_details, $auto_updates ) {
		$update_info = array();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! empty( $plugin_updates->response[ $slug ] ) ) {
			$update_info = array(
				'update_version'      => $plugin_updates->response[ $slug ]->new_version,
				'requires_wp_version' => $plugin_updates->response[ $slug ]->requires,
				'requires_php'        => $plugin_updates->response[ $slug ]->requires_php,
				'tested_wp_version'   => $plugin_updates->response[ $slug ]->tested,
				'last_updated'        => $plugin_updates->response[ $slug ]->last_updated,
			);
		}

		$slug_split_array = explode( '/', $slug );
		$plugin_slug      = reset( $slug_split_array );

		$this->slug                 = $plugin_slug;
		$this->name                 = $plugin_details['Name'];
		$this->version              = $plugin_details['Version'];
		$this->author               = $plugin_details['Author'];
		$this->author_uri           = $plugin_details['AuthorURI'];
		$this->description          = $plugin_details['Description'];
		$this->title                = $plugin_details['Title'];
		$this->active               = is_plugin_active( $slug );
		$this->uninstallable        = is_uninstallable_plugin( $slug );
		$this->auto_updates_enabled = in_array( $slug, $auto_updates, true );
		$this->update               = $update_info;
	}
}
