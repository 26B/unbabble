<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Custom post translations table.
 *
 * @since 0.0.1
 */
class PostTable extends Table {

	/**
	 * Suffix for the post translations table name.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	protected function get_table_suffix() : string {
		return 'ubb_post_translations';
	}

	/**
	 * Returns the create table mysql.
	 *
	 * @since 0.0.1
	 *
	 * @param string $table_man
	 * @param string $charset_collate
	 * @return string
	 */
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
