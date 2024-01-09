<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\Admin\Admin;
use TwentySixB\WP\Plugin\Unbabble\Validation\Validator;

/**
 * Handler for the `ubb_options` option.
 *
 * @since 0.0.1
 */
class Options {

	static private $options = null;

	public function register() : void {
		\add_action( 'wp_loaded', [ self::class, 'update' ] );
	}

	/**
	 * Returns the Unbabble options.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public static function get() : array {
		if ( self::$options !== null ) {
			return self::$options;
		}

		$defaults = self::defaults();
		$options  = \get_option( 'ubb_options' );

		if ( ! is_array( $options ) ) {
			self::$options = $defaults;
			return $defaults;
		}

		$options = wp_parse_args( $options, $defaults );

		$options = self::standardize( $options );

		$errors = self::validate( $options );
		if ( $errors ) {
			\add_filter( 'admin_notices', fn () => ( new Admin() )->invalid_options_notice( __( 'loading options', 'unbabble' ), $errors ), 0 );
			self::$options = $defaults;
			return $defaults;
		}

		self::$options = $options;
		return $options;
	}

	public static function validate( array $options ) : array {
		$validator = new Validator(
			[
				'allowed_languages' => [ 'string_array', 'not_empty' ],
				'default_language'  => [ 'string', 'not_empty' ],
				'hidden_languages'  => [ 'string_array' ],
				'post_types'        => [ 'string_array' ],
				'taxonomies'        => [ 'string_array' ],
				'router'            => [ 'string', 'in:query_var,directory' ],
				'router_options'    => [ 'array' ],
			]
		);

		if ( ! $validator->validate( $options ) ) {
			return $validator->errors();
		}

		$errors = [];
		if ( ! in_array( $options['default_language'], $options['allowed_languages'], true ) ) {
			$errors['default_language'][] = \__( 'Default not in allowed languages.', 'unbabble' );
		}

		if ( in_array( $options['default_language'], $options['hidden_languages'], true ) ) {
			$errors['hidden_languages'][] = \__( 'Default in hidden languages.', 'unbabble' );
		}

		if ( empty( array_diff( $options['allowed_languages'], $options['hidden_languages'] ) ) ) {
			$errors['hidden_languages'][] = \__( 'Hidden languages will remove all the allowed languages.', 'unbabble' );
		}

		return $errors;
	}

	/**
	 * Updates the Unbabble options if the value returned from the filter `ubb_options` is
	 * different from the saved options.
	 *
	 * @since 0.0.11
	 *
	 * @return void
	 */
	public static function update() : void {

		/**
		 * Filters the Unbabble options array.
		 *
		 * The options returned from this filter will be saved to the option `ubb_options`.
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
		$filter_options = \apply_filters( 'ubb_options', null );
		if ( ! is_array( $filter_options ) ) {
			return;
		}

		$filter_options = \wp_parse_args( $filter_options, self::defaults() );

		$filter_options = self::standardize( $filter_options );

		$errors = self::validate( $filter_options );
		if ( $errors ) {
			// TODO: shows up twice if both the loading and the update are invalid.
			\add_filter( 'admin_notices', fn () => ( new Admin() )->invalid_options_notice( \__( 'updating options', 'unbabble' ), $errors ), 0 );
			return;
		}

		$options = self::get();
		if ( $options === $filter_options ) {
			return;
		}

		if ( ! \update_option( 'ubb_options', $filter_options ) ) {
			\add_action( 'admin_notices', [ new Admin(), 'options_update_failed_notice' ], 0 );
			return;
		}

		/**
		 * Fires after the Unbabble options have been updated in the database after a difference
		 * was detected between the value in the database and the value returned from the
		 * `ubb_options` filter.
		 *
		 * @param array $options_value     The updated options.
		 * @param array $old_options_value The old options.
		 */
		\do_action( 'ubb_options_updated', $filter_options, $options );

		\add_action( 'admin_notices', [ new Admin(), 'options_updated' ], 0 );
	}

	public static function update_via_api( \WP_REST_Request $request ) {
		$body = json_decode( $request->get_body(), true );
		$new_options = self::build_options_from_api( $body );

		$errors = self::validate( $new_options );
		if ( $errors ) {
			return $errors;
		}

		$current_options = self::get();
		if ( $current_options === $new_options ) {
			return true;
		}

		if ( ! \update_option( 'ubb_options', $new_options ) ) {
			return []; //TODO: errors
		}

		return true;
	}

	/**
	 * Default option values.
	 *
	 * @since 0.0.11
	 *
	 * @return array
	 */
	public static function defaults() : array {
		\add_filter( 'ubb_stop_switch_locale', '__return_true' );
		$wp_locale = \get_locale();
		\remove_filter( 'ubb_stop_switch_locale', '__return_true' );
		return [
			'allowed_languages' => [ $wp_locale ],
			'default_language'  => $wp_locale,
			'hidden_languages'  => [],
			'post_types'        => [],
			'taxonomies'        => [],
			'router'            => 'query_var',
			'router_options'    => [],
		];
	}

	/**
	 * Returns whether almost all of Unbabble's filters and processes should be running.
	 *
	 * TODO: We should check at the earliest if this is defined and static save it to prevent it being set in the middle of the process.
	 * TODO: move to lang interface.
	 *
	 * @since 0.0.1
	 * @return bool If it's a single site: false if the idle constant is defined as true, true
	 *              otherwise. If it's a multisite: false if the idle constant is an array and
	 *              contains the current blog id.
	 */
	public static function should_run_unbabble() : bool {
		if ( ! defined( 'UNBABBLE_IDLE' ) ) {
			return true;
		}

		if ( ! is_multisite() ) {
			return ! UNBABBLE_IDLE;
		}

		if ( ! is_array( UNBABBLE_IDLE ) ) {
			return true;
		}

		return ! in_array( get_current_blog_id(), UNBABBLE_IDLE, true );
	}

	/**
	 * Returns the router type.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public static function get_router() : string {
		return self::get()['router'];
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

	public static function standardize( array $options ) : array {
		$standard = $options;

		$standard['post_types'] = array_values( $standard['post_types'] );
		$standard['taxonomies'] = array_values( $standard['taxonomies'] );

		return $standard;
	}

	private static function build_options_from_api( array $options ) : array {
		$current_options = self::get();
		$new_options     = [];

		// Languages
		$allowed_languages = array_map(
			fn ( $language ) => $language['language'],
			$options['languages']
		);
		$hidden_languages = array_map(
			fn ( $language ) => $language['language'],
			array_values( array_filter(
				$options['languages'],
				fn ( $language ) => ! empty( $language['hidden'] )
			) )
		);
		$default_language = $options['defaultLanguage'];

		// Routing. Keep same routing options if the router is not directory.
		$router         = $options['routing']['router'];
		$router_options = $current_options['router_options'];
		if ( $router === 'directory' ) {
			$router_options = $options['routing']['router_options'];
			$router_options['directories'][ $default_language ] = '';
			foreach ( array_keys( $router_options['directories'] ) as $language ) {
				if ( is_string( $router_options['directories'][ $language ] ) ) {
					continue;
				}
				$router_options['directories'][ $language ] = '';
			}
		}

		// Types.
		$post_types = $options['postTypes'];
		$taxonomies = $options['taxonomies'];

		$new_options = [
			'allowed_languages' => array_unique( $allowed_languages ),
			'hidden_languages'  => array_unique( $hidden_languages ),
			'default_language'  => $default_language,
			'router'            => $router,
			'router_options'    => $router_options,
			'post_types'        => array_unique( $post_types ),
			'taxonomies'        => array_unique( $taxonomies ),
		];

		$new_options = wp_parse_args(
			$new_options,
			self::defaults()
		);

		$new_options = self::standardize( $new_options );

		return $new_options;
	}
}
