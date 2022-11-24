<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Custom term translations table.
 *
 * @since 0.0.1
 */
class TermTable extends Table {

	/**
	 * Suffix for the term translations table name.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	protected function get_table_suffix() : string {
		return 'ubb_term_translations';
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
			term_id bigint(20) unsigned NOT NULL,
			locale varchar(5) NOT NULL,
			PRIMARY KEY (term_id),
			FOREIGN KEY (term_id) REFERENCES {$wpdb->terms} (term_id) ON DELETE CASCADE
		) $charset_collate;";
	}
}
