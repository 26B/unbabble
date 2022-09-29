<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Custom Post Table handler.
 *
 * @since 0.0.0
 */
class PostTable extends Table {

	protected function get_table_suffix() : string {
		return 'ubb_post_translations';
	}

	protected function get_table_mysql( string $table_name, string $charset_collate ) : string {
		global $wpdb;
		return "CREATE TABLE {$table_name} (
			post_id bigint(20) unsigned NOT NULL,
			locale varchar(5) NOT NULL,
			PRIMARY KEY (post_id),
			FOREIGN KEY (post_id) REFERENCES {$wpdb->posts} (ID) ON DELETE CASCADE
		) $charset_collate;";
	}
}
