<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options;

class QueryVar {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_filter( 'rest_pre_dispatch', [ $this, 'add_lang_query_arg' ], 1, 3 );
	}

	public function add_lang_query_arg( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
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
