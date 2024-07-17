<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * For hooks related to the SearchWP plugin.
 *
 * @since Unreleased
 */
class SearchWP {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'searchwp\query', [ $this, 'searchwp_query' ], 10, 2 );
	}

	/**
	 * Filters the SearchWP query to include the language filter.
	 *
	 * @since Unreleased
	 *
	 * @param array $query SearchWP query.
	 * @param array $args  SearchWP arguments.
	 * @return array
	 */
	public function searchwp_query( array $query, array $args ) : array {
		$lang                    = LangInterface::get_current_language();
		$posts_table             = ( new PostTable() )->get_table_name();
		$translatable_post_types = implode( "','", LangInterface::get_translatable_post_types() );

		$query['join'][]  = "LEFT JOIN {$posts_table} ubb ON ubb.post_id = s.id";
		$query['where'][] = "(
			( ubb.locale IS NULL AND s1.post_type NOT IN ('{$translatable_post_types}') )
			OR
			( ubb.locale = '{$lang}' AND s1.post_type IN ('{$translatable_post_types}') )
		)";

		return $query;
	}
}
