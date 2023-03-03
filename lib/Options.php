<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use Exception;

/**
 * Handler for the `ubb_options` option.
 *
 * @since 0.0.1
 */
class Options {

	/**
	 * Default values for options.
	 *
	 * @since 0.0.1
	 *
	 * @var array
	 */
	const DEFAULT = [
		'allowed_languages' => [],
		'default_language'  => '',
		'post_types'        => [],
		'taxonomies'        => [],
		'router'            => 'query_var',
		'router_options'    => [],
	];

	/**
	 * Returns the Unbabble options.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public static function get() : array {

		/**
		 * Filters the Unbabble options array.
		 *
		 * @since 0.0.1
		 *
		 * @param ?array $options {
		 *     Contains all the options for unbabble functionality. If returned null, the options in
		 *     the DB are fetched or a default value is generated. Returned options will be merged
		 *     with a set of empty defaults to prevent missing keys.
		 *
		 *     Expected entries are the following:
		 *     @type string[] $allowed_languages List of allowed language codes for translation and
		 *                                       language switching.
		 *     @type string   $default_language  The code of the default language.
		 *     @type string[] $post_types        List of post type slugs allowed to be translated.
		 *     @type string[] $taxonomies        List of taxonomy slugs allowed to be translated.
		 *     @type string   $router            Routing type. Accepted values are 'query_var' or 'directory'.
		 *     @type array    $router_options    {
		 *         Options related to routing.
		 *
		 *         @type array $directories Map of language codes to directory names.
		 *     }
		 * }
		 */
		$options = \apply_filters( 'ubb_options', null );
		if ( is_array( $options ) ) {
			return array_merge( self::DEFAULT, $options );
		}

		$options = \get_option( 'ubb_options' );
		if ( $options ) {
			return $options;
		}

		add_filter( 'ubb_stop_switch_locale', '__return_true' );
		$wp_locale = \get_locale();
		remove_filter( 'ubb_stop_switch_locale', '__return_true' );
		return [
			'allowed_languages' => [ $wp_locale ],
			'default_language'  => $wp_locale,
			'post_types'        => self::build_default_post_types(),
			'taxonomies'        => self::build_default_taxonomies(),
			'router'            => 'query_var',
		];
	}

	/**
	 * Returns if there is more than one allowed language.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public static function only_one_language_allowed() : bool {
		return count( self::get()['allowed_languages'] ) < 2;
	}

	/**
	 * Returns whether unbabble functionality (filtering, etc) should be running.
	 *
	 * @since 0.0.1
	 * @return bool False if only a single language is allowed or other language plugins are
	 *              active. True otherwise.
	 */
	public static function should_run_unbabble() : bool {
		if ( self::only_one_language_allowed() ) {
			return false;
		}

		// Check for conflicting language plugins.
		$known_language_plugins = [
			'sitepress-multilingual-cms/sitepress.php',
			// TODO: Add others.
		];
		foreach ( $known_language_plugins as $plugin_name ) {
			if ( is_plugin_active( $plugin_name ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns the allowed post types.
	 *
	 * @since 0.0.1
	 *
	 * @return array Array of post type slugs.
	 */
	public static function get_allowed_post_types() : array {
		$options            = self::get();
		$allowed_post_types = $options['post_types'] ?? self::build_default_post_types();
		return is_array( $allowed_post_types ) ? $allowed_post_types : [];
	}

	/**
	 * Returns the allowed taxonomies.
	 *
	 * @since 0.0.1
	 *
	 * @return array Array of taxonomy slugs.
	 */
	public static function get_allowed_taxonomies() : array {
		$options            = self::get();
		$allowed_taxonomies = $options['taxonomies'] ?? self::build_default_taxonomies();
		return is_array( $allowed_taxonomies ) ? $allowed_taxonomies : [];
	}

	/**
	 * Returns the router type.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public static function get_router() : string {
		$router_type = self::get()['router'] ?? '';
		return empty( $router_type ) ? 'query_var' : $router_type;
	}

	/**
	 * Returns possible router types.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public static function get_router_types() : array {
		return [
			'query_var',
			'directory',
		];
	}

	/**
	 * Returns information about the allowed languages.
	 *
	 * @since 0.0.3
	 * @todo Perhaps move this to LangInterface.
	 *
	 * @return array
	 */
	public static function get_languages_info() : array {
		$lang_info = get_option( 'ubb_lang_info', [] );
		$languages = self::get()['allowed_languages'];
		if ( $languages == array_keys( $lang_info ) ) {
			return $lang_info;
		}

		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$wp_translations = wp_get_available_translations();
		$new_lang_info   = [];
		foreach ( $languages as $ubb_lang ) {
			if ( $ubb_lang === 'en_US' ) {
				$new_lang_info[ $ubb_lang ] = [
					'locale'      => 'en_US',
					'native_name' => 'English'
				];
				continue;
			}


			// Shouldn't happen.
			if ( ! isset( $wp_translations[ $ubb_lang ] ) ) {
				$new_lang_info[ $ubb_lang ] = [ 'locale' => $ubb_lang ];
				continue;
			}

			$new_lang_info[ $ubb_lang ]           = $wp_translations[ $ubb_lang ];
			$new_lang_info[ $ubb_lang ]['locale'] = $ubb_lang;
		}

		update_option( 'ubb_lang_info', $new_lang_info );
		return $new_lang_info;
	}

	/**
	 * Builds the default post types allowed for translation.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	private static function build_default_post_types() : array {
		$post_types         = \get_post_types( [], '' );
		$allowed_post_types = [];

		foreach ( $post_types as $post_type ) {
			if ( ! $post_type->public ) {
				continue;
			}
			$allowed_post_types[] = $post_type->name;
		}

		return $allowed_post_types;
	}

	/**
	 * Builds the default taxonomies allowed for translations.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	private static function build_default_taxonomies() : array {
		$taxonomies         = \get_taxonomies( [], '' );
		$allowed_taxonomies = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public || ! $taxonomy->show_ui ) {
				continue;
			}
			$allowed_taxonomies[] = $taxonomy->name;
		}

		return $allowed_taxonomies;
	}
}
