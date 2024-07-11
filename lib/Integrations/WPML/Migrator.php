<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations\WPML;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_CLI;

/**
 * For hooks related to migration from wpml.
 *
 * @since 0.0.3
 */
class Migrator {

	/**
	 * Register commands.
	 *
	 * @since 0.0.3
	 */
	public function register() : void {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::add_command( 'ubb migrate-wpml', [ $this, 'run' ] );
		}
	}

	/**
	 * Run WPML to Unbabble migration.
	 *
	 * Will continue on previously done migrations. If the previous migration was fully completed
	 * and nothing new was added, no further migrations will be done.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<batch_size>]
	 * : (Optional) Number of WPML translation groups to migrate.
	 *
	 * [--fresh]
	 * : (Optional) Run a new migration.
	 *
	 * [--yes]
	 * : (Optional) Skip confirmations.
	 *
	 * @since 0.0.3
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function run( array $args, array $assoc_args ) : void {

		// Don't hide any language during migration.
		\add_filter( 'ubb_do_hidden_languages_filter', '__return_false' );

		if ( ! $this->check_for_wpml_tables() ) {
			WP_CLI::error( 'No WPML tables for migration.' );
		}

		$fresh = isset( $assoc_args['fresh'] );

		$process_pid = $this->get_migration_pid();
		if ( $process_pid ) {
			if ( $fresh ) {
				WP_CLI::confirm( 'Kill migrator process already running?' );
				posix_kill( $process_pid, SIGKILL );
			} else {
				WP_CLI::error( 'Migrator already running.' );
			}
		}

		if ( $fresh ) {
			update_option( 'ubb_wpml_migrate_offset', 0 );
		}

		$options = [];
		if ( isset( $assoc_args['batch'] ) && is_numeric( $assoc_args['batch'] ) ) {
			$options['batch'] = $assoc_args['batch'];
		}

		$offset = $this->get_migration_offset();
		if ( $offset ) {
			WP_CLI::warning( 'Continuing previous migration. Use `wp ubb migrate-wpml restart` to start a new one.' );
			$options['trid_offset'] = $offset;
		}

		$sql = $this->make_sql( $options );

		WP_CLI::confirm( 'Run migration?', $assoc_args );

		$trids_migrated = $this->migrate( $sql );
		if ( $trids_migrated === 0 ) {
			WP_CLI::success( 'Nothing left to migrate.' );
			return;
		}

		WP_CLI::success( "Migrated {$trids_migrated} translation groups." );
	}

	/**
	 * Returns migration process id if it's running.
	 *
	 * @since 0.0.3
	 *
	 * @return int PID if process running, otherwise 0.
	 */
	private function get_migration_pid() : int {
		// TODO: How to check if shell_exec is permitted.
		// FIXME: Only works for *nix systems.
		$grep = shell_exec( 'ps aux | grep "ubb migrate-wpml run\|ubb migrate-wpml restart" | grep -v grep | awk \'{print $2}\'' );
		$rows = explode( "\n", $grep );
		if ( count( $rows ) < 1 ) {
			return 0;
		}

		$processes = [];
		$currpid   = getmypid();
		foreach ( $rows as $pid ) {
			if ( empty( $pid ) || $currpid === (int) $pid ) {
				continue;
			}
			$processes[] = $pid;
		}

		return current( $processes );
	}

	/**
	 * Returns current migration's offset.
	 *
	 * @since 0.0.3
	 *
	 * @return int
	 */
	private function get_migration_offset() : int {
		return (int) get_option( 'ubb_wpml_migrate_offset', 0 );
	}

	/**
	 * Returns sql query for fetching WPML's translation groups.
	 *
	 * @since 0.0.3
	 *
	 * @param array $options
	 * @return string
	 */
	private function make_sql( array $options ) : string {
		global $wpdb;
		$limit = '';
		if ( isset( $options['batch'] ) ) {
			$limit = sprintf( 'LIMIT %d', esc_sql( $options['batch'] ) );
		}

		$query = $wpdb->prepare(
			"SELECT DISTINCT trid
			FROM (
				SELECT trid
				FROM {$wpdb->prefix}icl_translations as icl
				INNER JOIN {$wpdb->prefix}posts as P ON (icl.element_id = P.ID)
				WHERE trid > %s AND element_type LIKE 'post_%'
				UNION
				SELECT trid
				FROM {$wpdb->prefix}icl_translations as icl
				INNER JOIN {$wpdb->prefix}terms as T ON (icl.element_id = T.term_id)
				WHERE trid > %s AND element_type LIKE 'tax_%'
			) as A
			ORDER BY trid ASC
			{$limit}",
			$options['trid_offset'] ?? 0,
			$options['trid_offset'] ?? 0,
		);
		return $query;
	}

	/**
	 * Migrates translation groups from WPML to Unbabble.
	 *
	 * @since 0.0.3
	 *
	 * @param string $sql
	 * @return int
	 */
	private function migrate( string $sql ) : int {
		global $wpdb;
		$data = $wpdb->get_col( $sql );
		if ( empty( $data ) ) {
			// TODO: set process as finished.
			return 0;
		}

		$count = count( $data );
		foreach ( $data as $i => $trid ) {
			printf( "\r%s(%1.02f%%)", $i, ( 100 * $i/$count ) );

			$group = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT element_type as type, element_id as object_id, language_code as lang
					FROM {$wpdb->prefix}icl_translations as icl
					INNER JOIN {$wpdb->prefix}posts as P ON (icl.element_id = P.ID)
					WHERE trid = %s AND element_type LIKE 'post_%'
					UNION
					SELECT element_type as type, element_id as object_id, language_code as lang
					FROM {$wpdb->prefix}icl_translations as icl
					INNER JOIN {$wpdb->prefix}terms as T ON (icl.element_id = T.term_id)
					WHERE trid = %s AND element_type LIKE 'tax_%'",
					$trid,
					$trid
				),
				ARRAY_A
			);

			// Shouldn't happen.
			if ( empty( $group ) ) {
				update_option( 'ubb_wpml_migrate_offset', $trid );
				continue;
			}

			// Assuming all of the group is the same type.
			$type = $this->get_content_type( $group[0]['type'] );
			if ( empty( $type ) ) {
				update_option( 'ubb_wpml_migrate_offset', $trid );
				continue;
			}

			if ( $type === 'post' ) {
				$source_id = LangInterface::get_new_post_source_id();
			} else {
				$source_id = LangInterface::get_new_term_source_id();
			}

			foreach ( $group as $row ) {
				$lang = $this->get_locale( $row['lang'] );
				if ( empty( $lang ) ) {
					continue;
				}
				if ( $type === 'post' ) {
					// TODO: should we force it?
					LangInterface::set_post_language( $row['object_id'], $lang, true );
					LangInterface::set_post_source( $row['object_id'], $source_id, true );
				} else {
					LangInterface::set_term_language( $row['object_id'], $lang, true );
					LangInterface::set_term_source( $row['object_id'], $source_id, true );
				}
			}

			update_option( 'ubb_wpml_migrate_offset', $trid );
		}

		printf( "\r%s(%2.02f%%)\n", ++$i, ( 100 * $i/$count ) );

		return $i;
	}

	/**
	 * Returns content type, post or tax, from WPML's object_type.
	 *
	 * @since 0.0.3
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_content_type( string $type ) : string {
		$matches = [];
		if ( preg_match( '/(tax|post){1}\_.*/', $type, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Returns locale code from WPML language code.
	 *
	 * @since 0.0.3
	 *
	 * @param string $lang
	 * @return ?string
	 */
	private function get_locale( string $lang ) : ?string {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT default_locale
				FROM {$wpdb->prefix}icl_languages
				WHERE code = %s",
				$lang
			)
		);
	}

	/**
	 * Returns whether necessary WPML tables exist.
	 *
	 * @since 0.0.3
	 *
	 * @return bool
	 */
	private function check_for_wpml_tables() : bool {
		global $wpdb;
		$icl_tables = $wpdb->get_col( "SHOW TABLES LIKE '%icl%'" );
		if ( ! in_array( $wpdb->prefix . 'icl_translations', $icl_tables ) ) {
			return false;
		}
		if ( ! in_array( $wpdb->prefix . 'icl_languages', $icl_tables ) ) {
			return false;
		}
		return true;
	}
}
