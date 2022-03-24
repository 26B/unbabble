<?php

namespace TwentySixB\WP\Plugin\Unbabble;


use WP_Query;

/**
 * Control language filtering for queried content.
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


	/**
	 * Filter queried posts by current language.
	 *
	 * @param \WP_Query $wp_query WordPress query object.
	 *
	 * @return \WP_Query
	 */
	public function filter_posts_by_language( \WP_Query $wp_query ) : \WP_Query {

		if ( isset( $wp_query->query_vars['post_type'] ) && $wp_query->query_vars['post_type'] === 'attachment' ) {
			return $wp_query;
		}

		$language = $this->get_language();

		// Don't do anything if there is no language defined.
		if ( empty( $language ) ) {
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

		// Envelop meta query in our own meta condition.
		$wp_query->set(
			'meta_query',
			[
				'relation' => 'AND',
				[
					'key'     => 'ubb_lang',
					'value'   => $language,
					'compare' => '=',
				],
				$meta_query,
			]
		);

		return $wp_query;
	}

	/**
	 * Determine language for the current query.
	 *
	 * @since 0.0.0
	 *
	 * @return string Language identifier.
	 */
	private function get_language() : string {

		// Reset language cookie.
		if ( is_admin() ) {
			//TODO: function to get ubb_lang cookie
			return $_COOKIE['ubb_lang'] ?? '';
		}

		// TODO: getting language in frontend.
		return '';
	}
}
