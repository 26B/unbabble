<?php

namespace TwentySixB\WP\Plugin\Unbabble;

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
		// TODO: Hook docs.
		$options = \apply_filters( 'ubb_options', null );
		if ( is_array( $options ) ) {
			return array_merge( self::DEFAULT, $options );
		}

		$options = \get_option( 'ubb_options' );
		if ( $options ) {
			return $options;
		}

		$wp_locale = \get_locale();
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
