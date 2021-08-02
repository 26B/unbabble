<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * Handler for the `unbabble_options` option.
 *
 * @since 0.0.0
 */
class Options {

	public static function get() : array {
		$options = \get_option( 'unbabble_options' );
		if ( $options ) {
			return $options;
		}

		$wp_locale = \get_locale();
		return [
			'allowed_languages' => [ $wp_locale ],
			'default_language'  => $wp_locale,
		];
	}

	public static function only_one_language_allowed() : bool {
		return count( self::get()['allowed_languages'] ) === 1;
	}
}
