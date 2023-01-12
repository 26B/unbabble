<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use Ramsey\Uuid\Uuid;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Handle Language Meta Box for Posts and Terms.
 *
 * @since 0.0.1
 */
class LangInterface {

	/**
	 * Returns the current language code.
	 *
	 * @since 0.0.1
	 *
	 * @return string The current language code.
	 */
	public static function get_current_language() : string {
		global $wp_query;
		$options = Options::get();
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
			$lang = $options['default_language'];
		}

		if ( ! in_array( $lang, $options['allowed_languages'] ) ) {
			$lang = $options['default_language'];
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
		$options = Options::get();
		if ( ! in_array( $lang, $options['allowed_languages'], true ) ) {
			return false;
		}
		set_query_var( 'lang', $lang );
		return true;
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
		return in_array( $post_type, Options::get_allowed_post_types(), true );
	}

	/**
	 * Sets a posts language.
	 *
	 * If the language is already set, nothing will happen and it will return `false`. Use the $force
	 * argument to force the language change.
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

		if ( ! in_array( $language, Options::get()['allowed_languages'], true ) ) {
			return false;
		}

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
	 * Returns a post's language.
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

		$table_name = ( new PostTable() )->get_table_name();
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT locale FROM {$table_name} WHERE post_id = %s LIMIT 1",
				$post_id
			)
		);
	}

	/**
	 * Sets a post's source.
	 *
	 * A post's source, via a meta entry, is what links it to other posts (translations).
	 *
	 * @since 0.0.1
	 *
	 * @param int    $post_id   Post to set source for.
	 * @param string $source_id Source ID (translation map ID) to set post to.
	 * @param bool   $force     Whether to force the source set. If false (default) and the
	 *                          source is already set, nothing happens.
	 * @return bool True if source was set, false otherwise or if meta insert/update failed.
	 */
	public static function set_post_source( int $post_id, string $source_id, bool $force = false ) : bool {
		if ( $force ) {
			if ( $source_id === LangInterface::get_post_source( $post_id ) ) {
				return true;
			}
			$meta_id = update_post_meta( $post_id, 'ubb_source', $source_id );
		} else {
			$meta_id = add_post_meta( $post_id, 'ubb_source', $source_id, true );
		}
		return (bool) $meta_id;
	}

	/**
	 * Returns the post's source ID.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id ID of the post to get source for.
	 * @return ?string String if the source is found, null otherwise.
	 */
	public static function get_post_source( int $post_id ) : ?string {
		$source_id = get_post_meta( $post_id, 'ubb_source', true );;
		if ( empty( $source_id ) ) {
			return null;
		}
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
		global $wpdb;
		$source_id = self::get_post_source( $post_id );
		if (
			$source_id === null
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
		) {
			return null;
		}

		$post_lang_table = ( new PostTable() )->get_table_name();
		return $wpdb->get_var(
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
	}

	/**
	 * Returns a post's translations.
	 *
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

	/**
	 * Changes a post's language.
	 *
	 * When changing a post's language, the translatable terms and meta are changed to their
	 * translation if they exist, otherwise the term relation is lost and the meta value becomes
	 * empty. The translatable terms are set via the Unbabble Options and the meta keys via the
	 * `ubb_change_language_post_meta_translate_keys` filter, more in-depth description on the
	 * filter documentation.
	 *
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
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
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
	 * @return array Array of post IDs.
	 */
	public static function get_posts_for_source( string $source_id ) : array {
		global $wpdb;
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id as ID
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s",
				$source_id,
			)
		);
		if ( $posts === null ) {
			return [];
		}
		return array_map( fn ( $post ) => $post->ID, $posts );
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
	 * @since 0.0.1
	 *
	 * @param string $post_id ID of the post to delete source.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_post_source( string $post_id ) : bool {
		return delete_post_meta( $post_id, 'ubb_source' );
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
		return in_array( $taxonomy, Options::get_allowed_taxonomies(), true );
	}

	/**
	 * Sets a term language.
	 *
	 * If the language is already set, nothing will happen and it will return `false`. Use the $force
	 * argument to force the language change.
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

		if ( ! in_array( $language, Options::get()['allowed_languages'], true ) ) {
			return false;
		}

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

	/**
	 * Returns a term's language.
	 *
	 * @param  int    $term_id
	 * @return ?string String if the term has a language, null otherwise.
	 */
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

	/**
	 * Sets a term's source.
	 *
	 * A term's source, via a meta entry, is what links it to other terms (translations).
	 *
	 * @since 0.0.1
	 *
	 * @param int    $term_id   Post to set source for.
	 * @param string $source_id Source ID (translation map ID) to set term to.
	 * @param bool   $force     Whether to force the source set. If false (default) and the
	 *                          source is already set, nothing happens.
	 * @return bool True if source was set, false otherwise or if meta insert/update failed.
	 */
	public static function set_term_source( int $term_id, string $source_id, bool $force = false ) : bool {
		if ( $force ) {
			if ( $source_id === LangInterface::get_term_source( $term_id ) ) {
				return true;
			}
			$meta_id = update_term_meta( $term_id, 'ubb_source', $source_id );
		} else {
			$meta_id = add_term_meta( $term_id, 'ubb_source', $source_id, true );
		}
		return (bool) $meta_id;
	}

	/**
	 * Returns the term's source ID.
	 *
	 * @since 0.0.1
	 *
	 * @param int $term_id ID of the term to get source for.
	 * @return ?string String if the source is found, null otherwise.
	 */
	public static function get_term_source( int $term_id ) : ?string {
		$source_id = get_term_meta( $term_id, 'ubb_source', true );;
		if ( empty( $source_id ) ) {
			return null;
		}
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
		global $wpdb;
		$source_id = self::get_term_source( $term_id );
		if (
			$source_id === null
			|| ! in_array( $lang, Options::get()['allowed_languages'], true )
		) {
			return null;
		}

		$term_lang_table = ( new TermTable() )->get_table_name();
		return $wpdb->get_var(
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

		$languages = Options::get()['allowed_languages'];
		$lang_list = [];
		foreach ( $terms as $term ) {
			$term_language = self::get_term_language( $term->term_id );
			if ( ! in_array( $term_language, $languages, true ) ) {
				continue;
			}
			$lang_list[ $term->term_id ] = $term_language;
		}
		return $lang_list;
	}

	/**
	 * Changes a term's language.
	 *
	 *  TODO: What to do when term language changes? How to handle post relationships?
	 *
	 * @since 0.0.1
	 *
	 * @param int    $term_id ID of the term being changed.
	 * @param string $lang    Language code to change term to.
	 * @return bool True if language was changed.
	 */
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

	/**
	 * Returns the terms for a source ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string $source_id The source ID to get translations map.
	 * @return array Array of term IDs.
	 */
	public static function get_terms_for_source( string $source_id ) : array {
		global $wpdb;
		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id
				FROM {$wpdb->termmeta}
				WHERE meta_key = 'ubb_source'
				AND meta_value = %s",
				$source_id,
			)
		);
		if ( $terms === null ) {
			return [];
		}
		return array_map( fn ( $term ) => $term->term_id, $terms );
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
	 * @since 0.0.1
	 *
	 * @param string $term_id ID of the term to delete source.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_term_source( string $term_id ) : bool {
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
		if ( ! is_multisite() ) {
			return true;
		}
		$active_plugins = (array) get_option( 'active_plugins', [] );
		return in_array( 'unbabble/unbabble.php', $active_plugins, true );
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
			! in_array( $lang, Options::get()['allowed_languages'] )
			|| $lang === self::get_current_language()
		) {
			return ( $_SERVER['HTTPS'] ? 'https://' : 'http://' ) .
				$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		// Try to translate posts archive url.
		if ( $wp_the_query->is_posts_page ) {
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
		if ( $wp_the_query->is_post_type_archive() ) {
			add_filter( 'ubb_current_lang', $fn = fn () => $lang );
			$archive_link = get_post_type_archive_link( $wp_the_query->query['post_type'] );
			remove_filter( 'ubb_current_lang', $fn );
			return $archive_link;
		}

		// Try to translate a taxonomies archive url.
		if ( $wp_the_query->is_category() || $wp_the_query->is_tag() || $wp_the_query->is_tax() ) {
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
		if ( $wp_the_query->is_singular() ) {
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
		 * Return a non-empty and string value to bypass uuid generation.
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

		$table = $type === 'post' ? $wpdb->postmeta : $wpdb->termmeta;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(*) FROM $table
				WHERE meta_key = 'ubb_source' AND meta_value = %s",
				$uuid
			)
		);
		if ( 0 !== (int) $count ) {
			return self::get_new_source_id( $type );
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
