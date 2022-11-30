<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Hooks related to wordpress routing via the query_var lang.
 *
 * @since 0.0.1
 */
class QueryVar {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( Options::only_one_language_allowed() || Options::get_router() !== 'query_var' ) {
			return;
		}

		if ( ! is_admin() ) {
			add_filter( 'query_vars', function( $query_vars ) {
				if ( ! in_array( 'lang', $query_vars, true ) ) {
					$query_vars[] = 'lang';
				}
				return $query_vars;
			} );

			// TODO: We might need this sooner.
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

		// TODO: The hooks for the permalinks might no longer be necessary with this hook.
		\add_filter( 'home_url', [ $this, 'home_url' ], 10, 2 );
	}

	/**
	 * Applies language to the post's link given it's language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $post_link
	 * @param WP_Post|int|mixed $post
	 * @return string
	 */
	public function apply_lang_to_post_url( string $post_link, $post ) : string {

		// Don't do anything if switched to a site without unbabble.
		if ( ! LangInterface::is_unbabble_active() ) {
			return $post_link;
		}

		if ( is_numeric( $post ) ) {
			$post_id = (int) $post;
		} else if ( $post instanceof WP_Post ) {
			$post_id = $post->ID;
		} else {
			return $post_link;
		}
		$post_lang = LangInterface::get_post_language( $post_id );
		if ( $post_lang ===  Options::get()['default_language'] ) {
			return remove_query_arg( 'lang', $post_link );
		}
		return add_query_arg( 'lang', $post_lang, $post_link );
	}

	/**
	 * Applies language to the custom post's link given it's language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $post_link
	 * @param WP_Post $post
	 * @return string
	 */
	public function apply_lang_to_custom_post_url( string $post_link, WP_Post $post ) : string {
		$post_type = $post->post_type;
		if ( ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return $post_link;
		}
		return $this->apply_lang_to_post_url( $post_link, $post );
	}

	/**
	 * Applies language to the attachment's link given it's language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $link
	 * @param int $post_id
	 * @return string
	 */
	public function apply_lang_to_attachment_url( string $link, int $post_id ) : string {
		$post = WP_Post::get_instance( $post_id );
		return $this->apply_lang_to_post_url( $link, $post );
	}

	/**
	 * Applies language to the term's link given it's language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $termlink
	 * @param WP_Term $term
	 * @param string $taxonomy
	 * @return string
	 */
	public function apply_lang_to_term_link( string $termlink, WP_Term $term, string $taxonomy ) : string {

		// Don't do anything if switched to a site without unbabble.
		if ( ! LangInterface::is_unbabble_active() ) {
			return $termlink;
		}

		if ( ! in_array( $taxonomy, Options::get_allowed_taxonomies(), true ) ) {
			return $termlink;
		}
		$term_lang = LangInterface::get_term_language( $term->term_id );
		if ( $term_lang ===  Options::get()['default_language'] ) {
			return remove_query_arg( 'lang', $termlink );
		}
		return add_query_arg( 'lang', $term_lang, $termlink );
	}

	/**
	 * Sets the language for the default homepage.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function homepage_default_lang_redirect( \WP_Query $query ) : void {

		// Don't do anything if switched to a site without unbabble.
		if ( ! LangInterface::is_unbabble_active() ) {
			return;
		}

		if ( ! $query->is_main_query() || ! is_home()) {
			return;
		}

		// If there is a language set, that takes precedence.
		$lang = get_query_var( 'lang' );
		if ( ! empty( $lang ) ) {
			return;
		}

		// Set language of homepage to the default language.
		set_query_var( 'lang', Options::get()['default_language'] );
	}

	/**
	 * Stop redirect to 404 if the found post's language is not the same as the current language.
	 *
	 * TODO: improve this explanation
	 *
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
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $pre
	 * @return null|string|false
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
			 * @since 0.0.1
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

	/**
	 * Adds directory to home_url.
	 *
	 * @since 0.0.1
	 *
	 * @param string $url
	 * @param string $path
	 * @return string
	 */
	public function home_url( string $url, string $path ) : string {
		/**
		 * Filters whether to change the home url or not, given the routing type and the current
		 * language.
		 *
		 * @since 0.0.1
		 * @param bool   $stop_url_change Whether to change the home url or not.
		 * @param string $url             Home url.
		 * @param string $path            Url path.
		 */
		if ( apply_filters( 'ubb_home_url', false, $url, $path ) ) {
			return $url;
		}

		$curr_lang = LangInterface::get_current_language();
		if ( $curr_lang === Options::get()['default_language'] ) {
			return $url;
		}

		return add_query_arg( 'lang', $curr_lang, $url );
	}
}
