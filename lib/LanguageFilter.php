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

	public function filter_posts_by_language( WP_Query $wp_query ) : WP_Query {
		$meta_query = $wp_query->get( 'meta_query', [] );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = [];
		}

		$language_query = [
			'key'     => 'ubb_lang',
			'value'   => $_COOKIE['ubb_lang'],   //TODO: function to get ubb_lang cookie
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

		$meta_query[] = $language_query;
		$wp_query->set( 'meta_query', $meta_query );
		return $wp_query;
	}
}
