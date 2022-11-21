<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Handle Language Meta Box for Posts and Terms.
 *
 * @since 0.0.0
 */
class LangInterface {

	public static function get_current_language() : string {
		$options = Options::get();
		$lang    = get_query_var( 'lang', null );

		// TODO: Auto-draft saving does not put the query var.
		if ( ! isset( $lang ) && isset( $_GET['lang'] ) ) {
			$lang = $_GET['lang'];
		}

		if ( ! isset( $lang ) && is_admin() ) {
			$lang = $_COOKIE['ubb_lang'] ?? null;
		}

		if ( ! isset( $lang )  ) {
			$lang = $options['default_language'];
		}

		if ( ! in_array( $lang, $options['allowed_languages'] ) ) {
			$lang = $options['default_language'];
		}

		// TODO: which sanitize to use.
		return apply_filters( 'ubb_current_lang', \sanitize_text_field( $lang ) );
	}

	public static function set_current_language( string $lang ) : bool {
		$options = Options::get();
		if ( ! in_array( $lang, $options['allowed_languages'], true ) ) {
			return false;
		}
		set_query_var( 'lang', $lang );
		return true;
	}

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
			if ( $existing_language === $language ) {
				return true;
			}
			if ( $existing_language !== null ) {
				return false;
			}
		}

		$inserted = $wpdb->replace(
			$table_name,
			[
				'post_id' => $post_id,
				'locale'  => $language,
			],
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

	public static function set_post_source( int $post_id, int $source_id ) : bool {
		// TODO: Check already exists.
		$meta_id = add_post_meta( $post_id, 'ubb_source', $source_id, true );
		return is_int( $meta_id );
	}

	public static function get_post_source( int $post_id ) : ?string {
		$source_id = get_post_meta( $post_id, 'ubb_source', true );;
		if ( empty( $source_id ) ) {
			return null;
		}
		return $source_id;
	}

	// TODO: Test me!
	public static function get_post_translation( int $post_id, string $lang ) : ?int {
		global $wpdb;
		$source_id = self::get_post_source( $post_id );
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
				AND post_id IN ( SELECT post_id FROM {$post_lang_table} WHERE locale = %s )
				LIMIT 1",
				$source_id,
				$lang
			)
		);

		return $post_id;
	}

	// TODO: better name
	public static function get_post_translations( int $post_id ) : array {
		global $wpdb;
		$source_id = self::get_post_source( $post_id );
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

		$languages = Options::get()['allowed_languages'];
		$lang_list = [];
		foreach ( $posts as $post ) {
			$post_language = self::get_post_language( $post->post_id );
			if ( ! in_array( $post_language, $languages, true ) ) {
				continue;
			}
			$lang_list[ $post->post_id ] = $post_language;
		}
		return $lang_list;
	}

	public static function change_post_language( int $post_id, string $lang ) : bool {
		global $wpdb;

		$old_lang = self::get_post_language( $post_id );
		if (
			empty( $lang )
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
			|| $lang === $old_lang
		) {
			return false;
		}

		$translations = self::get_post_translations( $post_id );
		if ( in_array( $lang, $translations, true ) ) {
			return false;
		}

		$terms = wp_get_post_terms( $post_id, get_post_taxonomies( $post_id ) );

		$rows_updated = $wpdb->update(
			( new PostTable() )->get_table_name(),
			[ 'locale' => $lang ],
			[ 'post_id' => $post_id ],
		);

		if ( $rows_updated === false ) {
			return false;
		}

		// Update Terms.

		// Filter needed since system still thinks its in the previous language.
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		$allowed_taxonomies = Options::get_allowed_taxonomies();
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->taxonomy, $allowed_taxonomies, true ) ) {
				continue;
			}
			$term_translation = LangInterface::get_term_translation( $term->term_id, $lang );

			wp_remove_object_terms( $post_id, $term->term_id, $term->taxonomy );

			if ( $term_translation !== null ) {
				$status = wp_add_object_terms( $post_id, $term_translation, $term->taxonomy );
				if ( empty( $status ) || is_wp_error( $status )	) {
					error_log( print_r( "ChangeLanguage - term update failed for term {$term_translation}.", true ) );
					// TODO: What else to do here?
				}
			}
		}
		remove_filter( 'ubb_use_term_lang_filter', '__return_false' );

		// Update Meta.
		$default_meta = [];
		if ( in_array( 'attachment', Options::get_allowed_post_types(), true ) ) {
			$default_meta['_thumbnail_id'] = 'post';
		}
		// TODO: Filter docs. Use same filter as the one is YoastDuplicatePost.
		$meta_keys_to_translate = apply_filters( 'ubb_change_language_post_meta_translate_keys', $default_meta, $post_id, $lang, $old_lang );
		if ( ! self::translate_post_meta( $post_id, $lang, $meta_keys_to_translate ) ) {
			// TODO: Failure state
			return false;
		}

		return true;
	}

	// TODO: Documentation.

	public static function set_term_language( int $term_id, string $language, bool $force = false ) : bool {
		global $wpdb;
		$table_name = ( new TermTable() )->get_table_name();

		if ( ! $force ) {
			$existing_language = self::get_term_language( $term_id );
			if ( $existing_language !== null ) {
				return false;
			}
		}

		$inserted = $wpdb->replace(
			$table_name,
			[
				'term_id' => $term_id,
				'locale'  => $language,
			]
		);
		return is_int( $inserted );
	}

	public static function get_term_language( int $term_id ) : ?string {
		global $wpdb;
		$table_name = ( new TermTable() )->get_table_name();
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE term_id = %s LIMIT 1",
				$term_id
			),
			1
		);
	}

	public static function set_term_source( int $term_id, int $source_id ) : bool {
		// TODO: Check already exists.
		$meta_id = add_term_meta( $term_id, 'ubb_source', $source_id, true );
		return is_int( $meta_id );
	}

	public static function get_term_source( int $term_id ) : ?string {
		$source_id = get_term_meta( $term_id, 'ubb_source', true );;
		if ( empty( $source_id ) ) {
			return null;
		}
		return $source_id;
	}

	public static function get_term_translation( int $term_id, string $lang ) : ?int {
		global $wpdb;
		$source_id = self::get_term_source( $term_id );
		if ( $source_id === null ) {
			return null;
		}

		$term_lang_table = ( new TermTable() )->get_table_name();
		$term_id         = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id
				FROM {$wpdb->termmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s
				AND term_id IN ( SELECT term_id FROM {$term_lang_table} WHERE locale = %s )
				LIMIT 1",
				$source_id,
				$lang
			)
		);

		return $term_id;
	}

	public static function get_term_translations( int $term_id ) : array {
		global $wpdb;
		$source_id = self::get_term_source( $term_id );
		if ( $source_id === null ) {
			return [];
		}

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id
				FROM {$wpdb->termmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s
				AND term_id != %s",
				$source_id,
				$term_id
			)
		);

		$lang_list = [];
		foreach ( $terms as $term ) {
			$lang_list[ $term->term_id ] = self::get_term_language( $term->term_id );
		}
		return $lang_list;
	}

	// TODO: What to do when term language changes? How to handle post relationships?
	public static function change_term_language( int $term_id, string $lang ) : bool {
		global $wpdb;

		if (
			empty( $lang )
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
			|| $lang === self::get_term_language( $term_id )
		) {
			return false;
		}

		$translations = self::get_term_translations( $term_id );
		if ( in_array( $lang, $translations, true ) ) {
			return false;
		}

		$rows_updated = $wpdb->update(
			( new TermTable() )->get_table_name(),
			[ 'locale' => $lang ],
			[ 'term_id' => $term_id ],
		);

		if ( $rows_updated === false ) {
			return false;
		}

		// TODO: Update posts?
		return true;
	}

	private static function translate_post_meta( $post_id, $new_lang, $meta_keys_to_translate ) : bool {
		global $wpdb;
		$meta_keys_str = implode(
			"','",
			array_map(
				fn ( $meta_key ) => esc_sql( $meta_key ),
				array_keys( $meta_keys_to_translate )
			)
		);
		$meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN ('{$meta_keys_str}') AND post_id = %s",
				$post_id
			),
			ARRAY_A
		);

		// Code similar to one in YoastDuplicatePost.
		foreach ( $meta as $meta_data ) {
			$meta_key       = $meta_data['meta_key'];
			$meta_value     = maybe_unserialize( $meta_data['meta_value'] );
			$new_meta_value = \apply_filters( 'ubb_change_language_post_meta_translate_value', null, $meta_value, $meta_key, $post_id, $new_lang, $meta_data['meta_id'] );
			if ( $new_meta_value !== null ) {
				$status = update_metadata_by_mid( 'post', $meta_data['meta_id'], $new_meta_value, $meta_key );
				if ( $status ) {
					continue;
				}
			}

			self::translate_post_meta_type_ids( $meta_keys_to_translate[ $meta_key ], $meta_data['meta_id'], $meta_key, $meta_value, $new_lang );
		}

		return true;
	}

	private static function translate_post_meta_type_ids( $meta_type, $meta_id, $meta_key, $meta_value, $new_lang ) : void {
		if ( $meta_type !== 'post' && $meta_type !== 'term' ) {
			return;
		}

		$new_meta_value = null;
		if ( is_array( $meta_value ) ) {

			// Verify all numeric.
			if ( count( array_filter( $meta_value, 'is_numeric' ) ) !== count( $meta_value ) ) {
				return;
			}

			$new_meta_value = [];
			foreach ( $meta_value as $object_id ) {
				if ( $meta_type === 'post' ) {
					$post = get_post( $object_id );
					if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Options::get_allowed_post_types(), true )  ) {
						continue;
					}
					$meta_post_translation = LangInterface::get_post_translation( $object_id, $new_lang );
					if ( $meta_post_translation === null ) {
						continue;
					}
					$new_meta_value[] = $meta_post_translation;
				} else if ( $meta_type === 'term' ) {
					$term = get_term( $object_id );
					if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, Options::get_allowed_taxonomies(), true )  ) {
						continue;
					}
					$meta_term_translation = LangInterface::get_term_translation( $object_id, $new_lang );
					if ( $meta_term_translation === null ) {
						continue;
					}
					$new_meta_value[] = $meta_term_translation;
				}
			}
		}

		if ( is_numeric( $meta_value ) ) {
			if ( $meta_type === 'post' ) {
				$post = get_post( $meta_value );
				if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Options::get_allowed_post_types(), true )  ) {
					return;
				}
				$new_meta_value = LangInterface::get_post_translation( $meta_value, $new_lang );
				if ( $new_meta_value === null ) {
					$new_meta_value = '';
				}
			} else if ( $meta_type === 'term' ) {
				$term = get_term( $meta_value );
				if ( ! $term instanceof WP_Post || ! in_array( $term->taxonomy, Options::get_allowed_taxonomies(), true )  ) {
					return;
				}
				$new_meta_value = LangInterface::get_term_translation( $meta_value, $new_lang );
				if ( $new_meta_value === null ) {
					$new_meta_value = '';
				}
			}
		}

		if ( $new_meta_value === null ) {
			return;
		}

		if ( empty( $new_meta_value ) ) {
			$status = delete_metadata_by_mid( 'post', $meta_id );
			if ( ! $status ) {
				// TODO: log.
			}
			return;
		}

		$status = update_metadata_by_mid( 'post', $meta_id, $new_meta_value, $meta_key );
		if ( ! $status ) {
			// TODO: log.
		}
	}
}
