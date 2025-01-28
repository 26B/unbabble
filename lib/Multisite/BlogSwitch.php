<?php

namespace TwentySixB\WP\Plugin\Unbabble\Multisite;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to multisite and blog switching.
 *
 * @since Unreleased
 */
class BlogSwitch {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() || ! is_multisite() ) {
			return;
		}

		add_action( 'switch_blog', [ $this, 'switch_blog' ], 10, 3 );
	}

	/**
	 * Set the language correctly when switching and restoring the current blog.
	 *
	 * @since Unreleased
	 *
	 * @param int    $new_blog_id
	 * @param int    $prev_blog_id
	 * @param string $context
	 * @return void
	 */
	public function switch_blog( int $new_blog_id, int $prev_blog_id, string $context ) : void {
		if ( $context === 'switch' ) {
			$this->handle_switch( $new_blog_id, $prev_blog_id );

		} else if ( $context === 'restore' ) {
			$this->handle_restore();
		}
	}

	/**
	 * Handle switching the blog.
	 *
	 * @since Unreleased
	 *
	 * @param int $new_blog_id
	 * @param int $prev_blog_id
	 * @return void
	 */
	private function handle_switch( int $new_blog_id, int $prev_blog_id ) : void {
		global $ubb_switch_stack;

		// Put previous language into the stack.
		$lang = $this->get_lang_for_stack( $prev_blog_id );
		if ( ! isset( $ubb_switch_stack ) ) {
			$ubb_switch_stack = [];
		}

		// Save language in stack.
		$ubb_switch_stack[] = $lang;

		// Set language to the new blog.
		$new_options = Options::get_via_wpdb( $new_blog_id );

		// If the previous language is in the new blog's languages, set it.
		if ( in_array( $lang, LangInterface::get_languages( $new_options ), true ) ) {
			\set_query_var( 'lang', $lang );

		} else {

			// If the language is not in the new blog's languages, set it to the default language of the new blog.
			\set_query_var( 'lang', $new_options['default_language'] );
		}
	}

	/**
	 * Handle restoring the blog.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	private function handle_restore() : void {
		global $ubb_switch_stack;

		$lang = array_pop( $ubb_switch_stack );
		if ( $lang === null ) {
			\set_query_var( 'lang', false );

		} else {
			\set_query_var( 'lang', $lang );
		}
	}

	/**
	 * Get the language for the stack.
	 *
	 * @since Unreleased
	 *
	 * @param int $blog_id
	 * @return string
	 */
	private function get_lang_for_stack( int $blog_id ) : string {
		$prev_options = Options::get_via_wpdb( $blog_id );

		// Figure out language of where we came from.
		$lang = get_query_var( 'lang', null );

		if ( empty( $lang ) && isset( $_GET['lang'] ) ) {
			$lang = $_GET['lang'];
		}

		if ( empty( $lang ) && is_admin() ) {
			$lang = $_COOKIE['ubb_lang'] ?? null;
		}

		// If the language is not set or is not in the previous blog's languages, set it to the previous blog's default language.
		if (
			empty( $lang )
			|| ! in_array( $lang, LangInterface::get_languages( $prev_options ), true )
		) {
			$lang = $prev_options['default_language'] ?? null;
		}

		return $lang;
	}
}
