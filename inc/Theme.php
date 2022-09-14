<?php

namespace Bluehost\Maestro;

use Exception;
use WP_User_Query;

/**
 * Class for managing Themes
 */
class Theme {

	/**
	 * The id for the theme
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The theme name, usually same as the id
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The theme's title (or Name in pure WP sense)
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Theme's status, will either be active or inactive
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Theme's version
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Whether there is an update available for the theme or not
	 * will be either none or available
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $update;

	/**
	 * Theme's update version, will be either (undef) or the version
	 *
	 * @since 1.1.1
	 *
	 * @var string
	 */
	public $update_version;

	/**
	 * Theme's screenshot, an associative array of file and url
	 *
	 * @since 1.1.1
	 *
	 * @var array
	 */
	public $screenshot;

	/**
	 * If Auto updates have been enabled for this theme
	 * 
	 * @since 1.1.1
	 * 
	 * @var bool
	 */
	public $auto_updates_enabled;

	/**
	 * Constructor
	 *
	 * @since 1.1.1
	 *
	 * @param string   $theme_id      The theme id
	 * @param WP_Theme $theme         object to initialize our theme
	 * @param array    $auto_updates  array of auto update options
	 * @param array    $theme_updates array with info about theme updates
	 * @param WP_Theme $current_theme current theme's object
	 */
	public function __construct( $theme_id, $theme, $auto_updates, $theme_updates, $current_theme ) {

		// Include theme functions
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$stylesheet     = $theme->get_stylesheet();
		$update         = 'none';
		$update_version = '(undef)';
		if ( array_key_exists( $stylesheet, $theme_updates->response ) ) {
			$update         = 'available';
			$update_version = $themes_updates->response[ $stylesheet ]['new_version'];
		}

		$screenshot_url       = $theme->get_screenshot() ? $theme->get_screenshot() : 'none';
		$screenshot_url_array = 'none' !== $screenshot_url ? explode( '/', $screenshot_url ) : array( 'none' );
		$filename             = end( $screenshot_url_array );

		$screenshot = array(
			'url'  => $screenshot_url,
			'file' => $filename,
		);

		// Assign all the required values
		$this->id                   = $theme_id;
		$this->name                 = $theme_id;
		$this->title                = $theme->display( 'Name' );
		$this->status               = $theme->display( 'Name' ) === $current_theme->display( 'Name' ) ? 'active' : 'inactive';
		$this->version              = $theme->get( 'Version' );
		$this->update               = $update;
		$this->update_version       = $update_version;
		$this->screenshot           = $screenshot;
		$this->auto_updates_enabled = in_array( $theme_id, $auto_updates, true );
	}
}
