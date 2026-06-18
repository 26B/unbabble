<?php

namespace TwentySixB\WP\Plugin\Unbabble\SDK;

/**
 * Provide an interface to switch language contexts.
 *
 * @since [unreleased]
 */
class LanguageContext {

	/**
	 * The language stack.
	 *
	 * @since [unreleased]
	 *
	 * @var array
	 */
	static $language_stack = [];

	/**
	 * Switch to the language at the top of the stack.
	 *
	 * @since [unreleased]
	 *
	 * @param string $lang The language to switch to.
	 * @return string
	 */
	private static function switch() {
		return static::$language_stack[ count( static::$language_stack ) - 1 ];
	}

	/**
	 * Push a  to a specific language.
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

		// Add only one filter instance to the hook.
		if ( empty( static::$language_stack ) ) {
			\add_filter( 'ubb_current_lang', [ self::class, 'switch' ] );
		}

		static::$language_stack[] = $lang;
	}

	/**
	 * Restore the previous language in the stack.
	 *
	 * @since [unreleased]
	 *
	 * @return void
	 */
	public static function restore_language() {

		// Take the last language off the stack.
		array_pop( static::$language_stack );

		// Remove the filter if the stack is empty.
		if ( empty( static::$language_stack ) ) {
			\remove_filter( 'ubb_current_lang', [ self::class, 'switch' ] );
		}
	}
}
