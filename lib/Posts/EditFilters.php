<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use stdClass;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Query;

/**
 * Hooks for filters for the list of posts in the backoffice.
 *
 * @since 0.0.1
 */
class EditFilters {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_filter( 'views_edit-post', [ $this, 'add_no_lang_filter' ], 10 );
		\add_filter( 'posts_where', [ $this, 'filter_posts_without_language' ], 10, 2 );
	}

	public function add_no_lang_filter( array $views ) : array {
		global $wpdb;
		$post_type = \get_post_type();
		if (
			empty( $post_type )
			|| ! is_string( $post_type )
			|| ! LangInterface::is_post_type_translatable( $post_type )
		) {
			return $views;
		}

		$post_lang_table        = ( new PostTable() )->get_table_name();
		$posts_without_language = $wpdb->get_var(
			"SELECT COUNT( * )
			FROM {$wpdb->posts}
			WHERE post_type = '{$post_type}'
			AND ID NOT IN ( SELECT post_id FROM {$post_lang_table} )"
		);

		$url       = \add_query_arg( 'ubb_empty_lang_filter', '', 'edit.php' );
		$post_type = \get_post_type();
		if ( $post_type !== 'post' ) {
			$url = \add_query_arg( 'post_type', $post_type, $url );
		}

		$selected = isset( $_GET['ubb_empty_lang_filter'] );

		$views['ubb_empty_lang_filter'] = sprintf(
			'<a %s href="%s">%s</a>(%s)',
			$selected ? 'class="current"' : '',
			esc_url( $url ),
			esc_html__( 'No Language', 'unbabble' ),
			(int) $posts_without_language
		);

		return $views;
	}

	/**
	 */
	public function filter_posts_without_language( string $where, \WP_Query $query ) : string {
		global $wpdb;

		if ( ! $this->allow_filter( $query ) ) {
			return $where;
		}

		if ( ! isset( $_GET['ubb_empty_lang_filter'] ) ) {
			return $where;
		}

		$post_lang_table = ( new PostTable() )->get_table_name();

		$where .= sprintf(
			" AND (
				{$wpdb->posts}.ID NOT IN (
					SELECT post_id
					FROM {$post_lang_table} AS PT
				)
			)",
		);

		return $where;
	}

	/**
	 * Returns whether the filtering of posts should happen.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Query $query
	 * @return bool
	 */
	private function allow_filter( \WP_Query $query ) : bool {
		if ( ! $query->is_main_query() ) {
			return false;
		}

		$post_type = $query->get( 'post_type', 'post' );

		if ( is_array( $post_type ) ) {
			if ( empty( $post_type ) ) {
				$post_type = '';
			} else if ( is_string( current( $post_type ) ) ) {
				$post_type = current( $post_type );
			} else {
				return false;
			}
		}

		if ( ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return false;
		}

		// Don't apply filters on switch_to_blog to blogs without the plugin.
		if ( ! LangInterface::is_unbabble_active() ) {
			return false;
		}

		/**
		 * Filters whether posts should be filtered by having an unknown language or having no language.
		 *
		 * @since 0.4.0
		 *
		 * @param bool $apply_filter
		 * @param WP_Query $query
		 */
		return \apply_filters( 'ubb_use_post_lang_missing_filter', true, $query );
	}
}
