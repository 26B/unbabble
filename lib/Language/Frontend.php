<?php

namespace TwentySixB\WP\Plugin\Unbabble\Language;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to the language of frontend.
 *
 * @since 0.0.0
 */
class Frontend {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		\add_filter( 'locale', [ $this, 'switch_locale'] );
		\add_action( 'admin_bar_menu', [ $this, 'admin_bar_locale'], PHP_INT_MIN );
	}

	public function switch_locale( $locale ) {
		if ( is_admin() ) {
			return $locale;
		}
		$current = LangInterface::get_current_language();
		if ( in_array( $current, get_available_languages(), true ) ) {
			return $current;
		}
		return $locale;
	}

	public function admin_bar_locale() : void {
		switch_to_locale( get_user_locale() );
		\add_filter( 'wp_after_admin_bar_render', function () {
			restore_previous_locale();
		} );
	}
}
