<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI\Hidden;

use TwentySixB\WP\Plugin\Unbabble\CLI\Command;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_CLI;

/**
 * CLI commands for hidden terms.
 *
 * @since 0.0.13
 */
class Term extends Command {

	/**
	 * Show statistics about terms with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing terms by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--taxonomies=<taxonomies>]
	 * : (Optional) Filter by taxonomy(ies). Multiple taxonomies should be separated by commas.
	 *
	 * @since 0.0.13
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function stats( array $args, array $assoc_args ) : void {
		$filter     = $assoc_args['filter'] ?? 'all';
		$taxonomies = $assoc_args['taxonomies'] ?? [];
		if ( ! empty( $taxonomies ) ) {
			$taxonomies = explode( ',', $taxonomies );
		}

		$hidden_terms = $this->get_hidden_terms( $filter, $taxonomies );
		if ( empty( $hidden_terms ) ) {
			WP_CLI::success( __( 'There are no terms missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$this->display_hidden_term_stats( $hidden_terms );
	}

	/**
	 * List terms with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing terms by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--taxonomies=<taxonomies>]
	 * : (Optional) Filter by taxonomy(ies). Multiple taxonomies should be separated by commas.
	 *
	 * [--limit=<limit>]
	 * : (Optional) Limit how many terms are listed. Must be bigger than 0. No limit by default.
	 *
	 * @since 0.0.13
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function list( array $args, array $assoc_args ) : void {
		$filter     = $assoc_args['filter'] ?? 'all';
		$taxonomies = $assoc_args['taxonomies'] ?? [];
		$limit      = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
		if ( ! empty( $taxonomies ) ) {
			$taxonomies = explode( ',', $taxonomies );
		}

		$hidden_terms = $this->get_hidden_terms( $filter, $taxonomies, $limit );

		if ( empty( $hidden_terms ) ) {
			WP_CLI::success( __( 'There are no terms missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$data = [];
		foreach ( $hidden_terms as $term ) {
			$language = LangInterface::get_term_language( $term->term_id );
			if ( $language === null ) {
				$data['missing'][ $term->taxonomy ][] = $term;
				continue;
			}

			$data['unknown'][ $language ][ $term->taxonomy ][] = $term;
		}

		if ( isset( $data['missing'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Taxonomy', 'unbabble' ) . '%N',
					'%g' . __( 'Term ID', 'unbabble' ) . '%N',
					'%g' . __( 'Name', 'unbabble' ) . '%N',
					'%g' . __( 'Edit URL', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['missing'] as $taxonomy => $terms ) {
				$taxonomy_object = get_taxonomy( $taxonomy );
				foreach ( $terms as $term ) {
					$link = '';
					if ( $taxonomy_object->show_ui ) {
						$args = [
							'taxonomy' => $taxonomy,
							'tag_ID'   => $term->term_id,
						];
						$link = add_query_arg( $args, admin_url( 'term.php' ) );
					}
					$lines[ $term->term_id ] = [
						$taxonomy,
						$term->term_id,
						$term->name,
						$link
					];
				}
			}

			self::log_color( '%4' . __( 'Terms missing language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}

		if ( isset( $data['unknown'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Language', 'unbabble' ) . '%N',
					'%g' . __( 'Taxonomy', 'unbabble' ) . '%N',
					'%g' . __( 'Term ID', 'unbabble' ) . '%N',
					'%g' . __( 'Name', 'unbabble' ) . '%N',
					'%g' . __( 'Edit URL', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['unknown'] as $language => $taxonomy ) {
				foreach ( $taxonomy as $taxonomy => $terms ) {
					$taxonomy_object = get_taxonomy( $taxonomy );
					foreach ( $terms as $term ) {
						$link = '';
						if ( $taxonomy_object->show_ui ) {
							$args = [
								'taxonomy' => $taxonomy,
								'tag_ID'   => $term->term_id,
							];
							$link = add_query_arg( $args, admin_url( 'term.php' ) );
						}

						$lines[ $term->term_id ] = [
							$language,
							$taxonomy,
							$term->term_id,
							$term->name,
							$link
						];
					}
				}
			}

			self::log_color( '%4' . __( 'Terms with unknown language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}
	}

	/**
	 * Fix terms with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * <language_code>
	 * : Language code to set the terms to.
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing terms by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--taxonomies=<taxonomies>]
	 * : (Optional) Filter by taxonomy(ies). Multiple taxonomies should be separated by commas.
	 *
	 * [--limit=<limit>]
	 * : (Optional) Limit how many terms are fixed. Must be bigger than 0. No limit by default.
	 *
	 * @since 0.0.13
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function fix( array $args, array $assoc_args ) : void {
		$language = $args[0];
		if ( ! LangInterface::is_language_allowed( $language ) ) {
			WP_CLI::error( __( 'Unknown language.', 'unbabble' ) );
		}

		$filter     = $assoc_args['filter'] ?? 'all';
		$taxonomies = $assoc_args['taxonomies'] ?? [];
		$limit      = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
		if ( ! empty( $taxonomies ) ) {
			$taxonomies = explode( ',', $taxonomies );
		}

		$hidden_terms = $this->get_hidden_terms( $filter, $taxonomies, $limit );
		if ( empty( $hidden_terms ) ) {
			WP_CLI::success( __( 'There are no terms missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$this->display_hidden_term_stats( $hidden_terms );

		$this->confirm_color(
			sprintf(
				/* translators: 1: Language code */
				__( 'Are you sure you want to set the language %1$s for the terms displayed above?', 'unbabble' ),
				'%g'. $language . '%N'
			)
		);

		$successful         = 0;
		$success_taxonomies = [];
		$fail_taxonomies    = [];
		foreach ( $hidden_terms as $term ) {
			$success = LangInterface::set_term_language( $term->term_id, $language, true );
			if ( ! $success ) {
				$fail_taxonomies[ $term->taxonomy ] = 1 + ( $fail_taxonomies[ $term->taxonomy ] ?? 0 );
				/* translators: 1: Term ID, 2: Taxonomy */
				WP_CLI::warning( sprintf( __( 'Failed for term %1$s (%2$s).', 'unbabble' ), $term->term_id, $term->taxonomy ) );
				continue;
			}
			$success_taxonomies[ $term->taxonomy ] = 1 + ( $success_taxonomies[ $term->taxonomy ] ?? 0 );
			$successful += 1;
		}

		/* translators: 1: Amount of successfully updated terms, 2: Language code */
		WP_CLI::success( sprintf( __( 'Updated %1$s terms with the language %2$s.', 'unbabble' ), $successful, $language ) );
		$lines = [
			'header' => [
				'%g' . __( 'Taxonomy', 'unbabble' ) . '%N',
				'%g' . __( 'Count', 'unbabble' ) . '%N',
			],
		];
		if ( ! empty( $success_taxonomies ) ) {
			$success_lines = $lines;
			foreach ( $success_taxonomies as $taxonomy => $count ) {
				$success_lines[ $taxonomy ] = [ $taxonomy, $count ];
			}
			self::log_color( '%4' . __( 'Successful updates', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $success_lines, 2 );
		}
		if ( ! empty( $fail_taxonomies ) ) {
			$fail_lines = $lines;
			foreach ( $fail_taxonomies as $taxonomy => $count ) {
				$fail_lines[ $taxonomy ] = [ $taxonomy, $count ];
			}
			self::log_color( '%4' . __( 'Failed updates', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $fail_lines, 2 );
		}
	}

	/**
	 * Returns array of hidden terms.
	 *
	 * @since 0.0.13
	 *
	 * @param string $focus
	 * @param array  $taxonomies
	 * @param ?int   $limit
	 * @return array
	 */
	private function get_hidden_terms( string $focus = 'all', array $taxonomies = [], ?int $limit = null ) : array {
		global $wpdb;
		\add_filter( 'ubb_do_hidden_languages_filter', '__return_false' );

		$allowed_languages       = implode( "','", LangInterface::get_languages() );
		$translatable_taxonomies = LangInterface::get_translatable_taxonomies();
		$translations_table      = ( new TermTable() )->get_table_name();

		$taxonomies = empty( $taxonomies ) ? $translatable_taxonomies : $taxonomies;
		if ( is_string( $taxonomies ) ) {
			$taxonomies = array_filter(
				explode( ',', $taxonomies ),
				function ( $taxonomy ) use ( $translatable_taxonomies ) {
					if ( ! is_string( $taxonomy ) ) {
						return false;
					}
					if ( ! \taxonomy_exists( $taxonomy ) ) {
						return false;
					}
					return in_array( $taxonomy, $translatable_taxonomies, true );
				}
			);
		}
		$taxonomies = implode( "','", $taxonomies );

		if ( $focus === 'missing' ) {
			$where_focus = "T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as TT
			)";

		} else if ( $focus === 'unknown' ) {
			$where_focus = "T.term_id IN (
				SELECT term_id
				FROM {$translations_table} as TT
				WHERE TT.locale NOT IN ('{$allowed_languages}')
			)";

		} else if ( $focus === 'all' ) {
			$where_focus = $where_focus = "T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as TT
				WHERE TT.locale IN ('{$allowed_languages}')
			)";

		} else {
			WP_CLI::error( "Unknown focus argument. Accepts: 'all', 'missing' and 'unknown'." );
		}

		$limit_str = '';
		if ( is_int( $limit ) && $limit > 0 ) {
			$limit_str = " LIMIT {$limit}";
		}

		return $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->terms} as T
			INNER JOIN {$wpdb->term_taxonomy} as TermTax ON (T.term_id = TermTax.term_id)
			WHERE TermTax.taxonomy IN ('{$taxonomies}')
			AND {$where_focus}
			{$limit_str}",
			OBJECT
		);
	}

	/**
	 * Displays statistics of the hidden terms.
	 *
	 * @since 0.0.13
	 *
	 * @param array $hidden_terms
	 * @return void
	 */
	private function display_hidden_term_stats( array $hidden_terms ) : void {
		$data = [];
		foreach ( $hidden_terms as $term ) {
			$language = LangInterface::get_term_language( $term->term_id );
			if ( $language === null ) {
				$data['missing'][ $term->taxonomy ][] = $term->term_id;
				continue;
			}

			$data['unknown'][ $language ][ $term->taxonomy ][] = $term->term_id;
		}

		if ( isset( $data['missing'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Taxonomy', 'unbabble' ) . '%N',
					'%g' . __( 'Count', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['missing'] as $taxonomy => $ids ) {
				$lines[ $taxonomy ] = [
					$taxonomy,
					count( $ids )
				];
			}

			self::log_color( '%4' . __( 'Terms missing language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}

		if ( isset( $data['unknown'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Language', 'unbabble' ) . '%N',
					'%g' . __( 'Taxonomy', 'unbabble' ) . '%N',
					'%g' . __( 'Count', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['unknown'] as $language => $taxonomies ) {
				foreach ( $taxonomies as $taxonomy => $ids ) {
					$lines[ $language . '-' . $taxonomy ] = [
						$language,
						$taxonomy,
						count( $ids )
					];
				}
			}

			self::log_color( '%4' . __( 'Terms with unknown language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}
	}
}
