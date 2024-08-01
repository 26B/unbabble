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

	/**
	 * Register hooks.
	 *
	 * @since Unreleased Added conditions for registering the update action.
	 * @since 0.0.11
	 *
	 * @return null
	 */
	public function register() : void {
		\add_action( 'wp_loaded', [ self::class, 'update' ] );
	}

	/**
	 * Returns the Unbabble options.
	 *
	 * @since 0.4.1 - added fetching for multisite blogs.
	 * @since 0.0.1
	 *
	 * @param array $args {
	 * 		Optional arguments.
	 *
	 *		@type int $blog_id The blog id to fetch the options from.
	 * }
	 * @return array
	 */
	public static function get( array $args = [] ) : array {
		$blog_id = is_int( $args['blog_id'] ?? null ) ? $args['blog_id'] : null;

		// Load from static if already loaded, and no blog_id is provided.
		if ( $blog_id === null && self::$options !== null ) {
			return self::$options;
		}

		$defaults = self::defaults();
		$options  = self::fetch_options_value( $blog_id );

		if ( ! is_array( $options ) ) {

			// Save defaults to static if no blog_id is provided.
			if ( $blog_id === null ) {
				self::$options = $defaults;
			}

			return $defaults;
		}

		$options = wp_parse_args( $options, $defaults );

		$options = self::standardize( $options );

		$errors = self::validate( $options );
		if ( $errors ) {
			\add_filter( 'admin_notices', fn () => ( new Admin() )->invalid_options_notice( __( 'loading options', 'unbabble' ), $errors ), 0 );

			// Save defaults to static if no blog_id is provided.
			if ( $blog_id === null ) {
				self::$options = $defaults;
			}

			return $defaults;
		}

		// Save to static if no blog_id is provided.
		if ( $blog_id === null ) {
			self::$options = $options;
		}

		return $options;
	}

	/**
	 * Validates the options.
	 *
	 * @since 0.0.11
	 *
	 * @param array $options
	 * @return array
	 */
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
	 * @since Unreleased Added boolean return. Refactor fetch of options from filter into a separate method. Add update for manual changes option.
	 * @since 0.4.5 Force 'nav_menu' and 'nav_menu_item' to be translatable if one of them is.
	 * @since 0.0.11
	 *
	 * @return bool True on successful update, false otherwise.
	 */
	public static function update() : bool {

		$filter_options = self::get_filter_options();
		if ( is_wp_error( $filter_options ) ) {
			// TODO: shows up twice if both the loading and the update are invalid.
			\add_filter( 'admin_notices', fn () => ( new Admin() )->invalid_options_notice( \__( 'updating options', 'unbabble' ), $filter_options->error_data ), 0 );
			return false;
		} else if ( $filter_options === false ) {
			return false;
		}

		$options = self::get();
		if ( $options === $filter_options ) {
			\update_option( 'ubb_settings_manual_changes', false );
			return true;
		}

		if ( ! \update_option( 'ubb_options', $filter_options ) ) {
			\add_action( 'admin_notices', [ new Admin(), 'options_update_failed_notice' ], 0 );
			return false;
		}

		\update_option( 'ubb_settings_manual_changes', false );

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

		return true;
	}

	/**
	 * Updates the Unbabble options via the API.
	 *
	 * @since Unreleased Add update for manual changes option.
	 * @since 0.2.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return array|bool
	 */
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

		\update_option( 'ubb_settings_manual_changes', true );

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

	/**
	 * Fetch options set on the `ubb_options` filter.
	 *
	 * @since Unreleased
	 *
	 * @return array|bool|\WP_Error
	 */
	public static function get_filter_options() : array|bool|\WP_Error {

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
			return false;
		}

		$filter_options = \wp_parse_args( $filter_options, self::defaults() );

		$filter_options = self::standardize( $filter_options );

		$errors = self::validate( $filter_options );
		if ( $errors ) {
			return new \WP_Error( '', '', $errors );
		}

		/**
		 * If one of `nav_menu_item` and `nav_menu` are translatable but the other one isn't,
		 * force the missing one into the options.
		 */
		if (
			in_array( 'nav_menu_item', $filter_options['post_types'], true )
			&& ! in_array( 'nav_menu', $filter_options['taxonomies'], true )
		) {
			$filter_options['taxonomies'][] = 'nav_menu';

		} else if (
			in_array( 'nav_menu', $filter_options['taxonomies'], true )
			&& ! in_array( 'nav_menu_item', $filter_options['post_types'], true )
		) {
			$filter_options['post_types'][] = 'nav_menu_item';
		}

		return $filter_options;
	}

	/**
	 * Returns whether there are settings passed via the filter.
	 *
	 * @since Unreleased
	 *
	 * @return bool
	 */
	public static function has_filter_settings() : bool {
		if ( ! \has_filter( 'ubb_options' ) ) {
			return false;
		}

		$filter_options = self::get_filter_options();
		if ( \is_wp_error( $filter_options ) || ! is_array( $filter_options ) ) {
			return false;
		}

		return $filter_options !== self::defaults();
	}

	/**
	 * Clear the static cache for options.
	 *
	 * @since Unreleased
	 *
	 * @return null
	 */
	public static function clear_static_cache() {
		self::$options = null;
	}

	/**
	 * Returns whether there are manual changes to the settings.
	 *
	 * @since Unreleased
	 *
	 * @return bool
	 */
	public static function has_manual_changes() : bool {
		return (bool) \get_option( 'ubb_settings_manual_changes', false );
	}

	/**
	 * Build the options array from the API values.
	 *
	 * @since 0.2.0
	 *
	 * @param array $options
	 * @return array
	 */
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

	/**
	 * Fetches the options value from the database.
	 *
	 * @since 0.4.1
	 *
	 * @param  ?int $blog_id
	 * @return mixed
	 */
	private static function fetch_options_value( ?int $blog_id = null ) : mixed {
		global $wpdb;
		if ( $blog_id === null || ! \is_multisite() ) {
			return \get_option( 'ubb_options' );
		}

		switch_to_blog( $blog_id );

		$options = $wpdb->get_results(
			"SELECT option_name, option_value
				FROM {$wpdb->options}
				WHERE option_name IN ('active_plugins','ubb_options')",
			OBJECT_K
		);

		restore_current_blog();

		$active_plugins = maybe_unserialize( $options['active_plugins']->option_value ?? [] );
		if ( ! in_array( 'unbabble/unbabble.php', $active_plugins, true ) ) {
			return null;
		}

		$ubb_options = maybe_unserialize( $options['ubb_options']->option_value ?? [] );
		return empty( $ubb_options ) ? null : $ubb_options;
	}
}
