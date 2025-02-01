<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Overrides\WP_Posts_List_Table;
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
	 * @since 0.4.5 Add views-edit-{$post_type} filter for all translatable post types.
	 * @since 0.4.2 Remove filter in $_GET when not in admin. Only add filter hook when $_GET filter is set. Stop post lang filter from being applied.
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! \is_admin() ) {

			// Make sure the filter is not applied outside of admin.
			unset( $_GET['ubb_empty_lang_filter'] );
			return;
		}

		$post_types = LangInterface::get_translatable_post_types();
		foreach ( $post_types as $post_type ) {
			\add_filter( "views_edit-{$post_type}", [ $this, 'add_no_lang_filter' ], 10 );
		}

		// Only add this filter if the post filter is set in $_GET
		if ( isset( $_GET['ubb_empty_lang_filter'] ) ) {
			\add_filter( 'posts_where', [ $this, 'filter_posts_without_language' ], 10, 2 );
			\add_filter( 'ubb_use_post_lang_filter', '__return_false' );
			\add_action( 'wp_list_table_class_name', [ $this, 'override_wp_list_table_class' ], 10, 2 );
		}
	}

	/**
	 * Adds a filter to show posts without language.
	 *
	 * @since 0.4.5 Get post type via $_GET instead of `get_post`.
	 * @since 0.4.2 Fixed the query not considering posts with unknown language.
	 * @since 0.4.0
	 *
	 * @param array $views
	 * @return array
	 */
	public function add_no_lang_filter( array $views ) : array {
		global $wpdb;
		$post_type = $_GET['post_type'] ?? 'post';
		if (
			empty( $post_type )
			|| ! is_string( $post_type )
			|| ! LangInterface::is_post_type_translatable( $post_type )
		) {
			return $views;
		}

		$post_lang_table   = ( new PostTable() )->get_table_name();
		$allowed_languages = implode( "','", LangInterface::get_languages() );
		if ( empty( $allowed_languages ) ) {
			return $views;
		}

		$posts_count = $wpdb->get_results(
			"SELECT COUNT( * ) as count
				FROM {$wpdb->posts} AS P
				LEFT JOIN {$post_lang_table} AS UBB ON (P.ID = UBB.post_id)
				WHERE P.post_type = '{$post_type}'
				AND ( UBB.locale IS NULL
					OR UBB.locale NOT IN ('{$allowed_languages}'))",
			ARRAY_A
		);

		$invalid_count = $posts_count[0]['count'] ?? 0;

		$url = \add_query_arg( 'ubb_empty_lang_filter', '', 'edit.php' );
		if ( $post_type !== 'post' ) {
			$url = \add_query_arg( 'post_type', $post_type, $url );
		}

		$selected = isset( $_GET['ubb_empty_lang_filter'] );

		$views['ubb_empty_lang_filter'] = sprintf(
			'<a %s href="%s">%s</a>(%s)',
			$selected ? 'class="current"' : '',
			esc_url( $url ),
			esc_html__( 'No Language', 'unbabble' ),
			(int) $invalid_count
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

		$post_lang_table   = ( new PostTable() )->get_table_name();
		$allowed_languages = implode( "','", LangInterface::get_languages() );
		if ( empty( $allowed_languages ) ) {
			return $where;
		}

		$where .= sprintf(
			" AND (
				{$wpdb->posts}.ID NOT IN (
					SELECT post_id
					FROM {$post_lang_table} AS PT
					WHERE locale IN ('{$allowed_languages}')
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

	/**
	 * Overrides the WP_Posts_List_Table class to add a hidden input for the language filter.
	 *
	 * Necessary for keeping the language filter on when the search input is used.
	 *
	 * @since 0.5.0
	 *
	 * @param string $class_name
	 * @param array $args
	 * @return string
	 */
	public function override_wp_list_table_class( string $class_name, array $args ) : string {
		if ( 'WP_Posts_List_Table' !== $class_name ) {
			return $class_name;
		}

		if ( ! $args['screen'] instanceof \WP_Screen ) {
			return $class_name;
		}

		if ( $args['screen']->base !== 'edit' ) {
			return $class_name;
		}

		$post_types = LangInterface::get_translatable_post_types();
		if ( ! in_array( $args['screen']->post_type, $post_types, true ) ) {
			return $class_name;
		}

		return WP_Posts_List_Table::class;
	}
}
