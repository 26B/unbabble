<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options;

class QueryVar {
	public function register() {
		\add_filter( 'rest_pre_dispatch', [ $this, 'add_lang_query_arg' ], 10, 3 );
	}

	public function add_lang_query_arg( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		$lang = esc_sql( $request->get_param( 'lang' ) );
		if ( in_array( $lang, Options::get()['allowed_languages'], true ) ) {
			set_query_var( 'lang', $lang );
		}
		return $result;
	}
}
