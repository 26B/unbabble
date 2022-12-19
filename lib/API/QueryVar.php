<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks for handling query args in API requests.
 *
 * @since 0.0.1
 */
class QueryVar {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}
		\add_filter( 'rest_pre_dispatch', [ $this, 'set_lang_query_var' ], 1, 3 );
	}

	/**
	 * Sets the lang query var from the API request if necessary.
	 *
	 * Query arguments from API requests are not automatically set to query vars so we need to
	 * check for the lang argument and set it as a query var.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed           $result
	 * @param WP_REST_Server  $server
	 * @param WP_Rest_Request $request
	 * @return mixed
	 */
	public function set_lang_query_var( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		$lang = str_replace( '-', '_', esc_sql( $request->get_param( 'lang' ) ) );
		if (
			in_array( $lang, Options::get()['allowed_languages'], true )
			&& empty( get_query_var( 'lang', '' ) )
		) {
			set_query_var( 'lang', $lang );
		}
		return $result;
	}
}
