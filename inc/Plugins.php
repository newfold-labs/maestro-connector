<?php

namespace Bluehost\Maestro;

/**
 * Class for managing Plugins
 */
class Plugins {

	/**
	 * Global Auto updates Toggle for plugin
	 *
	 * @since 1.1.1
	 *
	 * @var bool
	 */
	public $auto_update_global;

	/**
	 * List of plugins installed
	 *
	 * @since 1.1.1
	 *
	 * @var array
	 */
	public $plugins;

	/**
	 * Timestamp of last updates check
	 *
	 * @since 1.1.1
	 *
	 * @var timestamp
	 */
	public $last_checked;


	/**
	 * Constructor
	 *
	 * @since 1.1.1
	 *
	 */
	public function __construct() {
        // Import necessary files
        if (!function_exists('get_plugin_data')) {
            include_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        if (!function_exists('get_option')) {
            include_once ABSPATH.'wp-includes/option.php';
        }

		// Make sure we populate the plugins updates transient
		wp_update_plugins();

        // Get all installed plugins
        $installed_plugins = get_plugins();
        $updates = get_site_transient('update_plugins');
        $auto_updates = get_option('auto_update_plugins');
        
        $this->plugins = array();
        foreach ($installed_plugins as $plugin_slug => $plugin_details) {
            $update_info = NULL;
            $update_response = $updates->response[ $plugin_slug ];
            if ( isset( $update_response ) ) {
                $update_info = array(
                    'update_version' => $update_response->new_version,
                    'requires_wp_version' => $update_response->requires,
                    'requires_php' => $update_response->requires_php,
                    'tested_wp_version' => $update_response->tested,
                    'last_updated' => $update_response->last_updated,
                ); 
            }

            $plugin = new Plugin(
                getSlugFromBasename($plugin_slug),
                $plugin_details['Name'],
                $plugin_details['Version'],
                $plugin_details['Author'],
                $plugin_details['AuthorURI'],
                $plugin_details['Description'],
                $plugin_details['Title'],
                is_plugin_active($plugin_slug),
                is_uninstallable_plugin($plugin_slug),
                in_array($plugin_slug, $auto_updates),
                $update_info
            );
            
            array_push($this->plugins, $plugin);
        }

        $this->auto_update_global = get_option('auto_update_plugin');
        $this->last_checked = $updates->last_checked;
	}
}

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
	 */
	public function __construct($slug, $name, $version, $author, $author_uri, $description, $title, $active, $uninstallable, $auto_updates_enabled, $update) {
        $this->slug = $slug;
        $this->name = $name;
        $this->version = $version;
        $this->author = $author;
        $this->author_uri = $author_uri;
        $this->description = $description;
        $this->title = $title;
        $this->active = $active;
        $this->uninstallable = $uninstallable;
        $this->auto_updates_enabled = $auto_updates_enabled;
        $this->update = $update;
    }

}