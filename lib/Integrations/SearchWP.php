<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;

class SearchWP {
	public function register() {
		add_filter( 'searchwp\query', [ $this, 'searchwp_query' ], 10, 2 );
	}

	public function searchwp_query( array $query, array $args ) : array {
		$lang        = LangInterface::get_current_language();
		$posts_table = ( new PostTable() )->get_table_name();

		$query['join'][] = "LEFT JOIN {$posts_table} ubb ON ubb.post_id = s.id";

		// TODO: Add parts for translatable and non translatable post types
		$query['where'][] = "ubb.locale = '{$lang}'";

		return $query;
	}
}
