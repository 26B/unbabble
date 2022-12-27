<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Query;

/**
 * Hooks for filtering posts based on their language.
 *
 * @since 0.0.1
 */
class LangFilter {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}
		\add_filter( 'posts_where', [ $this, 'filter_posts_by_language' ], 10, 2 );
	}

	/**
	 * Adds where clauses to query in order to filters posts by language, if necessary.
	 *
	 * @since 0.0.1
	 *
	 * @param string   $where
	 * @param WP_Query $query
	 * @return string
	 */
	public function filter_posts_by_language( string $where, WP_Query $query ) : string {
		global $wpdb;
		if ( ! $this->allow_filter( $query ) ) {
			return $where;
		}

		// TODO: Deal with untranslatable post types.

		$current_lang       = esc_sql( LangInterface::get_current_language() );
		$post_lang_table    = ( new PostTable() )->get_table_name();
		$allowed_post_types = implode( "','", Options::get_allowed_post_types() );
		$where .= " AND (
			{$wpdb->posts}.ID IN (
				SELECT post_id
				FROM {$post_lang_table} AS PT
				WHERE locale = '$current_lang'
				UNION
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_type NOT IN ('{$allowed_post_types}')
			)
		)";

		return $where;
	}

	/**
	 * Returns whether the filtering of posts should happen.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Query $query
	 * @return bool
	 */
	public function allow_filter( WP_Query $query ) : bool {
		$post_type = $query->get( 'post_type', null );
		if ( empty( $post_type ) && ! empty( $query->get( 'pagename', null ) ) ) {
			$post_type = 'page';
		}

		if (
			! empty( $post_type )
			&& $post_type !== 'any'
			&& ! in_array( $post_type, Options::get_allowed_post_types(), true )
		) {
			return false;
		}

		// Don't apply filters on switch_to_blog to blogs without the plugin.
		if ( ! LangInterface::is_unbabble_active() ) {
			return false;
		}

		/**
		 * Filters whether posts should be filtered by their language.
		 *
		 * @since 0.0.1
		 *
		 * @param bool $apply_filter
		 * @param WP_Query $query
		 */
		return \apply_filters( 'ubb_use_post_lang_filter', true, $query );
	}
}
