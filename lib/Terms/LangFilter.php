<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks for filtering terms based on their language.
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
		\add_filter( 'terms_clauses', [ $this, 'filter_terms_by_language' ], 10, 3 );
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
