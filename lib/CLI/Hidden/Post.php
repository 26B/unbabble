<?php

namespace TwentySixB\WP\Plugin\Unbabble\CLI\Hidden;

use TwentySixB\WP\Plugin\Unbabble\Cache\Keys;
use TwentySixB\WP\Plugin\Unbabble\CLI\Command;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_CLI;

/**
 * CLI commands for hidden posts.
 *
 * @since 0.0.13
 */
class Post extends Command {

	/**
	 * Show statistics about posts with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing posts by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--post-types=<post_types>]
	 * : (Optional) Filter by post type(s). Multiple post types should be separated by commas.
	 *
	 * @since 0.0.13
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function stats( array $args, array $assoc_args ) : void {
		$filter     = $assoc_args['filter'] ?? 'all';
		$post_types = $assoc_args['post-types'] ?? [];
		if ( ! empty( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}

		$hidden_posts = $this->get_hidden_posts( $filter, $post_types );
		if ( empty( $hidden_posts ) ) {
			WP_CLI::success( __( 'There are no posts missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$this->display_hidden_post_stats( $hidden_posts );
	}

	/**
	 * List posts with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing posts by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--post-types=<post_types>]
	 * : (Optional) Filter by post type(s). Multiple post types should be separated by commas.
	 *
	 * [--limit=<limit>]
	 * : (Optional) Limit how many posts are listed. Must be bigger than 0. No limit by default.
	 *
	 * @since 0.0.13
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function list( array $args, array $assoc_args ) : void {
		$filter     = $assoc_args['filter'] ?? 'all';
		$post_types = $assoc_args['post-types'] ?? [];
		$limit      = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
		if ( ! empty( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}

		$hidden_posts = $this->get_hidden_posts( $filter, $post_types, $limit );

		if ( empty( $hidden_posts ) ) {
			WP_CLI::success( __( 'There are no posts missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$data = [];
		foreach ( $hidden_posts as $post ) {
			$language = LangInterface::get_post_language( $post->ID );
			if ( $language === null ) {
				$data['missing'][ $post->post_type ][] = $post;
				continue;
			}

			$data['unknown'][ $language ][ $post->post_type ][] = $post;
		}

		if ( isset( $data['missing'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Post Type', 'unbabble' ) . '%N',
					'%g' . __( 'ID', 'unbabble' ) . '%N',
					'%g' . __( 'Title', 'unbabble' ) . '%N',
					'%g' . __( 'Edit URL', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['missing'] as $post_type => $posts ) {
				$post_type_object = get_post_type_object( $post_type );
				foreach ( $posts as $post ) {
					$link = '';
					if ( $post_type_object->_edit_link ) {
						$link = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) );
					}
					$lines[ $post->ID ] = [
						$post_type,
						$post->ID,
						$post->post_title,
						$link
					];
				}
			}

			self::log_color( '%4' . __( 'Posts missing language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}

		if ( isset( $data['unknown'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Language', 'unbabble' ) . '%N',
					'%g' . __( 'Post Type', 'unbabble' ) . '%N',
					'%g' . __( 'ID', 'unbabble' ) . '%N',
					'%g' . __( 'Title', 'unbabble' ) . '%N',
					'%g' . __( 'Edit URL', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['unknown'] as $language => $post_types ) {
				foreach ( $post_types as $post_type => $posts ) {
					$post_type_object = get_post_type_object( $post_type );
					foreach ( $posts as $post ) {
						$link = '';
						if ( $post_type_object->_edit_link ) {
							$link = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) );
						}

						$lines[ $post->ID ] = [
							$language,
							$post_type,
							$post->ID,
							$post->post_title,
							$link
						];
					}
				}
			}

			self::log_color( '%4' . __( 'Posts with unknown language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}
	}

	/**
	 * Fix posts with missing language or unknown languages.
	 *
	 * ## OPTIONS
	 *
	 * <language_code>
	 * : Language code to set the posts to.
	 *
	 * [--filter=<filter_type>]
	 * : (Optional) Filter missing posts by 'missing' language, 'unknown' language, or 'all'.
	 *
	 * [--post-types=<post_types>]
	 * : (Optional) Filter by post type(s). Multiple post types should be separated by commas.
	 *
	 * [--limit=<limit>]
	 * : (Optional) Limit how many posts are fixed. Must be bigger than 0. No limit by default.
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
		$post_types = $assoc_args['post-types'] ?? [];
		$limit      = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
		if ( ! empty( $post_types ) ) {
			$post_types = explode( ',', $post_types );
		}

		$hidden_posts = $this->get_hidden_posts( $filter, $post_types, $limit );
		if ( empty( $hidden_posts ) ) {
			WP_CLI::success( __( 'There are no posts missing languages or with an unknown language.', 'unbabble' ) );
			return;
		}

		$this->display_hidden_post_stats( $hidden_posts );

		$this->confirm_color(
			sprintf(
				/* translators: 1: Language code */
				__( 'Are you sure you want to set the language %1$s for the posts displayed above?', 'unbabble' ),
				'%g'. $language . '%N'
			)
		);

		$successful        = 0;
		$success_post_type = [];
		$fail_post_type    = [];
		foreach ( $hidden_posts as $post ) {
			$success = LangInterface::set_post_language( $post->ID, $language, true );
			if ( ! $success ) {
				$fail_post_type[ $post->post_type ] = 1 + ( $fail_post_type[ $post->post_type ] ?? 0 );
				/* translators: 1: Post ID, 2: Post Type */
				WP_CLI::warning( sprintf( __( 'Failed for post %1$s (%2$s).', 'unbabble' ), $post->ID, $post->post_type ) );
				continue;
			}
			$success_post_type[ $post->post_type ] = 1 + ( $success_post_type[ $post->post_type ] ?? 0 );
			$successful += 1;
		}

		/* translators: 1: Amount of successfully updated posts, 2: Language code */
		WP_CLI::success( sprintf( __( 'Updated %1$s posts with the language %2$s.', 'unbabble' ), $successful, $language ) );
		$lines = [
			'header' => [
				'%g' . __( 'Post Type', 'unbabble' ) . '%N',
				'%g' . __( 'Count', 'unbabble' ) . '%N',
			],
		];
		if ( ! empty( $success_post_type ) ) {
			$success_lines = $lines;
			foreach ( $success_post_type as $post_type => $count ) {
				$success_lines[ $post_type ] = [ $post_type, $count ];

				// Delete the posts with missing language transient since it's no longer missing.
				delete_transient( sprintf( Keys::POST_TYPE_MISSING_LANGUAGE, $post_type ) );
			}
			self::log_color( '%4' . __( 'Successful updates', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $success_lines, 2 );
		}
		if ( ! empty( $fail_post_type ) ) {
			$fail_lines = $lines;
			foreach ( $fail_post_type as $post_type => $count ) {
				$fail_lines[ $post_type ] = [ $post_type, $count ];
			}
			self::log_color( '%4' . __( 'Failed updates', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $fail_lines, 2 );
		}
	}

	/**
	 * Returns array of hidden posts.
	 *
	 * @since 0.0.13
	 *
	 * @param string $focus
	 * @param array  $post_types
	 * @param ?int   $limit
	 * @return array
	 */
	private function get_hidden_posts( string $focus = 'all', array $post_types = [], ?int $limit = null ) : array {
		global $wpdb;
		\add_filter( 'ubb_do_hidden_languages_filter', '__return_false' );

		$allowed_languages       = implode( "','", LangInterface::get_languages() );
		$translatable_post_types = LangInterface::get_translatable_post_types();
		$translations_table      = ( new PostTable() )->get_table_name();

		$post_types = empty( $post_types ) ? $translatable_post_types : $post_types;
		if ( is_string( $post_types ) ) {
			$post_types = array_filter(
				explode( ',', $post_types ),
				function ( $post_type ) use ( $translatable_post_types ) {
					if ( ! is_string( $post_type ) ) {
						return false;
					}
					if ( ! \post_type_exists( $post_type ) ) {
						return false;
					}
					return in_array( $post_type, $translatable_post_types, true );
				}
			);
		}
		$post_types = implode( "','", $post_types );

		if ( $focus === 'missing' ) {
			$where_focus = "ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
			)";

		} else if ( $focus === 'unknown' ) {
			$where_focus = "ID IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale NOT IN ('{$allowed_languages}')
			)";

		} else if ( $focus === 'all' ) {
			$where_focus = $where_focus = "ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale IN ('{$allowed_languages}')
			)";

		} else {
			WP_CLI::error( "Unknown focus argument. Accepts: 'all', 'missing' and 'unknown'." );
		}

		$limit_str = '';
		if ( is_int( $limit ) && $limit > 0 ) {
			$limit_str = " LIMIT {$limit}";
		}

		return $wpdb->get_results(
			"SELECT ID, post_title, post_type
			FROM {$wpdb->posts} as P
			WHERE post_status != 'auto-draft'
			AND post_type IN ('{$post_types}')
			AND {$where_focus}
			{$limit_str}",
			OBJECT
		);
	}

	/**
	 * Displays statistics of the hidden posts.
	 *
	 * @since 0.0.13
	 *
	 * @param array $hidden_posts
	 * @return void
	 */
	private function display_hidden_post_stats( array $hidden_posts ) : void {
		$data = [];
		foreach ( $hidden_posts as $post ) {
			$language = LangInterface::get_post_language( $post->ID );
			if ( $language === null ) {
				$data['missing'][ $post->post_type ][] = $post->ID;
				continue;
			}

			$data['unknown'][ $language ][ $post->post_type ][] = $post->ID;
		}

		if ( isset( $data['missing'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Post Type', 'unbabble' ) . '%N',
					'%g' . __( 'Count', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['missing'] as $post_type => $ids ) {
				$lines[ $post_type ] = [
					$post_type,
					count( $ids )
				];
			}

			self::log_color( '%4' . __( 'Posts missing language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}

		if ( isset( $data['unknown'] ) ) {
			$lines = [
				'header' => [
					'%g' . __( 'Language', 'unbabble' ) . '%N',
					'%g' . __( 'Post Type', 'unbabble' ) . '%N',
					'%g' . __( 'Count', 'unbabble' ) . '%N',
				],
			];

			foreach ( $data['unknown'] as $language => $post_types ) {
				foreach ( $post_types as $post_type => $ids ) {
					$lines[ $language . '-' . $post_type ] = [
						$language,
						$post_type,
						count( $ids )
					];
				}
			}

			self::log_color( '%4' . __( 'Posts with unknown language', 'unbabble' ) . '%N' );
			$this->format_lines_and_log( $lines, 2 );
		}
	}
}
