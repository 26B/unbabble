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

		// Validate new_lang.
		if ( ! LangInterface::is_language_allowed( $new_lang ) ) {
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
			$new_lang === LangInterface::get_default_language() ? '' : "lang={$new_lang}",
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
	 * @since 0.4.8 Add checks for bad edit link and/or unknown post, and redirects for those cases.
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

		$post_type = get_post_type( $_REQUEST['post'] );
		if ( ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return;
		}

		$current_lang = LangInterface::get_current_language();
		$post_lang    = LangInterface::get_post_language( $_REQUEST['post'] );
		if (
			$post_lang === null
			|| $post_lang === $current_lang
			|| ! LangInterface::is_language_allowed( $post_lang )
		) {
			return;
		}

		// Fetch edit post link and redirect to it with the correct language if it's valid.
		$edit_post_link = get_edit_post_link( $_REQUEST['post'], '&' );
		if ( ! empty( $edit_post_link ) ) {
			wp_safe_redirect( add_query_arg( 'lang', $post_lang, $edit_post_link ) );
			exit;
		}

		// Fetch post and redirect to main admin page if the post is not found.
		$post = get_post( $_REQUEST['post'] );
		if ( empty( $post ) || ! isset( $post->post_type ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// Redirect to the post type list page with the correct language.
		wp_safe_redirect(
			add_query_arg(
				array_filter(
					[
						'lang'      => $post_lang,
						'post_type' => $post->post_type === 'post' ? '' : $post->post_type,
					],
				),
				'edit.php'
			)
		);
		exit;
	}

	/**
	 * Redirect if the current language is not the correct one for the current term.
	 *
	 * @since 0.5.0 Keep other query arguments in edit term link redirect.
	 * @since 0.4.8 Add checks for bad edit link and/or unknown term, and redirects for those cases.
	 * @since 0.4.2 Don't redirect when term lang is not in allowed languages to allow the user to fix it.
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

		// If the language is not in the allowed languages, do nothing so user can fix it.
		$languages = LangInterface::get_languages();
		if ( ! in_array( $term_lang, $languages, true ) ) {
			return;
		}

		// Fetch edit term link and redirect to it with the correct language if it's valid.
		$edit_term_link = get_edit_term_link( $_REQUEST['tag_ID'], '', '&' );
		if ( ! empty( $edit_term_link ) ) {
			$query_args = $_REQUEST;
			unset( $query_args['taxonomy'], $query_args['tag_ID'], $query_args['post_type'], $query_args['lang'] );
			$query_args['lang'] = $term_lang;
			$edit_term_link = add_query_arg( $query_args, $edit_term_link );;
			wp_safe_redirect( $edit_term_link );
			exit;
		}

		// Fetch term and redirect to main admin page if the term is not found.
		$term = get_term( $_REQUEST['tag_ID'] );
		if ( empty( $term ) || ! isset( $term->taxonomy ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// Redirect to the taxonomy list page with the correct language.
		wp_safe_redirect(
			add_query_arg(
				array_filter(
					[
						'lang'      => $term_lang,
						'taxonomy'  => $term->taxonomy === 'post_tag' ? '' : $term->taxonomy,
						'post_type' => $_REQUEST['post_type'] ?? '',
					],
				),
				'edit-tags.php'
			)
		);
		exit;
	}
}
