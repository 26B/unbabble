<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Base Custom Table handler.
 *
 * @since 0.0.0
 */
abstract class Table {

	abstract protected function get_table_suffix() : string;
	abstract protected function get_table_mysql( string $table_name, string $charset_collate ) : string;

	public function get_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . $this->get_table_suffix();
	}

	public function create_table() : bool {
		global $wpdb;
		$table_name = $this->get_table_name();

		// Validate db exists.
		if ( ! $this->table_exists() ) {

			// Create Table.
			$charset_collate = $wpdb->get_charset_collate();

			// Be careful when changing this query.
			// See https://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table
			$sql = $this->get_table_mysql( $table_name, $charset_collate );

			"CREATE TABLE {$table_name} (
				post_id bigint(20) unsigned NOT NULL,
				locale varchar(5) NOT NULL,
				FOREIGN KEY (post_id) REFERENCES {$wpdb->posts} (ID) ON DELETE CASCADE
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			return true;
		}

		return false;
	}

	/**
	 * Delete Countries table if it exists.
	 *
	 * @since  0.0.0
	 * @return void
	 */
	public function delete_table() : void {
		global $wpdb;
		$table_name = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Check if Countries table exists in the BD.
	 *
	 * @since 0.0.0
	 *
	 * @return bool
	 */
	private function table_exists() : bool {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->get_table_name() ) )
		) !== null;
	}

}
