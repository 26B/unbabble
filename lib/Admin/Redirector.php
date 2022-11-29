<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to redirecting when needed.
 *
 * @since 0.0.1
 */
class Redirector {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		// Handle language switching.
		if ( is_admin() && isset( $_GET['ubb_switch_lang'] ) ) {
			$this->handle_language_switch_redirect( $_GET['ubb_switch_lang'] );
		}

		// Redirects to fix current language for the one of the content.
		\add_action( 'admin_init', [ $this, 'redirect_post_if_needed' ], PHP_INT_MAX );
		\add_action( 'admin_init', [ $this, 'redirect_term_if_needed' ], PHP_INT_MAX );
	}

	/**
	 * Changes back-office language and redirects to new url.
	 *
	 * @since 0.0.1
	 *
	 * @param  string $new_lang
	 * @return void
	 */
	public function handle_language_switch_redirect( string $new_lang ) : void {
		$options = Options::get();

		// Validate new_lang.
		if ( ! in_array( $new_lang, $options['allowed_languages'], true ) ) {
			return;
		}

		// Update cookie with new lang.

		// Try to get cookie with expiration time. Otherwise use User Session default expiration time.
		$expiration = LangCookie::get_lang_expire_cookie();
		setcookie( 'ubb_lang', $new_lang, $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );

		$uri = $_SERVER['REQUEST_URI'];
		if ( is_multisite() ) {
			$site_info = get_site();
			if ( $site_info->path !== '/' ) {
				$uri = str_replace( untrailingslashit( $site_info->path ), '', $uri );
			}
		}

		// Change `ubb_switch_lang` in query in url to `lang` if not default language.
		$path    = str_replace(
			"ubb_switch_lang={$new_lang}",
			$new_lang === $options['default_language'] ? '' : "lang={$new_lang}",
			$uri
		);
		$new_url = home_url( $path );

		// Redirect.
		nocache_headers();
		wp_safe_redirect( $new_url, 302, 'WordPress - Unbabble' );
		exit;
	}

	/**
	 * Redirect if the current language is not the correct one for the current post.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function redirect_post_if_needed() : void {
		if (
			! is_admin()
			|| ! isset( $_REQUEST['post'] )
			|| ! is_numeric( $_REQUEST['post'] )
		) {
			return;
		}

		$current_lang = LangInterface::get_current_language();
		$post_lang    = LangInterface::get_post_language( $_REQUEST['post'] );
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
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function redirect_term_if_needed() : void {
		if (
			! is_admin()
			|| ! isset( $_REQUEST['tag_ID'] )
			|| ! is_numeric( $_REQUEST['tag_ID'] )
		) {
			return;
		}

		$current_lang = LangInterface::get_current_language();
		$term_lang    = LangInterface::get_term_language( $_REQUEST['tag_ID'] );
		if ( $term_lang === null || $term_lang === $current_lang ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'lang', $term_lang, get_edit_term_link( $_REQUEST['tag_ID'], '', '&' ) ) );
		exit;
	}
}
