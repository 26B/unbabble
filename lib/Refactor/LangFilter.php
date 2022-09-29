<?php

namespace TwentySixB\WP\Plugin\Unbabble\Refactor;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Query;

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
		\add_filter( 'posts_where', [ $this, 'filter_posts_by_language' ], 10, 2 );
	}

	public function filter_posts_by_language( string $where, WP_Query $query ) : string {
		global $wpdb;
		$current_lang    = esc_sql( LangInterface::get_current_language() );
		$post_lang_table = ( new PostTable() )->get_table_name();
		$where .= " AND ({$wpdb->posts}.ID IN ( SELECT post_id FROM {$post_lang_table} WHERE locale = '$current_lang' ))";
		return $where;
	}
}
