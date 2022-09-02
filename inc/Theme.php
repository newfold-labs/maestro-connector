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
     * Constructor
     * 
     * @since 1.1.1
     * 
     * @param theme_id string   The theme id
     * @param theme    WP_Theme object to initialize our theme
     */
    public function __construct( $theme_id, $theme ) {
        // Make sure we populate the themes updates transient
        wp_update_themes();

        // Get the current theme
        $current_theme = wp_get_theme();

        // Include theme functions
        require_once( ABSPATH . 'wp-admin/includes/theme.php' );

        $theme_updates  = get_site_transient( 'update_themes' );
        $stylesheet     = $theme->get_stylesheet();
        $update         = 'none';
        $update_version = '(undef)';
        if ( array_key_exists( $stylesheet, $theme_updates->response ) ) {
            $update         = 'available';
            $update_version = $themes_updates->response[ $stylesheet ]['new_version'];
        }

        $screenshot_url       = $theme->get_screenshot() ? $theme->get_screenshot() : 'none';
        $screenshot_url_array = $screenshot_url != 'none' ? explode( '/', $screenshot_url ) : array( 'none' );
        $filename             = end( $screenshot_url_array );

        $screenshot = array(
            'url'  => $screenshot_url,
            'file' => $filename
        );

        // Assign all the required values
        $this->id             = $theme_id;
        $this->name           = $theme_id;
        $this->title          = $theme->display( 'Name' );
        $this->status         = $theme->display( 'Name' ) == $current_theme->display( 'Name' ) ? 'active' : 'inactive';
        $this->version        = $theme->get('Version');
        $this->update         = $update;
        $this->update_version = $update_version;
        $this->screenshot     = $screenshot;
    }
}
