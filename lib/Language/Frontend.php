<?php

namespace TwentySixB\WP\Plugin\Unbabble\Language;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks related to the language of frontend.
 *
 * @since 0.0.1
 */
class Frontend {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_filter( 'locale', [ $this, 'switch_locale'] );
		\add_action( 'admin_bar_menu', [ $this, 'set_admin_bar_language'], PHP_INT_MIN );
	}

	/**
	 * Switch the locale for the frontend.
	 *
	 * Necessary for loading the correct translations of the themes and plugins.
	 *
	 * @since 0.0.1
	 *
	 * @param string $locale
	 * @return string
	 */
	public function switch_locale( string $locale ) : string {
		if ( is_admin() ) {
			return $locale;
		}
		if ( apply_filters( 'ubb_stop_switch_locale', false, $locale ) ) {
			return $locale;
		}

		$current = LangInterface::get_current_language();
		if ( $current === 'en_US' || in_array( $current, get_available_languages(), true ) ) {
			return $current;
		}
		return $locale;
	}

	/**
	 * Sets the admin bar language to the user locale.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function set_admin_bar_language() : void {
		switch_to_locale( get_user_locale() );
		\add_filter( 'wp_after_admin_bar_render', function () {
			restore_previous_locale();
		} );
	}
}
