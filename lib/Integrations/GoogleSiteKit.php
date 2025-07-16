<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

/**
 * Hooks for integrating with GoogleSiteKit.
 *
 * @since Unreleased
 */
class GoogleSiteKit {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased
	 */
	public function register() {
		if ( ! \is_plugin_active( 'google-site-kit/google-site-kit.php' ) ) {
			return;
		}

		add_filter( 'googlesitekit_inline_base_data', [ $this, 'fix_admin_url_in_assets' ] );
	}

	/**
	 * Fix the admin URL in the inline data for Google Site Kit assets.
	 *
	 * The adminURL in the assets can contain a 'lang' query parameter but is then followed by
	 * a trailing slash. The frontend then builds on this URL as if it was a path. The trailing
	 * slash is also ensured to be there in the frontend before building the rest of the URL.
	 * This creats a broken URL, so here we remove our language query parameter to fix it.
	 *
	 * @since Unreleased
	 *
	 * @param array $inline_data The inline data array.
	 * @return array The modified inline data array.
	 */
	public function fix_admin_url_in_assets( array $inline_data ) : array {
		$admin_url = $inline_data['adminURL'] ?? '';
		if ( empty( $admin_url ) ) {
			return $inline_data;
		}

		if ( str_ends_with( $admin_url, '/' ) ) {
			$admin_url = substr( $admin_url, 0, -1 );
		}

		$args = parse_url( $admin_url, PHP_URL_QUERY );

		if ( empty( $args ) ) {
			return $inline_data;
		}

		$base_admin_url = str_replace( '?' . $args, '', $admin_url );

		$args_parts = explode( '&', $args );
		$new_args_parts = [];
		foreach ( $args_parts as $part ) {
			if ( str_starts_with( $part, 'lang=' ) ) {
				continue;
			}

			$new_args_parts[] = $part;
		}

		$new_args = implode( '&', $new_args_parts );

		if ( ! empty( $new_args ) ) {
			$base_admin_url = $base_admin_url . '?' . $new_args;
		}

		$inline_data['adminURL'] = trailingslashit( $base_admin_url );

		return $inline_data;
	}
}
