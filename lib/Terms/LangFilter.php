<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Query;
use WP_Term_Query;

/**
 * TODO:
 *
 * @since 0.0.0
 */
class LangFilter {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_filter( 'terms_clauses', [ $this, 'filter_terms_by_language' ], 10, 3 );
	}

	public function filter_terms_by_language( array $pieces, array $taxonomies, array $args ) : array {

		// Divide $taxonomies into taxonomies with and without language.
		$allowed_taxonomies = Options::get_allowed_taxonomies();
		$taxonomies_w_lang  = [];
		$taxonomies_wo_lang = [];
		foreach ( $taxonomies as $taxonomy ) {
			// TODO: maybe use intersect.
			if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
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
