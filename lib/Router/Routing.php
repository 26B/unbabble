<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\Options;
use WP;

/**
 * Hooks for the generic routing functionalities.
 *
 * @since 0.0.3
 */
class Routing {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.3
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		// Fix non-empty queries for non-default language homepage.
		add_action( 'parse_request', [ $this, 'remove_lang_from_empty_queries' ] );
	}

	/**
	 * Remove the language query var from otherwise empty queries.
	 *
	 * This is necessary in order for homepages for non-default languages to work. See parse_query
	 * in wp-includes/class-wp-query.php.
	 *
	 * @since 0.0.3
	 *
	 * @param WP $wp
	 * @return void
	 */
	public function remove_lang_from_empty_queries( WP $wp ) : void {
		if (
			count( array_keys( $wp->query_vars ) ) === 1
			&& isset( $wp->query_vars['lang'] )
		) {
			unset( $wp->query_vars['lang'] );
		}
	}
}
