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
	 * @since 0.7.0 - Fix term delete bug via ajax when term has no language.
	 * @since 0.7.0 - Added handling of `set_object_terms` action to clear terms of a different language or without language when object terms are set without append.
	 * @since 0.0.1
	 */
	public function register() {
		\add_filter( 'terms_clauses', [ $this, 'filter_terms_by_language' ], 10, 3 );

		// Handle deleting terms without language.
		\add_filter( 'term_exists_default_query_args', [ $this, 'ajax_delete_term_exists' ], PHP_INT_MAX, 4 );

		// When object terms are set without append, clear all attached terms of a different language or without language.
		\add_action( 'set_object_terms', [ $this, 'clear_object_terms_of_different_language' ], 10, 5 );
	}

	/**
	 * Handle the case where a term is being deleted via ajax and it has no language.
	 * In this case, we want to ignore the language filter due to a term_exists call that is
	 * made during the deletion process.
	 *
	 * @since 0.7.0
	 *
	 * @param array $query_args The query args for term_exists.
	 * @param mixed $term The term being checked.
	 * @param string $taxonomy The taxonomy of the term.
	 * @param int|null $parent The parent term ID, if any.
	 * @return array The modified query args.
	 */
	public function ajax_delete_term_exists( $query_args, $term, $taxonomy, $parent ) : array {
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

	/**
	 * Clears all terms of a different language or without language when object terms are set without append.
	 *
	 * We need this because the fetch of terms in wp_set_object_terms() has the lang filter applied,
	 * so only the terms of the current language are fetched and deleted when append is false.
	 *
	 * @since 0.7.0
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      An array of object term IDs or slugs.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 */
	public function clear_object_terms_of_different_language( $object_id, $terms, $tt_ids, $taxonomy, $append ) : void {
		// Ignore any append operations.
		if ( $append ) {
			return;
		}

		// If the taxonomy is not translatable, we don't need to filter.
		if ( ! LangInterface::is_taxonomy_translatable( $taxonomy ) ) {
			return;
		}

		$post_lang = LangInterface::get_post_language( $object_id );

		// If the post doesn't have a language, we don't need to filter.
		if ( $post_lang === null ) {
			return;
		}

		/**
		 * We use $terms for the ids instead of $tt_ids because if the wp_set_object_terms was
		 * called with a term id that is not of the current language, it won't be in the $tt_ids
		 *  array, but it will be in the $terms array, and we want to keep it.
		 */
		$term_ids = array_filter( array_map( fn( $term ) => is_numeric( $term ) ? (int) $term : null, $terms ) );

		/**
		 * If the number of term IDs don't match the number of terms, we skip it since some
		 * might be slugs and we want to avoid dealing with those.
		 */
		if ( count( $term_ids ) !== count( $terms ) ) {
			return;
		}

		global $wpdb;

		$term_lang_table = ( new TermTable() )->get_table_name();
		$term_ids_str    = implode( ',', array_map( 'intval', $term_ids ) );
		$where_term_ids  = ! empty( $term_ids ) ? "AND TT.term_id NOT IN ({$term_ids_str})" : '';

		// Delete all old terms of a different language or without language.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE TR FROM {$wpdb->term_relationships} AS TR
					INNER JOIN {$wpdb->term_taxonomy} AS TT ON TR.term_taxonomy_id = TT.term_taxonomy_id
					WHERE TR.object_id = %d
					AND TT.taxonomy = %s
					{$where_term_ids}
					AND TT.term_id NOT IN (
						SELECT term_id
						FROM {$term_lang_table}
						WHERE locale = %s
					)",
				$object_id,
				$taxonomy,
				$post_lang
			)
		);

		if ( $deleted === false ) {
			\error_log( "Failed to delete terms of a different language or without language for object ID {$object_id} and taxonomy {$taxonomy} when wp_set_object_terms is applied with :append = false." );
		}

		return;
	}
}
