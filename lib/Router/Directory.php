<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * For hooks related to wordpress routing via the query_var lang.
 */
class Directory {
	public function register() {
		if ( Options::only_one_language_allowed() || Options::get_router() !== 'directory' ) {
			return;
		}

		if ( ! is_admin() ) {
			// FIXME: HACKY METHOD TO GET ROUTING TO WORK CORRECTLY.
			\add_filter( 'init', [ $this, 'init' ], PHP_INT_MIN );

			\add_filter( 'pre_get_posts', [ $this, 'homepage_default_lang_redirect' ], 1 );
		}

		// Post Permalinks:
		$allowed_post_types = Options::get_allowed_post_types();

		if ( in_array( 'post', $allowed_post_types, true ) ) {
			// Post permalink.
			\add_filter( 'post_link', [ $this, 'apply_lang_to_post_url' ], 10, 2 );
		}

		if ( in_array( 'page', $allowed_post_types, true ) ) {
			// Page permalink.
			\add_filter( 'page_link', [ $this, 'apply_lang_to_post_url' ], 10, 2 );
		}

		if ( in_array( 'attachment', $allowed_post_types, true ) ) {
			// Attachment permalink.
			\add_filter( 'attachment_link', [ $this, 'apply_lang_to_attachment_url' ], 10, 2 );
		}

		// Custom post types permalinks.
		\add_filter( 'post_type_link', [ $this, 'apply_lang_to_custom_post_url' ], 10, 2 );

		// Term archive permalinks.
		\add_filter( 'term_link', [ $this, 'apply_lang_to_term_link' ], 10, 3 );

		// TODO: post_type_archive_link

		\add_filter( 'pre_redirect_guess_404_permalink', [ $this, 'pre_redirect_guess_404_permalink' ] );
	}

	public function init() : void {
		$lang = $this->current_lang_from_uri( Options::get()['default_language'], $_SERVER['REQUEST_URI'] );
		if ( $lang === Options::get()['default_language'] ) {
			return;
		}

		if (
			str_starts_with( $_SERVER['REQUEST_URI'], "/{$lang}/" )
			&& str_starts_with( $_SERVER['PHP_SELF'], "/{$lang}/" )
		) {
			$_SERVER['REQUEST_URI'] = add_query_arg(
				'lang',
				$lang,
				substr( $_SERVER['REQUEST_URI'], strlen( "/{$lang}" ) )
			);
			$_SERVER['PHP_SELF']    = substr( $_SERVER['PHP_SELF'], strlen( "/{$lang}" ) );
			$_GET['lang']           = $lang;
		}
	}

	public function current_lang_from_uri( string $curr_lang, string $uri ) : string {
		$languages        = Options::get()['allowed_languages'];
		$default_language = Options::get()['default_language'];
		foreach ( $languages as $lang ) {
			if ( $lang === $default_language ) {
				continue;
			}
			if ( str_starts_with( $uri, "/{$lang}/" ) ) {
				return $lang;
			}
		}
		return $curr_lang;
	}

	public function apply_lang_to_post_url( string $post_link, WP_Post $post ) : string {
		$post_lang = LangInterface::get_post_language( $post->ID );
		if ( $post_lang === Options::get()['default_language'] ) {
			return $post_link;
		}
		$site_url = site_url();
		return str_replace( $site_url, trailingslashit( $site_url ) . $post_lang, $post_link );
	}

	public function apply_lang_to_custom_post_url( string $post_link, WP_Post $post ) : string {
		$post_type = $post->post_type;
		if ( ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return $post_link;
		}
		return $this->apply_lang_to_post_url( $post_link, $post );
	}

	public function apply_lang_to_attachment_url( string $link, int $post_id ) : string {
		// When attachments are attached to a post, their url already has the lang from the post permalink.
		$lang = $this->current_lang_from_uri( '', parse_url( $link, PHP_URL_PATH ) );
		if ( ! empty( $lang ) ) {
			return $link;
		}

		$post = WP_Post::get_instance( $post_id );
		return $this->apply_lang_to_post_url( $link, $post );
	}

	public function apply_lang_to_term_link( string $termlink, WP_Term $term, string $taxonomy ) : string {
		if ( ! in_array( $taxonomy, Options::get_allowed_taxonomies(), true ) ) {
			return $termlink;
		}
		$term_lang = LangInterface::get_term_language( $term->term_id );
		if ( $term_lang === Options::get()['default_language'] ) {
			return $termlink;
		}
		$site_url = site_url();
		return str_replace( $site_url, trailingslashit( $site_url ) . $term_lang, $termlink );
	}

	public function homepage_default_lang_redirect( \WP_Query $query ) : void {
		if ( ! $query->is_main_query() || ! is_home()) {
			return;
		}

		// If there is a language set, that takes precedence.
		$lang = $this->current_lang_from_uri( '', $_SERVER['REQUEST_URI'] );
		if ( ! empty( $lang ) ) {
			return;
		}

		// Set language of homepage to the default language.
		set_query_var( 'lang', Options::get()['default_language'] );
	}

	/**
	 * Deal with WP redirecting to post to its permalink (which includes the ?lang=XX) when it
	 * shouldn't, because its not the correct language in the original URL.
	 * Going to the url of an english (non main language) post without the ?lang=en query_var
	 * then wordpress will try to guess the redirect from the 404 and find the english post and
	 * get its permalink, which will include the ?lang=en. We don't want this to happen, so if
	 * the guessed post is not of the current language then we stop the redirect by faking
	 * that a post was not found. Most of the code in here is a copy of some of the code in the
	 * WP method redirect_guess_404_permalink found in canonical.php. This was done since there
	 * was no way of filtering the post found after the fact, but we wanted to have the same
	 * behaviour of guessing that WP has.
	 */
	public function pre_redirect_guess_404_permalink( $pre ) {
		// TODO: What to do with the $pre.
		global $wpdb;
		if ( get_query_var( 'name' ) ) {
			/**
			 * Filters whether to perform a strict guess for a 404 redirect.
			 *
			 * Returning a truthy value from the filter will redirect only exact post_name matches.
			 *
			 * @since 5.5.0
			 *
			 * @param bool $strict_guess Whether to perform a strict guess. Default false (loose guess).
			 */
			$strict_guess = apply_filters( 'strict_redirect_guess_404_permalink', false );

			if ( $strict_guess ) {
				$where = $wpdb->prepare( 'post_name = %s', get_query_var( 'name' ) );
			} else {
				$where = $wpdb->prepare( 'post_name LIKE %s', $wpdb->esc_like( get_query_var( 'name' ) ) . '%' );
			}

			// If any of post_type, year, monthnum, or day are set, use them to refine the query.
			if ( get_query_var( 'post_type' ) ) {
				if ( is_array( get_query_var( 'post_type' ) ) ) {
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$where .= " AND post_type IN ('" . join( "', '", esc_sql( get_query_var( 'post_type' ) ) ) . "')";
				} else {
					$where .= $wpdb->prepare( ' AND post_type = %s', get_query_var( 'post_type' ) );
				}
			} else {
				$where .= " AND post_type IN ('" . implode( "', '", get_post_types( array( 'public' => true ) ) ) . "')";
			}

			if ( get_query_var( 'year' ) ) {
				$where .= $wpdb->prepare( ' AND YEAR(post_date) = %d', get_query_var( 'year' ) );
			}
			if ( get_query_var( 'monthnum' ) ) {
				$where .= $wpdb->prepare( ' AND MONTH(post_date) = %d', get_query_var( 'monthnum' ) );
			}
			if ( get_query_var( 'day' ) ) {
				$where .= $wpdb->prepare( ' AND DAYOFMONTH(post_date) = %d', get_query_var( 'day' ) );
			}

			$publicly_viewable_statuses = array_filter( get_post_stati(), 'is_post_status_viewable' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$post_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE $where AND post_status IN ('" . implode( "', '", esc_sql( $publicly_viewable_statuses ) ) . "')" );

			if ( ! $post_id ) {
				return false;
			}

			error_log( print_r( $post_id, true ) );
			error_log( print_r( LangInterface::get_post_language( $post_id ), true ) );
			error_log( print_r( LangInterface::get_current_language(), true ) );

			// If the post language is not the same as the current language, then don't redirect.
			if ( LangInterface::get_post_language( $post_id ) !== LangInterface::get_current_language() ) {
				return false;
			}

			if ( get_query_var( 'feed' ) ) {
				return get_post_comments_feed_link( $post_id, get_query_var( 'feed' ) );
			} elseif ( get_query_var( 'page' ) > 1 ) {
				return trailingslashit( get_permalink( $post_id ) ) . user_trailingslashit( get_query_var( 'page' ), 'single_paged' );
			} else {
				return get_permalink( $post_id );
			}
		}

		return false;
	}
}
