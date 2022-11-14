<?php

namespace Bluehost\Maestro;

use Plugin_Upgrader_Skin;

/**
 * Extended Plugin upgrader skin to have no feedback and a clean api response
 */
class PluginUpgraderSkin extends Plugin_Upgrader_Skin {
	/**
	 * An empty feedback function to have clean api response
	 *
	 * @since 1.1.2
	 *
	 * @param string $feedback Message data.
	 * @param mixed  ...$args  Optional text replacements.
	 */
	public function feedback( $feedback, ...$args ) { }

	/**
	 * Override to remove the javascript echo
	 *
	 * @since 1.1.2
	 *
	 * @param string $type Type of update count to decrement. Likely values include 'plugin',
	 *                     'theme', 'translation', etc.
	 */
	protected function decrement_update_count( $type ) { }

	/**
	 * Override header to remove extra prints from api response
	 *
	 * @since 1.1.2
	 */
	public function header() {
		if ( $this->done_header ) {
			return;
		}
		$this->done_header = true;
	}

	/**
	 * Override footer to remove extra prints from api response
	 *
	 * @since 1.1.2
	 */
	public function footer() {
		if ( $this->done_footer ) {
			return;
		}
		$this->done_footer = true;
	}
}
