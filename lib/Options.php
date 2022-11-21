<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * Handler for the `ubb_options` option.
 *
 * @since 0.0.0
 */
class Options {

	public static function get() : array {
		// TODO: Hook docs.
		$options = \apply_filters( 'ubb_options', null );
		if ( is_array( $options ) ) {
			return array_merge(
				[
					'allowed_languages' => [],
					'default_language'  => '',
					'post_types'        => [],
					'taxonomies'        => [],
					'router'            => 'query_var',
				],
				$options
			);
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

	public static function only_one_language_allowed() : bool {
		return count( self::get()['allowed_languages'] ) < 2;
	}

	public static function get_allowed_post_types() : array {
		$options            = self::get();
		$allowed_post_types = $options['post_types'] ?? self::build_default_post_types();
		return is_array( $allowed_post_types ) ? $allowed_post_types : [];
	}

	public static function get_allowed_taxonomies() : array {
		$options            = self::get();
		$allowed_taxonomies = $options['taxonomies'] ?? self::build_default_taxonomies();
		return is_array( $allowed_taxonomies ) ? $allowed_taxonomies : [];
	}

	public static function get_router() : string {
		$router_type = self::get()['router'] ?? '';
		return empty( $router_type ) ? 'query_var' : $router_type;
	}

	public static function get_router_types() : array {
		return [
			'query_var',
			'directory',
		];
	}

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
