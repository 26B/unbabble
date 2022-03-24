<?php

namespace TwentySixB\WP\Plugin\Unbabble;


use WP_Query;

/**
 *
 * @since 0.0.0
 */
class LanguageFilter {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		\add_filter( 'pre_get_posts', [ $this, 'filter_posts_by_language' ] );
	}


	public function filter_posts_by_language( WP_Query $wp_query, string $language = '' ) : WP_Query {

		if ( isset( $wp_query->query_vars['post_type'] ) && $wp_query->query_vars['post_type'] === 'attachment' ) {
			return $wp_query;
		}

		// Don't do anything if there is no language defined.
		if ( empty( $language ) ) {

			// Reset language cookie.
			if ( is_admin() ) {
				//TODO: function to get ubb_lang cookie
				$language = $_COOKIE['ubb_lang'] ?? '';
			} else {
				// TODO: getting language in frontend.
			}

			return $wp_query;
		}

		/**
		 * Allow disabling transalation system on current query.
		 *
		 * @param bool      True if translation filtering should occur, false
		 *                  otherwise.
		 * @param \WP_Query Current query object.
		 */
		if ( ! \apply_filters( 'ubb_filter_pre_get_posts', true, $wp_query ) ) {
			return $wp_query;
		}

		$meta_query = $wp_query->get( 'meta_query', [] );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = [];
		}

		$language_query = [
			'key'     => 'ubb_lang',
			'value'   => esc_sql( $language ), // TODO: might not be needed.
			'compare' => '=',
		];

		// If there is an OR condition, it needs to become a lower condition.
		if ( isset( $meta_query['relation'] ) && $meta_query['relation'] === 'OR' ) {
			$wp_query->set(
				'meta_query',
				[
					'relation' => 'AND',
					$meta_query,
					$language_query,
				]
			);
			return $wp_query;
		}

		if ( empty( $meta_query ) || ! isset( $meta_query['relation'] ) ) {
			$meta_query['relation'] = 'AND';
		}

		$meta_query[] = $language_query;
		$wp_query->set( 'meta_query', $meta_query );
		return $wp_query;
	}
}
