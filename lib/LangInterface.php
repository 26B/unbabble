<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Handle Language Meta Box for Posts and Terms.
 *
 * @since 0.0.0
 */
class LangInterface {

	/**
	 * Set post language in the custom post language table.
	 *
	 * If the language is already set, nothing will happen and it will return `false`. To force
	 * language change, you can use the force argument.
	 *
	 * @param  int    $post_id
	 * @param  string $language
	 * @param  bool   $force
	 * @return bool
	 */
	public static function set_post_language( int $post_id, string $language, bool $force = false ) : bool {
		global $wpdb;
		$table_name = ( new PostTable() )->get_table_name();

		if ( ! $force ) {
			$existing_language = self::get_post_language( $post_id );
			if ( $existing_language !== null ) {
				return false;
			}
		}

		$inserted = $wpdb->insert(
			$table_name,
			[
				'post_id' => $post_id,
				'locale'  => $language,
			]
		);
		return is_int( $inserted );
	}

	/**
	 * Get post language from the custom post language table.
	 *
	 * @param  int    $post_id
	 * @return ?string String if the post has a language, null otherwise.
	 */
	public static function get_post_language( int $post_id ) : ?string {
		global $wpdb;
		$table_name = ( new PostTable() )->get_table_name();
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE post_id = %s LIMIT 1",
				$post_id
			),
			1
		);
	}

	public static function set_source( int $post_id, int $source_id ) : bool {
		// TODO: Check already exists.
		$meta_id = add_post_meta( $post_id, 'ubb_source', $source_id, true );
		return is_int( $meta_id );
	}

	public static function get_source( int $post_id ) : ?string {
		$source_id = get_post_meta( $post_id, 'ubb_source', true );;
		if ( empty( $source_id ) ) {
			return null;
		}
		return $source_id;
	}

	// TODO: better name
	public static function get_translation_list( int $post_id ) : array {
		global $wpdb;
		$source_id = self::get_source( $post_id );
		if ( $source_id === null ) {
			return [];
		}

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s
				AND post_id != %s",
				$source_id,
				$post_id
			)
		);

		$lang_list = [];
		foreach ( $posts as $post ) {
			$lang_list[ $post->post_id ] = self::get_post_language( $post->post_id );
		}
		return $lang_list;
	}

	public static function get_current_language() : string {
		$options = Options::get();
		return $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];
	}

	public static function change_post_language( int $post_id, string $lang ) : bool {
		global $wpdb;

		if (
			empty( $lang )
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
			|| $lang === self::get_post_language( $post_id )
		) {
			return false;
		}

		$translations = self::get_translation_list( $post_id );
		if ( in_array( $lang, $translations, true ) ) {
			return false;
		}

		$rows_updated = $wpdb->update(
			( new PostTable() )->get_table_name(),
			[ 'locale' => $lang ],
			[ 'post_id' => $post_id ],
		);

		if ( $rows_updated === false ) {
			return false;
		}

		// TODO: Update terms
		return true;
	}

	// TODO: Test me!
	public function get_post_translation( int $post_id, string $lang ) : ?int {
		global $wpdb;
		$source_id = self::get_source( $post_id );
		if ( $source_id === null ) {
			return null;
		}

		$post_lang_table = ( new PostTable() )->get_table_name();
		$post_id         = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s
				AND post_id IN ( SELECT post_id FROM {$post_lang_table} WHERE lang = %s )
				LIMIT 1",
				$source_id,
				$lang
			)
		);

		return $post_id;
	}
}
