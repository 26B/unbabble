<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * Proxy system for options relating to specific languages.
 *
 * @since 0.2.0
 */
class OptionsProxy {

	/**
	 * Register hooks.
	 *
	 * @since 0.2.0
	 */
	public function register() {
		add_filter( 'pre_option', [ $this, 'pre_get_option_proxy' ], 10, 2 );
		add_filter( 'pre_update_option', [ $this, 'pre_update_option_proxy' ], 10, 3 );
	}

	public function get_proxy_option_name( string $option, string $language ) : string {
		return sprintf(
			'ubb_proxy_(%s)_(%s)',
			$language,
			$option
		);
	}

	/**
	 * Proxy loading of option.
	 *
	 * Always try to load the option from the proxy, even with the default language. This handles
	 * cases when default language is changed and the values would otherwise be mixed up.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed  $option
	 * @param string $proxied_option
	 * @return mixed
	 */
	public function pre_get_option_proxy( $option, string $proxied_option ) {
		if ( ! $this->is_option_proxiable( $proxied_option ) ) {
			return $option;
		}

		$curr_lang = LangInterface::get_current_language();

		$proxy_name = $this->get_proxy_option_name( $proxied_option, $curr_lang );

		$value = get_option( $proxy_name, null );
		return $value === null ? $option : $value;
	}

	/**
	 * Proxy updating of option.
	 *
	 * Also updates the base WordPress option when the language is the default. This is to keep the
	 * default information in the core WordPress incase Unbabble is deactivated/uninstalled.
	 *
	 * @since 0.5.13 Add missing WordPress updated option actions.
	 * @since 0.2.0
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $old_value
	 * @return mixed
	 */
	public function pre_update_option_proxy( $value, string $option, $old_value ) {
		if ( ! $this->is_option_proxiable( $option ) ) {
			return $value;
		}

		$curr_lang = LangInterface::get_current_language();

		$proxy_name = $this->get_proxy_option_name( $option, $curr_lang );

		update_option( $proxy_name, $value );

		// Update base WordPress option too in case of default language.
		if ( $curr_lang === LangInterface::get_default_language() ) {
			return $value;
		}

		// Do updated option actions.

		/**
		 * Documentation at wp-includes/option.php.
		 */
		do_action( "update_option_{$option}", $old_value, $value, $option );

		/**
		 * Documentation at wp-includes/option.php.
		 */
		do_action( 'updated_option', $option, $old_value, $value );

		// Return old value so the value does not get updated.
		return $old_value;
	}

	/**
	 * Returns whether an option is proxiable by Unbabble.
	 *
	 * @since 0.4.4 Add handling for dynamic option keys.
	 * @since 0.2.0
	 *
	 * @param string $option
	 * @return bool
	 */
	public function is_option_proxiable( string $option ) : bool {
		/**
		 * Returns options that are to be proxied by Unbabble for each language.
		 *
		 * Options value is save to the database with the following format:
		 * - ubb_proxy_({language})_({option_key})
		 *
		 * The default language value is always saved to the original option key.
		 *
		 * If the option has a dynamic key, like an integer or string, the key should be supplied
		 * to this filter with a %d for dynamic integers or %s for dynamic strings.
		 * Example: An option with the keys 'my_option_0', 'my_option_1', etc, would be passed
		 * as 'my_option_%d' to the filter.
		 *
		 * @since 0.2.0
		 */
		$proxied_options = apply_filters( 'ubb_proxy_options', [] );
		if ( ! is_array( $proxied_options ) ) {
			return false;
		}

		// Match literal options before trying dynamic options.
		if ( in_array( $option, $proxied_options, true ) ) {
			return true;
		}

		// Check for dynamic options.
		foreach ( $proxied_options as $proxied_option ) {
			$count        = 0;
			$option_regex = str_replace(
				[ '%d', '%s' ],
				[ '([0-9]+)', '([a-zA-Z0-9]+)' ],
				$proxied_option,
				$count
			);

			if ( $count === 0 ) {
				continue;
			}

			if ( preg_match( '/^' . $option_regex . '$/', $option ) ) {
				return true;
			}
		}

		return false;
	}
}
