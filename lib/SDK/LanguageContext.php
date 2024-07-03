<?php

namespace TwentySixB\WP\Plugin\Unbabble\SDK;

/**
 * Provide an interface to switch language contexts.
 *
 * @since [unreleased]
 */
class LanguageContext {

	/**
	 * The current language.
	 *
	 * @since [unreleased]
	 *
	 * @var string
	 */
	static $current_lang = null;

	/**
	 * The language to switch to.
	 *
	 * @since [unreleased]
	 *
	 * @param string $lang The language to switch to.
	 * @return string
	 */
	private static function switch( string $lang ) {
		return static::$lang;
	}

	/**
	 * Switch to a specific language.
	 *
	 * @since [unreleased]
	 *
	 * @param string $lang The language to switch to.
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the language is empty.
	 */
	public static function switch_to_language( string $lang ) {

		// Throw to prevent empty language issues.
		if ( empty( $lang ) ) {
			throw new \InvalidArgumentException( 'Language cannot be empty' );
		}

		static::$current_lang = $lang;

		\add_filter( 'ubb_current_lang', [ self::class, 'switch' ] );
	}

	public static function restore_language() {
		\remove_filter( 'ubb_current_lang', [ self::class, 'switch' ] );
	}
}
