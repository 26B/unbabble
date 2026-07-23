<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * Hooks for filtering terms based on their language.
 *
 * @since 0.0.1
 */
class LangFilter {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased - Fix term delete bug via ajax when term has no language.
	 * @since 0.0.1
	 */
	public function register() {
		\add_filter( 'terms_clauses', [ $this, 'filter_terms_by_language' ], 10, 3 );

		// Handle deleting terms without language.
		\add_filter( 'term_exists_default_query_args', [ $this, 'ajax_delete_term_exists' ], PHP_INT_MAX, 4 );
	}

	/**
	 * Handle the case where a term is being deleted via ajax and it has no language.
	 * In this case, we want to ignore the language filter due to a term_exists call that is
	 * made during the deletion process.
	 *
	 * FIXME: this is too specific for ajax, how do we handle term delete in general?
	 *
	 * @since Unreleased
	 *
	 * @param array $query_args The query args for term_exists.
	 * @param mixed $term The term being checked.
	 * @param string $taxonomy The taxonomy of the term.
	 * @param int|null $parent The parent term ID, if any.
	 * @return array The modified query args.
	 */
	public function ajax_delete_term_term_exists( $query_args, $term, $taxonomy, $parent ) : array {
		// Check if we are in the ajax delete term action.
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! isset( $_POST['action'] ) || 'delete-tag' !== $_POST['action'] ) {
			return $query_args;
		}

		if ( ! isset( $_POST['tag_ID'] ) ) {
			return $query_args;
		}

		$tag_id = (int) $_POST['tag_ID'];
		if ( $tag_id !== $term || ! is_int( $term ) ) {
			return $query_args;
		}

		check_ajax_referer( "delete-tag_$tag_id" );

		// If term has language, we ignore it.
		$term_lang = LangInterface::get_term_language( $tag_id );
		if ( $term_lang ) {
			return $query_args;
		}

		// TODO: use run_once from 26/wp-framework.
		add_filter(
			'ubb_use_term_lang_filter',
			function( $apply_filter, $pieces, $taxonomies, $args ) use ( $tag_id, $query_args, $taxonomy ) {
				if ( $taxonomies != [ $taxonomy ] ) {
					return $apply_filter;
				}
				// Check taxonomy in $args.
				if ( ! isset( $args['taxonomy'] ) || $args['taxonomy'] != [ $taxonomy ] ) {
					return $apply_filter;
				}
				// Check number is 1.
				if ( ! isset( $args['number'] ) || $args['number'] != 1 ) {
					return $apply_filter;
				}
				// Check include is the tag_id.
				if ( ! isset( $args['include'] ) || $args['include'] != [ $tag_id ] ) {
					return $apply_filter;
				}
				return false;
			},
			PHP_INT_MAX, 4
		);

		return $query_args;
	}

	/**
	 * Adds where clauses to query in order to filters terms by language, if necessary.
	 *
	 * @since 0.0.1
	 *
	 * @param array $pieces
	 * @param array $taxonomies
	 * @param array $args
	 * @return array
	 */
	public function filter_terms_by_language( array $pieces, array $taxonomies, array $args ) : array {

		// Don't apply filters on switch_to_blog to blogs without the plugin.
		if ( ! LangInterface::is_unbabble_active() ) {
			return $pieces;
		}

		/**
		 * Filters whether terms should be filtered by their language.
		 *
		 * @since 0.0.1
		 *
		 * @param bool  $apply_filter
		 * @param array $pieces
		 * @param array $taxonomies
		 * @param array $args
		 */
		if ( ! \apply_filters( 'ubb_use_term_lang_filter', true, $pieces, $taxonomies, $args ) ) {
			return $pieces;
		}

		// Divide $taxonomies into taxonomies with and without language.
		$taxonomies_w_lang  = [];
		$taxonomies_wo_lang = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! LangInterface::is_taxonomy_translatable( $taxonomy ) ) {
				$taxonomies_wo_lang[] = esc_sql( $taxonomy );
				continue;
			}
			$taxonomies_w_lang[] = $taxonomy;
		}

		// If there are no taxonomies with language, we don't need to filter.
		if ( count( $taxonomies_w_lang ) === 0 ) {
			return $pieces;
		}

		$current_lang     = esc_sql( LangInterface::get_current_language() );
		$term_lang_table  = ( new TermTable() )->get_table_name();

		// If the taxonomies with language are the same as $taxonomies, we put a simple language filter.
		if ( count( $taxonomies_w_lang ) === count( $taxonomies ) ) {
			$pieces['where'] .= " AND ( t.term_id IN ( SELECT term_id FROM {$term_lang_table} WHERE locale = '$current_lang' ))";
			return $pieces;
		}

		// Otherwise we need to only filter for the taxonomies with language.
		$taxonomies_str = implode( "','", $taxonomies_wo_lang );
		$pieces['where'] .= " AND ( tt.taxonomy IN ('{$taxonomies_str}') OR t.term_id IN ( SELECT term_id FROM {$term_lang_table} WHERE locale = '$current_lang' ) )";
		return $pieces;
	}
}
