<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use Ramsey\Uuid\Uuid;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Handle Language Meta Box for Posts and Terms.
 *
 * @since 0.0.1
 */
class LangInterface {

	/**
	 * Returns an array of the available languages.
	 *
	 * @since 0.0.11
	 *
	 * @return array
	 */
	public static function get_languages() : array {
		$options           = Options::get();
		$allowed_languages = $options['allowed_languages'];

		// Don't filter languages on admin.
		if ( \is_admin() ) {
			return $allowed_languages;
		}

		// Check if hidden languages is set.
		if ( ! isset( $options['hidden_languages'] ) ) {
			return $allowed_languages;
		}

		// Don't filter language when the user is logged in.
		if ( self::user_is_logged_in() ) {
			return $allowed_languages;
		}

		/**
		 * Filters whether to filter the allowed languages with the languages set in hidden
		 * languages.
		 *
		 * @since 0.0.12
		 *
		 * @param bool  $filter
		 * @param array $options Array of Unbabble's options.
		 */
		if ( ! \apply_filters( 'ubb_do_hidden_languages_filter', true, $options ) ) {
			return $allowed_languages;
		}

		// Remove hidden languages from allowed languages.
		$allowed_languages = array_filter(
			$allowed_languages,
			fn ( $lang ) => ! in_array( $lang, $options['hidden_languages'], true )
		);

		// Validation should keep this from happening.
		if ( empty( $allowed_languages ) ) {
			$allowed_languages = [ $options['default_language'] ];
		}

		return $allowed_languages;
	}

	/**
	 * Returns whether a language is allowed currently or not.
	 *
	 * @since 0.0.11
	 *
	 * @param string $language
	 * @return bool
	 */
	public static function is_language_allowed( string $language ) : bool {
		return in_array( $language, self::get_languages(), true );
	}

	/**
	 * Returns the default language.
	 *
	 * @since 0.0.11
	 *
	 * @return string
	 */
	public static function get_default_language() : string {
		$options   = Options::get();
		$languages = self::get_languages();
		// Validation should keep this from happening.
		if ( ! in_array( $options['default_language'], $languages, true ) ) {
			return $languages[ array_key_first( $languages ) ];
		}
		return $options['default_language'];
	}

	/**
	 * Returns the current language code.
	 *
	 * @since 0.0.1
	 *
	 * @return string The current language code.
	 */
	public static function get_current_language() : string {
		global $wp_query;
		if ( $wp_query !== null ) {
			$lang = get_query_var( 'lang', null );
		}
		// TODO: Auto-draft saving does not put the query var.
		if ( ! isset( $lang ) && isset( $_GET['lang'] ) ) {
			$lang = $_GET['lang'];
		}

		if ( ! isset( $lang ) && is_admin() ) {
			$lang = $_COOKIE['ubb_lang'] ?? null;
		}

		if ( ! isset( $lang ) ) {
			$lang = self::get_default_language();

		} else if ( ! self::is_language_allowed( $lang ) ) {
			$lang = self::get_default_language();
		}


		/**
		 * Filters the current language.
		 *
		 * @since 0.0.1
		 *
		 * @param string $language Current language.
		 */
		return apply_filters( 'ubb_current_lang', \sanitize_text_field( $lang ) );
	}

	/**
	 * Sets the current language.
	 *
	 * @since 0.0.1
	 *
	 * @param string $lang New current language.
	 * @return bool True if language is known and allowed and was set, false otherwise.
	 */
	public static function set_current_language( string $lang ) : bool {
		if ( ! self::is_language_allowed( $lang ) ) {
			return false;
		}
		\set_query_var( 'lang', $lang );
		return true;
	}

	/**
	 * Returns the translatable post types.
	 *
	 * @since 0.0.11
	 *
	 * @return array Array of post type slugs.
	 */
	public static function get_translatable_post_types() : array {
		$options = Options::get();
		return is_array( $options['post_types'] ) ? $options['post_types'] : [];
	}

	/**
	 * Returns if a post_type is translatable.
	 *
	 * @since 0.0.1
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public static function is_post_type_translatable( string $post_type ) : bool {
		return in_array( $post_type, self::get_translatable_post_types(), true );
	}

	/**
	 * Sets a posts language.
	 *
	 * If the language is already set, nothing will happen and it will return `false`. Use the $force
	 * argument to force the language change.
	 *
	 * @since 0.4.6 Added `ubb_post_language_set` action.
	 * @since 0.0.1
	 *
	 * @param  int    $post_id  The post that language is being set for.
	 * @param  string $language Language being set for post.
	 * @param  bool   $force    Whether to force the language set. If false (default) and the
	 *                          language is already set, nothing happens.
	 * @return bool True if language was set, false otherwise.
	 */
	public static function set_post_language( int $post_id, string $language, bool $force = false ) : bool {
		global $wpdb;
		$table_name = ( new PostTable() )->get_table_name();

		if ( ! self::is_language_allowed( $language ) ) {
			return false;
		}

		$old_language = self::get_post_language( $post_id );

		if ( ! $force ) {
			if ( $old_language === $language ) {
				return true;
			}
			if ( $old_language !== null ) {
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

		if ( is_int( $inserted ) ) {
			\delete_transient( sprintf( 'ubb_%s_post_language', $post_id ) );

			/**
			 * Fires after a post's language is set.
			 *
			 * @since 0.4.6
			 *
			 * @param int     $post_id      ID of the post.
			 * @param string  $language     Language code of the post.
			 * @param ?string $old_language Old language of the post.
			 * @param bool    $force        Whether the language set was forced.
			 */
			do_action( 'ubb_post_language_set', $post_id, $language, $force, $old_language );

			return true;
		}

		return false;
	}

	/**
	 * Returns a post's language.
	 *
	 * @since 0.5.0 Improve handling of empty values.
	 * @since 0.0.1
	 *
	 * @param  int    $post_id
	 * @return ?string String if the post has a language, null otherwise.
	 */
	public static function get_post_language( int $post_id ) : ?string {
		global $wpdb;
		$post_type = get_post_type( $post_id );
		if ( ! self::is_post_type_translatable( $post_type ) ) {
			// TODO: Maybe it should be the default.
			return self::get_current_language();
		}

		$transient_key = sprintf( 'ubb_%s_post_language', $post_id );
		$post_lang     = \get_transient( $transient_key );

		// If there is a transient value, return it.
		if ( $post_lang !== false ) {
			return empty( $post_lang ) ? null : $post_lang;
		}

		$table_name = ( new PostTable() )->get_table_name();
		$post_lang = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT locale FROM {$table_name} WHERE post_id = %s LIMIT 1",
				$post_id
			)
		);

		// Make sure `$post_lang` is an empty string (equal to null) if `get_var` returns empty.
		if ( empty( $post_lang ) ) {
			$post_lang = '';
		}

		\set_transient( $transient_key, $post_lang, 30 );

		return empty( $post_lang ) ? null : $post_lang;
	}

	/**
	 * Sets a post's source.
	 *
	 * A post's source, via a meta entry, is what links it to other posts (translations).
	 *
	 * @since 0.4.6 Added `ubb_post_source_set` action.
	 * @since 0.0.1
	 *
	 * @param int    $post_id   Post to set source for.
	 * @param string $source_id Source ID (translation map ID) to set post to.
	 * @param bool   $force     Whether to force the source set. If false (default) and the
	 *                          source is already set, nothing happens.
	 * @return bool True if source was set, false otherwise or if meta insert/update failed.
	 */
	public static function set_post_source( int $post_id, string $source_id, bool $force = false ) : bool {
		$previous_source = self::get_post_source( $post_id );

		if ( $force ) {
			if ( $source_id === $previous_source ) {
				return true;
			}
			$meta_id = update_post_meta( $post_id, 'ubb_source', $source_id );

		} else {
			$meta_id = add_post_meta( $post_id, 'ubb_source', $source_id, true );
		}

		if ( (bool) $meta_id ) {
			// Delete transient for post translations.
			\delete_transient( sprintf( 'ubb_%s_source_posts', $source_id ) );

			// Update transient for post source.
			\set_transient( sprintf( 'ubb_%s_post_source', $post_id ), $source_id, 30 );

			/**
			 * Fires after a post's source is set.
			 *
			 * @since 0.4.6
			 *
			 * @param int     $post_id         ID of the post.
			 * @param string  $source_id       Source ID of the post.
			 * @param ?string $previous_source Previous source ID of the post.
			 * @param bool    $force           Whether the source set was forced.
			 */
			do_action( 'ubb_post_source_set', $post_id, $source_id, $previous_source, $force );
		}

		return (bool) $meta_id;
	}

	/**
	 * Returns the post's source ID.
	 *
	 * @since 0.5.5 Remove delete since it was causing posts to get unlinked randomly.
	 * @since 0.4.6 Delete empty string `ubb_source`'s from the DB.
	 * @since 0.0.1
	 *
	 * @param int $post_id ID of the post to get source for.
	 * @return ?string String if the source is found, null otherwise.
	 */
	public static function get_post_source( int $post_id ) : ?string {
		$transient_key = sprintf( 'ubb_%s_post_source', $post_id );
		$source_id     = \get_transient( $transient_key );
		if ( $source_id !== false ) {
			return ( is_string( $source_id ) && ! empty( $source_id ) ) ? $source_id : null;
		}

		$source_id = get_post_meta( $post_id, 'ubb_source', true );

		/**
		 * If the source_id is an empty string, return null.
		 */
		if ( is_string( $source_id ) && empty( $source_id ) ) {
			$source_id = null;

		} else {
			$source_id = empty( $source_id ) ? null : $source_id;
		}

		\set_transient( $transient_key, $source_id, 30 );

		return $source_id;
	}

	/**
	 * Returns a post's translation for a specific language code.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $post_id ID of the post to get translation for.
	 * @param string $lang    Language of the translation.
	 * @return ?int Int if the translation is found, null if there is no translation or the language
	 *              is not known/allowed.
	 */
	public static function get_post_translation( int $post_id, string $lang ) : ?int {
		if ( ! self::is_language_allowed( $lang ) ) {
			return null;
		}
		$translations = self::get_post_translations( $post_id );
		$translation  = array_search( $lang, $translations, true );
		return is_int( $translation ) ? $translation : null;
	}

	/**
	 * Returns a post's translations.
	 *
	 * @since 0.4.6 Change null check to empty check for $source_id to prevent errors from empty ubb_source's.
	 * @since 0.0.1
	 *
	 * @param int $post_id ID of post to get translatiosn for.
	 * @return array {
	 *     Array of translations.
	 *
	 *     @type int    $key   Translation post ID.
	 *     @type string $value Translation post language code.
	 * }
	 */
	public static function get_post_translations( int $post_id ) : array {
		$source_id = self::get_post_source( $post_id );
		if ( empty( $source_id ) ) {
			return [];
		}

		$post_type = get_post( $post_id )->post_type ?? '';
		if ( empty( $post_type ) ){
			return [];
		}

		$posts = self::get_posts_for_source( $source_id );

		unset( $posts[ $post_id ] );

		return $posts;
	}

	/**
	 * Changes a post's language.
	 *
	 * When changing a post's language, the translatable terms and meta are changed to their
	 * translation if they exist, otherwise the term relation is lost and the meta value becomes
	 * empty. The translatable terms are set via the Unbabble Options and the meta keys via the
	 * `ubb_change_language_post_meta_translate_keys` filter, more in-depth description on the
	 * filter documentation.
	 *
	 * @since 0.4.6 Add `ubb_post_language_change` action.
	 * @since 0.0.1
	 *
	 * @param int    $post_id ID of the post being changed.
	 * @param string $lang    Language code to change post to.
	 * @return bool True if language was changed.
	 */
	public static function change_post_language( int $post_id, string $lang ) : bool {
		global $wpdb;

		$old_lang = self::get_post_language( $post_id );
		if (
			empty( $lang )
			|| ! self::is_language_allowed( $lang )
			|| $lang === $old_lang
		) {
			return false;
		}

		// If the target language is already used by the translation map, do not change language.
		$translations = self::get_post_translations( $post_id );
		if ( in_array( $lang, $translations, true ) ) {
			return false;
		}

		// Get post terms before language update.
		$terms = wp_get_post_terms( $post_id, get_post_taxonomies( $post_id ) );

		// Update the language.
		$rows_updated = $wpdb->update(
			( new PostTable() )->get_table_name(),
			[ 'locale' => $lang ],
			[ 'post_id' => $post_id ],
		);

		// Check for failure.
		if ( $rows_updated === false ) {
			return false;
		}

		\delete_transient( sprintf( 'ubb_%s_post_language', $post_id ) );

		/**
		 * Fires after a post's language is changed.
		 *
		 * TODO: should this be the same action as the one in `set_post_language`?
		 *
		 * @since 0.4.6
		 *
		 * @param int     $post_id  ID of the post.
		 * @param string  $lang     New language of the post.
		 * @param ?string $old_lang Old language of the post.
		 */
		do_action( 'ubb_post_language_change', $post_id, $lang, $old_lang );

		// Update Terms.

		// Filter needed since system still thinks its in the previous language.
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );
		foreach ( $terms as $term ) {
			if ( ! self::is_taxonomy_translatable( $term->taxonomy ) ) {
				continue;
			}
			$term_translation = self::get_term_translation( $term->term_id, $lang );

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
		if ( self::is_post_type_translatable( 'attachment' ) ) {
			$default_meta['_thumbnail_id'] = 'post';
		}

		/**
		 * Filters the list of meta keys to translate during post language change.
		 *
		 * TODO: Maybe use the same filter as the one in YoastDuplicatePost.
		 *
		 * @since 0.0.1
		 *
		 * @param string[] $meta_keys {
		 *     List of meta_keys (key) and their type (value) ('post' or 'term').
		 *
		 *     @type string $meta_key  Meta key to be translated
		 *     @type string $meta_type Type of the meta value, if they are IDs for post's or term's.
		 * }
		 * @param int    $post_id  ID of the post where the language is being changed.
		 * @param string $lang     New language of the post/meta.
		 * @param string $old_lang Old language of the post/meta.
		 */
		$meta_keys_to_translate = apply_filters( 'ubb_change_language_post_meta_translate_keys', $default_meta, $post_id, $lang, $old_lang );
		if ( ! self::translate_post_meta( $post_id, $lang, $meta_keys_to_translate ) ) {
			// TODO: Failure state
			// FIXME: language is changed but meta is not, what to do and return?
			return false;
		}

		return true;
	}

	/**
	 * Returns the posts for a source ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string $source_id The source ID to get translations map.
	 * @return array Array of post IDs and their languages.
	 */
	public static function get_posts_for_source( string $source_id ) : array {
		global $wpdb;
		$transient_key = sprintf( 'ubb_%s_source_posts', $source_id );
		$posts         = \get_transient( $transient_key );
		if ( $posts !== false ) {
			return is_array( $posts ) ? $posts : [];
		}

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id as ID
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s",
				$source_id,
			)
		);

		if ( $posts === null || empty( $posts ) ) {
			\set_transient( $transient_key, [], 30 );
			return [];
		}

		$table_name = ( new PostTable() )->get_table_name();

		$ids_str    = implode( ',', array_map( fn ( $post ) => $post->ID, $posts ) );
		$post_langs = $wpdb->get_results(
			"SELECT post_id, locale
			FROM {$table_name}
			WHERE post_id IN ({$ids_str})"
		);

		$lang_list = [];
		foreach ( $post_langs as $data ) {
			if ( ! self::is_language_allowed( $data->locale ) ) {
				continue;
			}
			$lang_list[ $data->post_id ] = $data->locale;
		}

		\set_transient( $transient_key, $lang_list, 30 );

		return $lang_list;
	}

	/**
	 * Returns a new unique source id (UUID) for posts.
	 *
	 * @since 0.0.1
	 *
	 * @return string Source UUID
	 */
	public static function get_new_post_source_id() : string {
		return self::get_new_source_id( 'post' );
	}

	/**
	 * Removes a post's source ID meta.
	 *
	 * Unlinks post from its translations.
	 *
	 * @since 0.4.6 Added `ubb_post_source_delete` action.
	 * @since 0.0.1
	 *
	 * @param string $post_id ID of the post to delete source.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_post_source( string $post_id ) : bool {

		// Delete transient for translations.
		$post_source   = self::get_post_source( $post_id );
		if ( ! $post_source ) {
			\delete_transient( sprintf( 'ubb_%s_source_posts', $post_source ) );
		}

		// Delete transient for source.
		\delete_transient( sprintf( 'ubb_%s_post_source', $post_id ) );

		/**
		 * Fires before a post's source is deleted.
		 *
		 * @since 0.4.6
		 *
		 * @param int    $post_id     ID of the post.
		 * @param string $post_source Source ID of the post.
		 */
		do_action( 'ubb_post_source_delete', $post_id, $post_source );

		return delete_post_meta( $post_id, 'ubb_source' );
	}

	/**
	 * Returns the translatable taxonomies.
	 *
	 * @since 0.0.11
	 *
	 * @return array Array of taxonomy slugs.
	 */
	public static function get_translatable_taxonomies() : array {
		$options = Options::get();
		return is_array( $options['taxonomies'] ) ? $options['taxonomies'] : [];
	}

	/**
	 * Returns if a taxonomy is translatable.
	 *
	 * @since 0.0.3
	 *
	 * @param string $taxonomy
	 * @return bool
	 */
	public static function is_taxonomy_translatable( string $taxonomy ) : bool {
		return in_array( $taxonomy, self::get_translatable_taxonomies(), true );
	}

	/**
	 * Sets a term language.
	 *
	 * If the language is already set, nothing will happen and it will return `false`. Use the $force
	 * argument to force the language change.
	 *
	 * @since 0.4.6 Added `ubb_term_language_set` action.
	 * @since 0.0.1
	 *
	 * @param  int    $term_id  The term that language is being set for.
	 * @param  string $language Language being set for term.
	 * @param  bool   $force    Whether to force the language set. If false (default) and the
	 *                          language is already set, nothing happens.
	 * @return bool True if language was set, false otherwise.
	 */
	public static function set_term_language( int $term_id, string $language, bool $force = false ) : bool {
		global $wpdb;
		$table_name = ( new TermTable() )->get_table_name();

		if ( ! self::is_language_allowed( $language ) ) {
			return false;
		}

		$old_language = self::get_term_language( $term_id );
		if ( ! $force ) {
			if ( $old_language !== null ) {
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

		if ( is_int( $inserted ) ) {
			\delete_transient( sprintf( 'ubb_%s_term_language', $term_id ) );

			/**
			 * Fires after a term's language is set.
			 *
			 * @since 0.4.6
			 *
			 * @param int     $term_id      ID of the term.
			 * @param string  $language     Language code of the term.
			 * @param ?string $old_language Old language of the term.
			 * @param bool    $force        Whether the language set was forced.
			 */
			do_action( 'ubb_term_language_set', $term_id, $language, $force, $old_language );

			return true;
		}

		return false;
	}

	/**
	 * Returns a term's language.
	 *
	 * @since 0.5.0 Improve handling of empty values.
	 * @since 0.0.1
	 *
	 * @param  int    $term_id
	 * @return ?string String if the term has a language, null otherwise.
	 */
	public static function get_term_language( int $term_id ) : ?string {
		global $wpdb;
		$transient_key = sprintf( 'ubb_%s_term_language', $term_id );
		$term_lang     = \get_transient( $transient_key );

		// If there is a transient value, return it.
		if ( $term_lang !== false ) {
			return empty( $term_lang ) ? null : $term_lang;
		}

		$table_name = ( new TermTable() )->get_table_name();
		$term_lang  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE term_id = %s LIMIT 1",
				$term_id
			),
			1
		);

		// Make sure `$term_lang` is an empty string (equal to null) when `get_var` returns empty.
		if ( empty( $term_lang ) ) {
			$term_lang = '';
		}

		\set_transient( $transient_key, $term_lang, 30 );

		return empty( $term_lang ) ? null : $term_lang;
	}

	/**
	 * Sets a term's source.
	 *
	 * A term's source, via a meta entry, is what links it to other terms (translations).
	 *
	 * @since 0.4.6 Added `ubb_term_source_set` action.
	 * @since 0.0.1
	 *
	 * @param int    $term_id   Post to set source for.
	 * @param string $source_id Source ID (translation map ID) to set term to.
	 * @param bool   $force     Whether to force the source set. If false (default) and the
	 *                          source is already set, nothing happens.
	 * @return bool True if source was set, false otherwise or if meta insert/update failed.
	 */
	public static function set_term_source( int $term_id, string $source_id, bool $force = false ) : bool {
		$previous_source = self::get_term_source( $term_id );
		if ( $force ) {
			if ( $source_id === $previous_source ) {
				return true;
			}
			$meta_id = update_term_meta( $term_id, 'ubb_source', $source_id );

		} else {
			$meta_id = add_term_meta( $term_id, 'ubb_source', $source_id, true );
		}

		if ( (bool) $meta_id ) {
			// Delete transient for term translations.
			\delete_transient( sprintf( 'ubb_%s_source_terms', $source_id ) );

			// Update transient for term source.
			\set_transient( sprintf( 'ubb_%s_term_source', $term_id ), $source_id, 30 );

			/**
			 * Fires after a term's source is set.
			 *
			 * @since 0.4.6
			 *
			 * @param int     $term_id         ID of the term.
			 * @param string  $source_id       Source ID of the term.
			 * @param ?string $previous_source Previous source ID of the term.
			 * @param bool    $force           Whether the source set was forced.
			 */
			do_action( 'ubb_term_source_set', $term_id, $source_id, $previous_source, $force );
		}

		return (bool) $meta_id;
	}

	/**
	 * Returns the term's source ID.
	 *
	 * @since 0.5.5 Remove delete since it was causing terms to get unlinked randomly.
	 * @since 0.4.5 Delete empty string `ubb_source`'s from the DB.
	 * @since 0.0.1
	 *
	 * @param int $term_id ID of the term to get source for.
	 * @return ?string String if the source is found, null otherwise.
	 */
	public static function get_term_source( int $term_id ) : ?string {
		$transient_key = sprintf( 'ubb_%s_term_source', $term_id );
		$source_id     = \get_transient( $transient_key );
		if ( $source_id !== false ) {
			return is_string( $source_id ) ? $source_id : null;
		}

		$source_id = \get_term_meta( $term_id, 'ubb_source', true );

		/**
		 * If the source_id is an empty string, return null.
		 */
		if ( is_string( $source_id ) && empty( $source_id ) ) {
			$source_id = null;

		} else {
			$source_id = empty( $source_id ) ? null : $source_id;
		}

		\set_transient( $transient_key, $source_id, 30 );

		return $source_id;
	}

	/**
	 * Returns a term's translation for a specific language code.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $term_id ID of the term to get translation for.
	 * @param string $lang    Language of the translation.
	 * @return ?int Int if the translation is found, null if there is no translation or the language
	 *              is not known/allowed.
	 */
	public static function get_term_translation( int $term_id, string $lang ) : ?int {
		if ( ! self::is_language_allowed( $lang ) ) {
			return null;
		}
		$translations = self::get_term_translations( $term_id );
		$translation  = array_search( $lang, $translations, true );
		return is_int( $translation ) ? $translation : null;
	}

	/**
	 * Returns a term's translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int $term_id ID of term to get translatiosn for.
	 * @return array {
	 *     Array of translations.
	 *
	 *     @type int    $key   Translation term ID.
	 *     @type string $value Translation term language code.
	 * }
	 */
	public static function get_term_translations( int $term_id ) : array {
		$source_id = self::get_term_source( $term_id );
		if ( empty( $source_id ) ) {
			return [];
		}

		$terms = self::get_terms_for_source( $source_id );

		unset( $terms[ $term_id ] );

		return $terms;
	}

	/**
	 * Changes a term's language.
	 *
	 *  TODO: What to do when term language changes? How to handle post relationships?
	 *
	 * @since 0.4.6 Added `ubb_term_language_change` action.
	 * @since 0.0.1
	 *
	 * @param int    $term_id ID of the term being changed.
	 * @param string $lang    Language code to change term to.
	 * @return bool True if language was changed.
	 */
	public static function change_term_language( int $term_id, string $lang ) : bool {
		global $wpdb;

		$old_lang = self::get_term_language( $term_id );
		if (
			empty( $lang )
			|| ! self::is_language_allowed( $lang )
			|| $lang === $old_lang
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

		\delete_transient( sprintf( 'ubb_%s_term_language', $term_id ) );

		/**
		 * Fires after a term's language is changed.
		 *
		 * TODO: should this be the same action as the one in `set_term_language`?
		 *
		 * @since 0.4.6
		 *
		 * @param int     $term_id  ID of the term.
		 * @param string  $lang     New language of the term.
		 * @param ?string $old_lang Old language of the term.
		 */
		do_action( 'ubb_term_language_change', $term_id, $lang, $old_lang );

		// TODO: Update posts?
		return true;
	}

	/**
	 * Returns the terms for a source ID.
	 *
	 * @since 0.4.5 Added missing return when terms are empty.
	 * @since 0.0.1
	 *
	 * @param string $source_id The source ID to get translations map.
	 * @return array Array of term IDs and their languages.
	 */
	public static function get_terms_for_source( string $source_id ) : array {
		global $wpdb;
		$transient_key = sprintf( 'ubb_%s_source_terms', $source_id );
		$terms         = \get_transient( $transient_key );
		if ( $terms !== false ) {
			return is_array( $terms ) ? $terms : [];
		}

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id as term_id
				FROM {$wpdb->termmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s",
				$source_id,
			)
		);

		if ( $terms === null ) {
			\set_transient( $transient_key, [], 30 );
			return [];
		}

		$table_name = ( new TermTable() )->get_table_name();
		$ids_str    = implode( ',', array_map( fn ( $term ) => $term->term_id, $terms ) );

		// Protection, but shouldn't happen.
		if ( empty( $ids_str ) ) {
			\set_transient( $transient_key, [], 30 );
			return [];
		}

		$term_langs = $wpdb->get_results(
			"SELECT term_id, locale
			FROM {$table_name}
			WHERE term_id IN ({$ids_str})"
		);

		$lang_list = [];
		foreach ( $term_langs as $data ) {
			if ( ! self::is_language_allowed( $data->locale ) ) {
				continue;
			}
			$lang_list[ $data->term_id ] = $data->locale;
		}

		\set_transient( $transient_key, $lang_list, 30 );

		return $lang_list;
	}

	/**
	 * Returns a new unique source id (UUID) for terms.
	 *
	 * @since 0.0.1
	 *
	 * @return string Source UUID
	 */
	public static function get_new_term_source_id() : string {
		return self::get_new_source_id( 'term' );
	}

	/**
	 * Removes a term's source ID meta.
	 *
	 * Unlinks term from its translations.
	 *
	 * @since 0.4.6 Added `ubb_term_source_delete` action.
	 * @since 0.0.1
	 *
	 * @param string $term_id ID of the term to delete source.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_term_source( string $term_id ) : bool {

		// Delete transient for translations.
		$term_source = self::get_term_source( $term_id );
		if ( ! $term_source ) {
			\delete_transient( sprintf( 'ubb_%s_source_terms', $term_source ) );
		}

		// Delete transient for source.
		\delete_transient( sprintf( 'ubb_%s_term_source', $term_id ) );

		/**
		 * Fires before a term's source is deleted.
		 *
		 * @since 0.4.6
		 *
		 * @param int    $term_id     ID of the term.
		 * @param string $term_source Source ID of the term.
		 */
		do_action( 'ubb_term_source_delete', $term_id, $term_source );

		return delete_term_meta( $term_id, 'ubb_source' );
	}

	/**
	 * Check whether Unbabble is active in the current blog.
	 *
	 * Helpful for stop some functionalities during switch_to_blog's.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public static function is_unbabble_active() : bool {
		return \is_plugin_active( 'unbabble/unbabble.php' );
	}

	/**
	 * Tries to translate the current url.
	 *
	 * If the language passed is not allowed or is the same as the current one, the current url is
	 * returned. If there is no available translation (post, taxonomy, archive) for the current url, the
	 * translated homepage url is returned.
	 *
	 * @since 0.0.3
	 *
	 * @param string $lang
	 * @return string
	 */
	public static function translate_current_url( string $lang ) : string {
		global $wp_the_query;

		// If language doesn't exist, or is the same as the current one, return the same url.
		if (
			! self::is_language_allowed( $lang )
			|| $lang === self::get_current_language()
		) {
			return ( ( $_SERVER['HTTPS'] ?? true ) ? 'https://' : 'http://' ) .
				$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		// Try to translate posts archive url.
		if ( $wp_the_query instanceof WP_Query && $wp_the_query->is_posts_page ) {
			add_filter( 'ubb_current_lang', $fn = fn () => $lang );
			$page_for_posts = get_option( 'page_for_posts' );
			$home_url       = home_url();
			remove_filter( 'ubb_current_lang', $fn );
			if ( empty( $page_for_posts ) ) {
				return $home_url;
			}
			return get_permalink( $page_for_posts );
		}

		// Try to translate a post types archive url.
		if ( $wp_the_query instanceof WP_Query && $wp_the_query->is_post_type_archive() ) {
			add_filter( 'ubb_current_lang', $fn = fn () => $lang );
			$archive_link = get_post_type_archive_link( $wp_the_query->query['post_type'] );
			remove_filter( 'ubb_current_lang', $fn );
			return $archive_link;
		}

		// Try to translate a taxonomies archive url.
		if (
			$wp_the_query instanceof WP_Query
			&& ( $wp_the_query->is_category() || $wp_the_query->is_tag() || $wp_the_query->is_tax() )
		) {
			$term_id = $wp_the_query->queried_object->term_id ?? null;
			if ( $term_id === null ) {
				add_filter( 'ubb_current_lang', $fn = fn () => $lang );
				$home_url = home_url();
				remove_filter( 'ubb_current_lang', $fn );
				return $home_url;
			}

			$translation = self::get_term_translation( $term_id, $lang );
			if ( $translation ) {
				add_filter( 'ubb_current_lang', $fn = fn () => $lang );
				$translation_permalink = get_term_link( $translation );
				remove_filter( 'ubb_current_lang', $fn );
				return $translation_permalink;
			}
		}

		// Try to translate a singular post url.
		if ( $wp_the_query instanceof WP_Query && $wp_the_query->is_singular() ) {
			$post_id = $wp_the_query->queried_object->ID ?? null;
			if ( $post_id === null ) {
				add_filter( 'ubb_current_lang', $fn = fn () => $lang );
				$home_url = home_url();
				remove_filter( 'ubb_current_lang', $fn );
				return $home_url;
			}

			$translation = self::get_post_translation( $post_id, $lang );
			if ( $translation ) {
				add_filter( 'ubb_current_lang', $fn = fn () => $lang );
				$translation_permalink = get_permalink( $translation );
				remove_filter( 'ubb_current_lang', $fn );
				return $translation_permalink;
			}
		}

		// Check for user defined routes.
		if ( $wp_the_query instanceof WP_Query ) {

			/**
			 * Filter to add translatable routes outside of posts, taxonomies and archives.
			 *
			 * Only the top route needs to be added, the same that was added to the rewrite rules.
			 * For example: A route for accounts 'account' might have sub routes like
			 * 'account/address'. Only the top route 'account' needs to be added to this filter,
			 * since WordPress will match the top route in the rewrite rules.
			 *
			 * @since 0.4.0
			 *
			 * @param array  $routes Translatable routes.
			 * @param string $lang   Language code to translate route to.
			 * @return array
			 */
			$routes = apply_filters( 'ubb_translatable_routes', [], $lang );

			// Try to match all the routes with the route that WordPress matched. First one matched is chosen.
			foreach ( $routes as $route ) {

				// If the route is matched, return the translated url.
				if ( isset( $wp_the_query->query[ $route ] ) ) {

					// Get base url for the language requested.
					add_filter( 'ubb_current_lang', $fn = fn () => $lang );
					$home_url = \home_url();
					remove_filter( 'ubb_current_lang', $fn );

					// Build the url.
					$url_parts = [
						\untrailingslashit( $home_url ),
						$route,
						$wp_the_query->query[ $route ]
					];
					$url = implode( '/', array_filter( $url_parts ) );

					return $url;
				}
			}
		}

		// Default to home_url of the language.
		add_filter( 'ubb_current_lang', $fn = fn () => $lang );
		$lang_link = home_url();
		remove_filter( 'ubb_current_lang', $fn );
		return $lang_link;
	}

	/**
	 * Returns a new unique source ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string $type Type of source id, 'post' or 'term'.
	 * @return string Source UUID
	 */
	private static function get_new_source_id( string $type = 'post' ) : string {
		global $wpdb;

		/**
		 * Filters a new source id.
		 *
		 * Return a non-empty and string value to bypass uuid generation. Uuid's are not checked
		 * to see if they already exist in the database.
		 *
		 * @since 0.0.4
		 *
		 * @param string $source_id
		 * @param string $type
		 * @return string
		 */
		$uuid = apply_filters( 'ubb_new_source_id', '', $type );
		if ( empty( $uuid ) || ! is_string( $uuid ) ) {
			$uuid = Uuid::uuid7()->toString();
		}

		return $uuid;
	}

	/**
	 * Translate a posts meta entries.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $post_id                ID of the post to translate meta for.
	 * @param string $new_lang               Target code of the language translation.
	 * @param array  $meta_keys_to_translate List of meta keys to translate and their type of meta_value.
	 * @return bool True on success, false on failure.
	 */
	private static function translate_post_meta( int $post_id, string $new_lang, array $meta_keys_to_translate ) : bool {
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
			$meta_key   = $meta_data['meta_key'];
			$meta_value = maybe_unserialize( $meta_data['meta_value'] );

			/**
			 * Filters the value being saved for a post meta translation, before the new translated
			 * value is fetched.
			 *
			 * If the value returned is not null, that value is used to update the post meta.
			 *
			 * @since 0.0.1
			 *
			 * @param mixed  $pre        Value to save to database.
			 * @param mixed  $meta_value Meta value being updated.
			 * @param string $meta_key   Meta key being updated.
			 * @param int    $post_id    ID of the post for which the meta is being updated.
			 * @param string $new_lang   New language of the meta value.
			 * @param int    $meta_id    Meta ID being updated.
			 */
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

	/**
	 * Updates a meta_id with the updated translated value.
	 *
	 * @since 0.0.1
	 *
	 * @param string $meta_type   Meta type, 'post' or 'term'.
	 * @param int    $meta_id     Id of the meta, necessary to update.
	 * @param string $meta_key    Meta key.
	 * @param mixed  $meta_value  Value to translate and update.
	 * @param string $new_lang    Target language.
	 * @return void
	 */
	private static function translate_post_meta_type_ids( string $meta_type, int $meta_id, string $meta_key, $meta_value, string $new_lang ) : void {
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
					if ( ! $post instanceof WP_Post ) {
						continue;
					}

					// Keep same ID for non translatable post types.
					if ( ! self::is_post_type_translatable( $post->post_type ) ) {
						$new_meta_value[] = $object_id;
						continue;
					}

					$meta_post_translation = self::get_post_translation( $object_id, $new_lang );
					if ( $meta_post_translation === null ) {
						continue;
					}
					$new_meta_value[] = $meta_post_translation;
				} else if ( $meta_type === 'term' ) {
					$term = get_term( $object_id );
					if ( ! $term instanceof WP_Term ) {
						continue;
					}

					// Keep same ID for non translatable taxonomies.
					if ( ! self::is_taxonomy_translatable( $term->taxonomy ) ) {
						$new_meta_value[] = $object_id;
						continue;
					}

					$meta_term_translation = self::get_term_translation( $object_id, $new_lang );
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
				if ( ! $post instanceof WP_Post ) {
					return;
				}

				// Keep same ID for non translatable post types.
				$new_meta_value = $meta_value;
				if ( self::is_post_type_translatable( $post->post_type ) ) {
					$new_meta_value = self::get_post_translation( $meta_value, $new_lang );
					if ( $new_meta_value === null ) {
						$new_meta_value = '';
					}
				}

			} else if ( $meta_type === 'term' ) {
				$term = get_term( $meta_value );
				if ( ! $term instanceof WP_Term ) {
					return;
				}

				// Keep same ID for non translatable taxonomies.
				$new_meta_value = $meta_value;
				if ( self::is_taxonomy_translatable( $term->taxonomy ) ) {
					$new_meta_value = self::get_term_translation( $meta_value, $new_lang );
					if ( $new_meta_value === null ) {
						$new_meta_value = '';
					}
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

	/**
	 * Returns whether a user is logged in via the session cookie.
	 *
	 * This function is a workaround to check for a user session when the routing language check
	 * runs too early for WordPress to have the user session already fetched and validated.
	 *
	 * @since 0.4.1
	 *
	 * @return bool
	 */
	private static function user_is_logged_in() : bool {
		require_once ABSPATH . WPINC . '/user.php';
		require_once ABSPATH . WPINC . '/pluggable.php';
		if ( ! function_exists( 'wp_validate_logged_in_cookie' ) ) {
			return false;
		}
		return is_int( \wp_validate_logged_in_cookie( false ) );
	}
}
