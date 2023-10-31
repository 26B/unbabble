<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 *
 * @since 0.0.0
 */
class OptionsProxy {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
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
	 * @since 0.0.0
	 *
	 * @param mixed  $pre_option
	 * @param string $option
	 * @return mixed
	 */
	public function pre_get_option_proxy( $pre_option, string $option ) {
		$proxied_options = apply_filters( 'ubb_proxy_options', [] );

		if ( ! is_array( $proxied_options ) || ! in_array( $option, $proxied_options, true ) ) {
			return $pre_option;
		}

		$curr_lang = LangInterface::get_current_language();

		$proxy_name = $this->get_proxy_option_name( $option, $curr_lang );

		$value = get_option( $proxy_name, null );
		return $value === null ? $pre_option : $value;
	}

	/**
	 * Proxy updating of option.
	 *
	 * Also updates the base WordPress option when the language is the default. This is to keep the
	 * default information in the core WordPress incase Unbabble is deactivated/uninstalled.
	 *
	 * @since 0.0.0
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $old_value
	 * @return mixed
	 */
	public function pre_update_option_proxy( $value, string $option, $old_value ) {
		$proxied_options = apply_filters( 'ubb_proxy_options', [] );

		if ( ! is_array( $proxied_options ) || ! in_array( $option, $proxied_options, true ) ) {
			return $value;
		}

		$curr_lang = LangInterface::get_current_language();

		$proxy_name = $this->get_proxy_option_name( $option, $curr_lang );

		update_option( $proxy_name, $value );

		// Update base WordPress option too in case of default language.
		if ( $curr_lang === LangInterface::get_default_language() ) {
			return $value;
		}

		// Return old value so the value does not get updated.
		return $old_value;
	}
}
