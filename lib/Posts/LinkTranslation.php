<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

/**
 * Hooks related to linking and unlinking translations between existing posts or translation
 * sets of posts.
 *
 * @since 0.0.1
 */
class LinkTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_action( 'save_post', [ $this, 'save_link_translations' ], PHP_INT_MAX - 10 );
		\add_action( 'save_post', [ $this, 'save_unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'save_unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'save_link_translations' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Link translations for post $post_id.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function save_link_translations( int $post_id ) {
		$post_type = get_post_type( $post_id );
		if (
			! isset( $_POST['ubb_link_translation'] )
			|| ! is_numeric( $_POST['ubb_link_translation'] )
			|| isset( $_POST['menu'] ) // Stop if nav menu updated or saved.
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return false;
		}

		return $this->link_translations( $post_id, \sanitize_text_field( $_POST['ubb_link_translation'] ) );
	}

	public function link_translations( int $post_id, int $link_target ) : bool {
		$post_type = get_post_type( $post_id );

		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return false;
		}

		$link_post = \get_post( $link_target );;
		if (
			$link_post === null
			|| ! LangInterface::is_post_type_translatable( $link_post->post_type )
			|| $link_post->post_type !== $post_type
		) {
			return false;
		}

		$post_source = LangInterface::get_post_source( $post_id );
		$link_source = LangInterface::get_post_source( $link_post->ID );

		// Check if already linked.
		if ( $post_source !== null && $post_source === $link_source ) {
			return true;
		}

		if ( $link_source === null ) {
			$link_source = LangInterface::get_new_post_source_id();
			LangInterface::set_post_source( $link_post->ID, $link_source, true );
		}

		if ( ! LangInterface::set_post_source( $post_id, $link_source, true ) ) {
			// TODO: show admin notice of failure to change new post source.
			LangInterface::set_post_source( $post_id, $post_source );
			return false;
		}

		return true;
	}

	/**
	 * Unlink post $post_id from its translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function save_unlink( int $post_id ) : bool {
		$post_type = get_post_type( $post_id );
		if (
			! isset( $_POST['ubb_link_translation'] )
			|| $_POST['ubb_link_translation'] !== 'unlink'
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return false;
		}

		return $this->unlink( $post_id );
	}

	public function unlink( int $post_id ) : bool {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return false;
		}

		$translations = LangInterface::get_post_translations( $post_id );

		$delete_success = LangInterface::delete_post_source( $post_id );

		if ( ! $delete_success ) {
			return false;
		}

		// Clean up post source if there's a single other post for that source.
		if ( count( $translations ) === 1 ) {
			$other_post_id = array_keys( $translations )[0];
			LangInterface::delete_post_source( $other_post_id );
		}

		return true;
	}

	public function get_possible_links( WP_Post $post, string $post_lang, int $page ) : array {
		global $wpdb;
		$translations_table    = ( new PostTable() )->get_table_name();
		$allowed_languages_str = implode( "','", LangInterface::get_languages() );
		$per_page              = 10;
		if ( $page < 1 ) {
			$page = 1;
		}

		$possible_sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS
				MIN(A.post_id) as post_id,
				GROUP_CONCAT( CONCAT(A.post_id, 0x1F, locale, 0x1F, A.post_title) SEPARATOR 0x1D ) as group_info
				FROM (
					SELECT PT.post_id, P.post_title, locale, IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					INNER JOIN {$wpdb->posts} as P ON (PT.post_id = P.ID)
					WHERE post_type = %s AND post_status NOT IN ('revision','auto-draft')
					AND PT.locale IN ('{$allowed_languages_str}')
				) AS A
				WHERE locale != %s
				AND source NOT IN (
					SELECT IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					WHERE locale = %s
				) GROUP BY source
				ORDER BY group_info
				LIMIT %d
				OFFSET %d",
				$post->post_type,
				$post_lang,
				$post_lang,
				$per_page,
				$per_page * ( $page - 1 )
			)
		);

		$options = [];
		foreach ( $possible_sources as $source ) {
			$source_data  = explode( chr( 0x1D ), $source->group_info );
			$source_posts = [];
			foreach ( $source_data as $post_info_str ) {
				$post_info = explode( chr( 0x1F ), $post_info_str );
				if ( count( $post_info ) !== 3 ) {
					continue;
				}
				$source_posts[] = [ 'ID' => $post_info[0], 'lang' => $post_info[1], 'title' => $post_info[2] ];
			}

			$options[] = [
				'source' => $source->post_id,
				'posts'  => $source_posts,
			];
		}

		$links_found = $wpdb->get_var( "SELECT FOUND_ROWS()" );
		$pages       = ceil( $links_found / $per_page );

		return [
			'options' => $options,
			'pages'   => $pages,
			'page'    => $page,
		];
	}
}
