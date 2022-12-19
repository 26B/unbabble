<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Hooks related to wordpress routing via url directory.
 *
 * @since 0.0.1
 */
class Directory {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() || Options::get_router() !== 'directory' ) {
			return;
		}

		if ( ! is_admin() ) {
			// Needs to be done as early as possible.
			$this->init();

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

		\add_filter( 'home_url', [ $this, 'home_url' ], 10, 2 );
	}

	/**
	 * Changes $_SERVER in order to remove directories from the url and let query_var routing take
	 * place.
	 *
	 * Directory routing is a proxy for query_var routing. Every directory uri is changed, the
	 * directory is removed and the lang query argument is added. This procedure needs to be done as
	 * early as possible in order to avoid problems.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function init() : void {

		// Trailing slash to handle cases like homepage when url/uri does not have / at the end.
		$request_uri = trailingslashit( $this->clean_path( $_SERVER['REQUEST_URI'] ) );
		$lang        = $this->current_lang_from_uri( Options::get()['default_language'], $request_uri );
		if ( $lang === Options::get()['default_language'] ) {
			return;
		}

		$directory = $this->get_directory_name( $lang );
		if (
			str_starts_with( $request_uri, "/{$directory}/" )
			&& str_starts_with( trailingslashit( $this->clean_path( $_SERVER['PHP_SELF'] ) ), "/{$directory}/" )
		) {
			$_GET['lang'] = $lang;
		}
	}

	/**
	 * Get the current language from the uri.
	 *
	 * If the $uri does not contain a known directory (language), then the first argument $curr_lang
	 * is returned.
	 *
	 * @since 0.0.1
	 *
	 * @param string $curr_lang
	 * @param string $uri
	 * @return string Language of the directory in the $uri, if known, otherwise returns $curr_lang.
	 */
	public function current_lang_from_uri( string $curr_lang, string $uri ) : string {
		$languages        = Options::get()['allowed_languages'];
		$default_language = Options::get()['default_language'];
		foreach ( $languages as $lang ) {
			if ( $lang === $default_language ) {
				continue;
			}
			$directory = $this->get_directory_name( $lang );
			if ( str_starts_with( $uri, "/{$directory}/" ) ) {
				return $lang;
			}
		}
		return $curr_lang;
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

		if ( $post instanceof WP_Post ) {
			$post_id = $post->ID;
		} else if ( is_int( $post ) ) {
			$post_id = $post;
		} else {
			return $post_link;
		}

		$post_lang = LangInterface::get_post_language( $post_id );
		if ( empty( $post_lang ) ) {
			return $post_link;
		}

		$site_url = site_url();
		$url_lang = $this->current_lang_from_uri( '', str_replace( $site_url, '', $post_link ) );
		if ( $url_lang === $post_lang ) {
			return $post_link;
		}

		// The source url might be poluted by the home_url language addition.
		$source_url = $site_url;
		if ( ! empty( $url_lang ) && $url_lang !== $post_lang ) {
			$source_url = trailingslashit( $site_url ) . $this->get_directory_name( $url_lang );
		}

		// If it's not poluted and the language is the default language don't do anything to it.
		if ( $post_lang === Options::get()['default_language'] && $source_url === $site_url ) {
			return $post_link;
		}

		// If not default language, set the directory to the post language.
		$target_url = $site_url;
		if ( $post_lang !== Options::get()['default_language'] ) {
			$directory  = $this->get_directory_name( $post_lang );
			$target_url = trailingslashit( $site_url ) . $directory;
		}

		return str_replace( $source_url, $target_url, $post_link );
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
		// When attachments are attached to a post, their url already has the lang from the post permalink.
		$lang = $this->current_lang_from_uri( '', parse_url( $link, PHP_URL_PATH ) );
		if ( ! empty( $lang ) ) {
			return $link;
		}

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
		$site_url  = site_url();
		$url_lang  = $this->current_lang_from_uri( '', str_replace( $site_url, '', $termlink ) );
		if ( $url_lang === $term_lang ) {
			return $termlink;
		}

		// The source url might be poluted by the home_url language addition.
		$source_url = $site_url;
		if ( ! empty( $url_lang ) && $url_lang !== $term_lang ) {
			$source_url = trailingslashit( $site_url ) . $this->get_directory_name( $url_lang );
		}

		// If it's not poluted and the language is the default language don't do anything to it.
		if ( $term_lang === Options::get()['default_language'] && $source_url === $site_url ) {
			return $termlink;
		}

		// If not default language, set the directory to the term language.
		$target_url = $site_url;
		if ( $term_lang !== Options::get()['default_language'] ) {
			$directory  = $this->get_directory_name( $term_lang );
			$target_url = trailingslashit( $site_url ) . $directory;
		}

		return str_replace( $source_url, $target_url, $termlink );
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

		if ( ! $query->is_main_query() || ! is_home() ) {
			return;
		}

		// If there is a language set, that takes precedence.
		$lang    = '';
		$options = Options::get();
		if ( in_array( $_GET['lang'] ?? '', $options['allowed_languages'], true ) ) {
			$lang = \sanitize_text_field( $_GET['lang'] );
		}
		if ( ! empty( $lang ) ) {
			return;
		}

		// Set language of homepage to the default language.
		set_query_var( 'lang', $options['default_language'] );
	}

	/**
	 * Stop redirect to 404 if the found post's language is not the same as the current language.
	 *
	 * TODO: improve this explanation
	 * Dealing with WP redirecting to post to its permalink (which includes the ?lang=XX) when it
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

		$directory = $this->get_directory_name( $curr_lang );
		$subpath = $path;
		if ( str_starts_with( $path, '/' ) ) {
			$subpath = substr( $path, 1 );
		}

		if ( empty( $subpath ) ) {
			$new_url = trailingslashit( $url ) . trailingslashit( $directory );
		} else {
			$new_url = str_replace( "/{$subpath}", "/{$directory}/{$subpath}", trailingslashit( $url ) );
		}
		return $new_url;
	}

	/**
	 * Get the directory name for a language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $lang
	 * @return string
	 */
	private function get_directory_name( string $lang ) : string {
		$options = Options::get();
		if (
			isset( $options['router_options']['directories'][ $lang ] )
			&& ! empty( $options['router_options']['directories'][ $lang ] )
			&& is_string( $options['router_options']['directories'][ $lang ] )
		) {
			return $options['router_options']['directories'][ $lang ];
		}
		return $lang;
	}

	private function clean_path( string $path ) : string {
		if ( is_multisite() ) {
			$site_info = get_site();
			if ( $site_info->path !== '/' ) {
				return str_replace( untrailingslashit( $site_info->path ), '', $path );
			}
		}
		return $path;
	}

	private function remove_directory_from_path( string $path, string $directory ) : string {
		if ( is_multisite() ) {
			$site_info = get_site();
			if ( $site_info->path !== '/' ) {
				return untrailingslashit( $site_info->path ) . substr(
					str_replace( untrailingslashit( $site_info->path ), '', $path ),
					strlen( "/{$directory}" )
				);
			}
		}
		return substr( $path, strlen( "/{$directory}" ) );
	}

	private function path_has_directory( string $path, string $lang ) : bool {
		return $lang === $this->current_lang_from_uri( '', $path );
	}
}
