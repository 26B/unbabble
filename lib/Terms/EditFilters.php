<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Overrides\WP_Terms_List_Table;
use WP_Query;

/**
 * Hooks for filters for the list of terms in the backoffice.
 *
 * @since 0.4.2
 */
class EditFilters {

	static private $filter_added = false;

	// TODO: When a term is created when filtering for no/bad language, it will appear in the list when it shouldn't.

	/**
	 * Register hooks.
	 *
	 * @since 0.4.2
	 */
	public function register() {
		if ( ! \is_admin() ) {

			// Make sure the filter is not applied outside of admin.
			unset( $_GET['ubb_empty_lang_filter'] );
			return;
		}

		$taxonomies = LangInterface::get_translatable_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			\add_filter(
				"views_edit-{$taxonomy}",
				fn ( $views ) => $this->add_no_lang_filter( $views, $taxonomy ),
				PHP_INT_MAX // Delay so we can add the All filter if necessary.
			);
		}

		if ( isset( $_GET['ubb_empty_lang_filter'] ) ) {
			\add_action( 'pre_get_terms', [ $this, 'pre_get_terms' ], 10, 1 );
			\add_filter( 'terms_clauses', [ $this, 'filter_terms_without_language' ], 10, 3 );
			\add_filter( 'ubb_use_term_lang_filter', '__return_false' );
			\add_action( 'wp_list_table_class_name', [ $this, 'override_wp_list_table_class' ], 10, 2 );
		}
	}

	/**
	 * Adds a filter to show terms without language.
	 *
	 * @since 0.4.2
	 *
	 * @param array $views
	 * @param string $taxonomy
	 * @return array
	 */
	public function add_no_lang_filter( array $views, string $taxonomy ) : array {
		global $wpdb;
		if (
			empty( $taxonomy )
			|| ! is_string( $taxonomy )
			|| ! LangInterface::is_taxonomy_translatable( $taxonomy )
		) {
			return $views;
		}

		$term_lang_table   = ( new TermTable() )->get_table_name();
		$allowed_languages = implode( "','", LangInterface::get_languages() );
		if ( empty( $allowed_languages ) ) {
			return $views;
		}

		$terms_count = $wpdb->get_results(
			"SELECT IF(UBB.locale IN ('{$allowed_languages}'), 'VALID', 'INVALID') as good_locale, COUNT( * ) as count
				FROM {$wpdb->terms} AS T
				INNER JOIN {$wpdb->term_taxonomy} AS TT ON (T.term_id = TT.term_id)
				LEFT JOIN {$term_lang_table} AS UBB ON (T.term_id = UBB.term_id)
				WHERE TT.taxonomy = '{$taxonomy}'
				GROUP BY good_locale",
			OBJECT_K
		);

		$valid_count   = $terms_count['VALID']->count ?? 0;
		$invalid_count = $terms_count['INVALID']->count ?? 0;

		$url = 'edit-tags.php';
		if ( $taxonomy !== 'post_tag' ) {
			$url = \add_query_arg( 'taxonomy', $taxonomy, $url );
		}
		if ( ! empty( $_GET['post_type'] ?? '' ) ) {
			$url = \add_query_arg( 'post_type', $_GET['post_type'], $url );
		}

		$selected = isset( $_GET['ubb_empty_lang_filter'] );

		// Add an All view if there are no other views.
		if ( empty( $views ) ) {
			// Add an All filter because there is none by default.
			$views['ubb_all'] = sprintf(
				'<a %s href="%s">%s</a>(%s)',
				( ! $selected ) ? 'class="current"' : '',
				$url,
				esc_html__( 'All' ),
				(int) $valid_count
			);
		}

		$url = \add_query_arg( 'ubb_empty_lang_filter', '', $url );

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
	 * Tries to add an filter entry to main term query's query vars in order for the method
	 * `filter_terms_without_language` to be able to filter the terms for only the main query.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_Term_Query $query
	 * @return void
	 */
	public function pre_get_terms( \WP_Term_Query $query ) : void {
		if ( $this::$filter_added ) {
			return;
		}

		$taxonomy = $_GET['taxonomy'] ?? 'post_tag';
		if (
			! isset( $query->query_vars['taxonomy'] )
			|| ! is_array( $query->query_vars['taxonomy'] )
			|| count( $query->query_vars['taxonomy'] ) !== 1
			|| current( $query->query_vars['taxonomy'] ) !== $taxonomy
		) {
			return;
		}

		if ( ! in_array( $taxonomy, LangInterface::get_translatable_taxonomies(), true ) ) {
			return;
		}

		$query->query_vars['ubb_empty_lang_filter'] = true;

		// Make sure only the first query is filtered, since its the only to tell its the main query.
		$this::$filter_added = true;
	}

	/**
	 * Adds a filter to show terms without language.
	 *
	 * @since 0.4.2
	 *
	 * @param array $pieces
	 * @param array $taxonomies
	 * @param array $args
	 * @return array
	 */
	public function filter_terms_without_language( array $pieces, array $taxonomies, array $args ) : array {
		global $wpdb;

		if ( ! $this->allow_filter( $pieces, $taxonomies, $args ) ) {
			return $pieces;
		}

		$term_lang_table   = ( new TermTable() )->get_table_name();
		$allowed_languages = implode( "','", LangInterface::get_languages() );
		if ( empty( $allowed_languages ) ) {
			return $pieces;
		}

		$pieces['where'] .= sprintf(
			" AND (
				t.term_id NOT IN (
					SELECT term_id
					FROM {$term_lang_table} AS TT
					WHERE locale IN ('{$allowed_languages}')
				)
			)",
		);

		return $pieces;
	}

	/**
	 * Returns whether the filtering of term should happen.
	 *
	 * @since 0.4.2
	 *
	 * @param WP_Query $query
	 * @return bool
	 */
	private function allow_filter( array $pieces, array $taxonomies, array $args ) : bool {
		if ( ( $args['ubb_empty_lang_filter'] ?? false ) !== true ) {
			return false;
		}

		/**
		 * Filters whether terms should be filtered by having an unknown language or having no language.
		 *
		 * @since 0.4.2
		 *
		 * @param bool $apply_filter
		 * @param array $pieces
		 * @param array $taxonomies
		 * @param array $args
		 */
		return \apply_filters( 'ubb_use_term_lang_missing_filter', true, $pieces, $taxonomies, $args );
	}

	/**
	 * Overrides the WP_Terms_List_Table class to add a hidden input for the language filter.
	 *
	 * Necessary for keeping the language filter on when the search input is used.
	 *
	 * @since Unreleased
	 *
	 * @param string $class_name
	 * @param array $args
	 * @return string
	 */
	public function override_wp_list_table_class( string $class_name, array $args ) : string {
		if ( 'WP_Terms_List_Table' !== $class_name ) {
			return $class_name;
		}

		if ( ! $args['screen'] instanceof \WP_Screen ) {
			return $class_name;
		}

		if ( $args['screen']->base !== 'edit-tags' ) {
			return $class_name;
		}

		$taxonomies = LangInterface::get_translatable_taxonomies();
		if ( ! in_array( $args['screen']->taxonomy, $taxonomies, true ) ) {
			return $class_name;
		}

		return WP_Terms_List_Table::class;
	}
}
