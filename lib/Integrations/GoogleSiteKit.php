<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\Router\RoutingResolver;

/**
 * Hooks for integrating with GoogleSiteKit.
 *
 * @since 0.6.0
 */
class GoogleSiteKit {

	/**
	 * Register hooks.
	 *
	 * @since 0.6.0
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
	 * @since 0.6.0
	 *
	 * @param array $inline_data The inline data array.
	 * @return array The modified inline data array.
	 */
	public function fix_admin_url_in_assets( array $inline_data ) : array {
		\remove_filter( 'admin_url', [ RoutingResolver::class, 'admin_url' ], 10 );

		$inline_data['adminURL'] = esc_url_raw( trailingslashit( admin_url() ) );

		\add_filter( 'admin_url', [ RoutingResolver::class, 'admin_url' ], 10 );

		return $inline_data;
	}
}
