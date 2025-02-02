<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use stdClass;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
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
		\add_filter( 'posts_where', [ $this, 'filter_posts_by_language' ], 10, 2 );
		\add_filter( 'wp_count_posts', [ $this, 'filter_count_posts' ], 10, 3 );
	}

	/**
	 * Adds where clauses to query in order to filters posts by language, if necessary.
	 *
	 * @since Unreleased Moved the where condition to a separate method.
	 * @since 0.4.2 Remove posts with bad language filter check. Now done directly via the filter `ubb_use_post_lang_filter` hook.
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

		$post_types = $query->get( 'post_type', null );
		if ( ! is_array( $post_types ) ) {
			$post_types = [ $post_types ];
		}

		$where .= $this->get_lang_filter_where_query( array_filter( $post_types ) );

		return $where;
	}

	/**
	 * Get the query for filtering posts by language.
	 *
	 * @since Unreleased
	 *
	 * @param array $post_types
	 * @return string
	 */
	public function get_lang_filter_where_query( array $post_types = [] ) : string {
		global $wpdb;

		$current_lang            = \esc_sql( LangInterface::get_current_language() );
		$post_lang_table         = ( new PostTable() )->get_table_name();
		$translatable_post_types = LangInterface::get_translatable_post_types();

		/**
		 * If all post_types passed are translatable, we don't need to do a UNION with all the
		 * untranslatable posts.
		 */
		$query_fully_translatable = ! empty( $post_types );
		foreach ( $post_types as $post_type ) {
			if ( ! in_array( $post_type, $translatable_post_types, true ) ) {
				$query_fully_translatable = false;
				break;
			}
		}

		// If not all post types requested are translatable, we don't need the untranslatable.
		$untranslatable_condition = '';
		if ( ! $query_fully_translatable ) {

			// If no post types are passed, we want to filter all post types.
			if ( empty( $post_types ) ) {
				$untranslatable_post_types = array_diff( get_post_types(), $translatable_post_types );

			// Otherwise, we want to filter only the post types passed.
			} else {
				$untranslatable_post_types = array_diff( $post_types, $translatable_post_types );
			}

			if ( ! empty( $untranslatable_post_types ) ) {
				$post_types_str = implode( "','", $untranslatable_post_types );
				$untranslatable_condition = "OR {$wpdb->posts}.post_type IN ('{$post_types_str}')";
			}
		}

		return sprintf(
			" AND (
				{$wpdb->posts}.ID IN (
					SELECT post_id
					FROM {$post_lang_table} AS PT
					WHERE locale = '{$current_lang}'
				)
				%s
			)",
			$untranslatable_condition,
		);
	}

	/**
	 * Get the query for filtering posts by language using a UNION query.
	 *
	 * This query is used to filter posts by language when the upper query does not have a
	 * post type column, like we do in WP_Query.
	 *
	 * @since Unreleased
	 *
	 * @param array $post_types
	 * @return string
	 */
	public function get_lang_filter_union_query( array $post_types = [] ) : string {
		global $wpdb;

		$current_lang            = \esc_sql( LangInterface::get_current_language() );
		$post_lang_table         = ( new PostTable() )->get_table_name();
		$translatable_post_types = LangInterface::get_translatable_post_types();

		$untranslatable_post_types = [];

		// If no post types are passed, we want to filter all post types.
		if ( empty( $post_types ) ) {
			$untranslatable_post_types = array_diff( get_post_types(), $translatable_post_types );

		// Otherwise, we want to filter only the post types passed.
		} else {
			$untranslatable_post_types = array_diff( $post_types, $translatable_post_types );
		}

		if ( ! empty( $untranslatable_post_types ) ) {
			$post_types_str = implode( "','", $untranslatable_post_types );
		}

		return sprintf(
			"SELECT post_id
				FROM {$post_lang_table} AS PT
				WHERE locale = '{$current_lang}'
				%s",
			empty( $untranslatable_post_types ) ?
				''
				: "UNION
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_type IN ('{$post_types_str}')"
		);
	}

	/**
	 * Filter post counts by post_status by the current language.
	 *
	 * @since 0.0.10
	 *
	 * @param  stdClass $counts
	 * @param  string   $type
	 * @param  string   $perm
	 * @return stdClass
	 */
	public function filter_count_posts( stdClass $counts, string $type, string $perm ) : stdClass {
		global $wpdb;
		if ( ! LangInterface::is_post_type_translatable( $type ) ) {
			return $counts;
		}

		$current_lang    = LangInterface::get_current_language();
		$cache_key       = sprintf( 'ubb_%s_%s', $current_lang, _count_posts_cache_key( $type, $perm ) );
		$filtered_counts = wp_cache_get( $cache_key, 'counts' );
		if ( false !== $filtered_counts ) {
			// We may have cached this before every status was registered.
			foreach ( get_post_stati() as $status ) {
				if ( ! isset( $filtered_counts->{$status} ) ) {
					$filtered_counts->{$status} = 0;
				}
			}
			return $filtered_counts;
		}

		$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";

		if ( 'readable' === $perm && is_user_logged_in() ) {
			$post_type_object = get_post_type_object( $type );
			if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
				$query .= $wpdb->prepare(
					" AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
					get_current_user_id()
				);
			}
		}

		$current_lang     = esc_sql( $current_lang );
		$post_lang_table  = ( new PostTable() )->get_table_name();
		$query           .= "AND {$wpdb->posts}.ID IN (
			SELECT post_id
			FROM {$post_lang_table} AS PT
			WHERE locale = '{$current_lang}'
		)";

		$query .= ' GROUP BY post_status';

		$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );

		$filtered_counts  = array_fill_keys( get_post_stati(), 0 );

		foreach ( $results as $row ) {
			$filtered_counts[ $row['post_status'] ] = $row['num_posts'];
		}

		$filtered_counts = (object) $filtered_counts;
		wp_cache_set( $cache_key, $filtered_counts, 'counts' );

		return $filtered_counts;
	}

	/**
	 * Returns whether the filtering of posts should happen.
	 *
	 * @since 0.4.3 Add check for `ubb_lang_filter` query_var with a false value to stop the lang filter.
	 * @since 0.0.1
	 *
	 * @param WP_Query $query
	 * @return bool
	 */
	private function allow_filter( WP_Query $query ) : bool {
		$post_type = $query->get( 'post_type', null );
		if ( empty( $post_type ) && ! empty( $query->get( 'pagename', null ) ) ) {
			$post_type = 'page';
		}
		if ( is_array( $post_type ) ) {
			if ( empty( $post_type ) ) {
				$post_type = '';
			} else if ( is_string( current( $post_type ) ) ) {
				$post_type = current( $post_type );
			} else {
				return false;
			}
		}

		if (
			! empty( $post_type )
			&& $post_type !== 'any'
			&& ! LangInterface::is_post_type_translatable( $post_type )
		) {
			return false;
		}

		// Don't apply filters on switch_to_blog to blogs without the plugin.
		if ( ! LangInterface::is_unbabble_active() ) {
			return false;
		}

		// Stop the language filter via a query_var.
		if ( ! ( $query->query_vars['ubb_lang_filter'] ?? true ) ) {
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
