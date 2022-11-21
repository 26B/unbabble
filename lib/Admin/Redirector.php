<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to redirecting when needed.
 *
 * @since 0.0.0
 */
class Redirector {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'admin_init', [ $this, 'redirect_post_if_needed' ], PHP_INT_MAX );
		\add_action( 'admin_init', [ $this, 'redirect_term_if_needed' ], PHP_INT_MAX );
	}

	/**
	 * Redirect if the current language is not the correct one for the current post.
	 *
	 * @return void
	 */
	public function redirect_post_if_needed() : void {
		if ( ! is_admin() ) {
			return;
		}

		$current_lang = LangInterface::get_current_language();

		if ( ! isset( $_REQUEST['post'] ) || ! is_numeric( $_REQUEST['post'] ) ) {
			return;
		}

		$post_lang = LangInterface::get_post_language( $_REQUEST['post'] );
		if (
			$post_lang === null
			|| $post_lang === $current_lang
			|| ! in_array( $post_lang, Options::get()['allowed_languages'], true )
		) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'lang', $post_lang, get_edit_post_link( $_REQUEST['post'], '&' ) ) );
		exit;
	}

	/**
	 * Redirect if the current language is not the correct one for the current term.
	 *
	 * @return void
	 */
	public function redirect_term_if_needed() : void {
		if ( ! is_admin() ) {
			return;
		}

		$current_lang = LangInterface::get_current_language();

		if ( ! isset( $_REQUEST['tag_ID'] ) || ! is_numeric( $_REQUEST['tag_ID'] ) ) {
			return;
		}

		$term_lang = LangInterface::get_term_language( $_REQUEST['tag_ID'] );
		if ( $term_lang === null || $term_lang === $current_lang ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'lang', $term_lang, get_edit_term_link( $_REQUEST['tag_ID'], '', '&' ) ) );
		exit;
	}
}
