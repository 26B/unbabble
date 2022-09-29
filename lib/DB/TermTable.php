<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Custom Term Table handler.
 *
 * @since 0.0.0
 */
class TermTable extends Table {

	protected function get_table_suffix() : string {
		return 'ubb_term_translations';
	}

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
