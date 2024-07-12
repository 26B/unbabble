<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Post;
use WP_Query;

/**
 * Hooks for integrating with ElasticPress.
 *
 * @since Unreleased
 */
class ElasticPress {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased
	 */
	public function register() {
		if ( ! \is_plugin_active( 'elasticpress/elasticpress.php' ) ) {
			return;
		}

		\add_filter( 'ep_index_posts_args', [ $this, 'ep_index_posts_args' ], 10, 1 );
		\add_filter( 'ep_prepare_meta_data', [ $this, 'ep_prepare_meta_data' ], 10, 2 );
		\add_filter( 'ep_skip_query_integration', [ $this, 'ep_skip_query_integration' ], PHP_INT_MIN, 2 );
	}

	/**
	 * Add argument to ElasticPress's sync query arguments to not apply Unbabble's lang filter.
	 *
	 * @since Unreleased
	 *
	 * @param array $args
	 * @return array
	 */
	public function ep_index_posts_args( array $args ) : array {
		$args['ubb_lang_filter'] = false;
		return $args;
	}

	/**
	 * Add post language to meta entries sent to ElasticPress in order to be able to filter by
	 * language later.
	 *
	 * @since Unreleased
	 *
	 * @param array   $meta
	 * @param WP_Post $post
	 * @return array
	 */
	public function ep_prepare_meta_data( array $meta, WP_Post $post ) : array {
		if ( ! isset( $post->ID ) || ! is_numeric( $post->ID ) ) {
			return $meta;
		}

		$meta['ubb_lang'] = [ '' ];
		if ( LangInterface::is_post_type_translatable( $post->post_type ) ) {
			$meta['ubb_lang'] = LangInterface::get_post_language( $post->ID );
		}
		return $meta;
	}

	/**
	 * Add query_vars to ElasticPress's fetch query to filter by language.
	 *
	 * Using the `ep_skip_query_integration` filter since its the last filter we can use to
	 * specifically change the fetch query, and there is no appropriate filter otherwise.
	 *
	 * @since Unreleased
	 *
	 * @param bool     $return
	 * @param WP_Query $query
	 * @return bool
	 */
	public function ep_skip_query_integration( bool $return, WP_Query $query ) : bool {
		$query->query_vars['meta_query'] = [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				// Translatable posts.
				[
					'key'     => 'ubb_lang',
					'value'   => LangInterface::get_current_language(),
					'compare' => '=',
				],
				// Non-translatable posts.
				[
					'key'     => 'ubb_lang',
					'value'   => '',
					'compare' => '=',
				],
			],
			$query->query_vars['meta_query'] ?? [],
		];
		return $return;
	}
}
