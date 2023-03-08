<?php

namespace TwentySixB\WP\Plugin\Unbabble\DB;

use TwentySixB\WP\Plugin\Unbabble;

/**
 * Filters related to the database value of the Unbabble options.
 *
 * @since 0.0.10
 */
class Options {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.10
	 */
	public function register() : void {
		\add_action( 'wp_loaded', [ $this, 'update' ] );
	}

	/**
	 * Updates the Unbabble options if the value returned from the filter `ubb_options` is
	 * different from the saved options.
	 *
	 * @since 0.0.10
	 *
	 * @return void
	 */
	public function update() : void {

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

		$options = Unbabble\Options::get();
		if ( $options === $filter_options ) {
			return;
		}

		if ( ! update_option( 'ubb_options', $filter_options ) ) {
			\add_action( 'admin_notices', [ $this, 'option_update_failed_notice' ], PHP_INT_MAX );
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
		do_action( 'ubb_options_updated', $filter_options, $options );
	}

	/**
	 * Add an admin notice when the Unbabble options failed to update via the filter.
	 *
	 * @since 0.0.10
	 *
	 * @return void
	 */
	public function option_update_failed_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with options name */
			esc_html( __( 'Unbabble was not able to update the %s option value following an options value change via the filter.', 'unbabble' ) ),
			'<code>ubb_options</code>'
		);
		printf( '<div class="notice notice-error"><p><b>Unbabble: </b>%s</p></div>', $message );
	}
}
