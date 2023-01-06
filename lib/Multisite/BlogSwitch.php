<?php

namespace TwentySixB\WP\Plugin\Unbabble\Multisite;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to multisite and blog switching.
 *
 * @since 0.0.3
 */
class BlogSwitch {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.3
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
	 * @since 0.0.3
	 *
	 * @param int    $new_blog_id
	 * @param int    $prev_blog_id
	 * @param string $context
	 * @return void
	 */
	public function switch_blog( int $new_blog_id, int $prev_blog_id, string $context ) : void {
		global $ubb_switch_stack;

		if ( $context === 'switch' ) {
			// Figure out language of where we came from (if $_GET['lang'] is present use that, otherwise fetch via options.)
			if ( isset( $_GET['lang'] ) ) {
				$lang = $_GET['lang'];
			} else if ( is_admin() && isset( $_COOKIE['ubb_lang'] ) ) {
				$lang = $_COOKIE['ubb_lang'];
			} else {
				$lang = $this->get_default_lang_from_blog( $prev_blog_id );
			}

			// TODO: what if lang is null or empty string?
			if ( ! isset( $ubb_switch_stack ) ) {
				$ubb_switch_stack = [];
			}

			// Save language in stack.
			$ubb_switch_stack[] = $lang;

			// Set language according to previous language.
			if ( ! LangInterface::set_current_language( $lang ) ) {
				LangInterface::set_current_language( Options::get()['default_language'] );
			}

		} else if ( $context === 'restore' ) {
			$lang = array_pop( $ubb_switch_stack );
			if ( $lang === null ) {
				set_query_var( 'lang', false );
			} else {
				LangInterface::set_current_language( $lang );
			}
		}
	}

	/**
	 * Get the default language from a blog.
	 *
	 * @since 0.0.3
	 *
	 * @param int $blog_id
	 * @return string
	 */
	private function get_default_lang_from_blog( int $blog_id ) : string {
		return Options::get_via_wpdb( $blog_id )['default_language'];
	}
}
