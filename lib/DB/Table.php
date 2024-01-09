<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

/**
 * Base Custom Table creator.
 *
 * @since 0.0.1
 */
abstract class Table {

	/**
	 * Suffix for the table name.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	abstract protected function get_table_suffix() : string;

	/**
	 * Returns the create table mysql.
	 *
	 * @since 0.0.1
	 *
	 * @param string $table_man
	 * @param string $charset_collate
	 * @return string
	 */
	abstract protected function get_table_mysql( string $table_name, string $charset_collate ) : string;

	/**
	 * Get the full table name.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_table_name() : string {
		global $wpdb;
		return $wpdb->prefix . $this->get_table_suffix();
	}

	/**
	 * Create the table.
	 *
	 * @since 0.0.1
	 *
	 * TODO: We should check if table exists on every plugin load.
	 *
	 * @return bool Success for creating the table. False if the table already exists.
	 */
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

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			return true;
		}

		return false;
	}

	/**
	 * Delete table if it exists.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function delete_table() : void {
		global $wpdb;
		$table_name = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Check if table exists in the BD.
	 *
	 * @since 0.0.1
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
