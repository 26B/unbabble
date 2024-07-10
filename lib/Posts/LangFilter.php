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

		$where .= sprintf(
			" AND (
				{$wpdb->posts}.ID IN (
					%s
				)
			)",
			$this->get_lang_filter_where_query()
		);

		return $where;
	}

	public function get_lang_filter_where_query() : string {
		global $wpdb;

		// TODO: Deal with untranslatable post types.
		$current_lang            = \esc_sql( LangInterface::get_current_language() );
		$post_lang_table         = ( new PostTable() )->get_table_name();
		$translatable_post_types = implode( "','", LangInterface::get_translatable_post_types() );

		return "SELECT post_id
			FROM {$post_lang_table} AS PT
			WHERE locale = '{$current_lang}'
			UNION
			SELECT ID
			FROM {$wpdb->posts}
			WHERE post_type NOT IN ('{$translatable_post_types}')";
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
