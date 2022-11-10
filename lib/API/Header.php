<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options;

class Header {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_filter( 'rest_pre_dispatch', [ $this, 'accept_language_header' ], 2, 3 );
	}

	public function accept_language_header( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( ! empty( get_query_var( 'lang', '' ) ) ) {
			return $result;
		}
		$header_value = esc_sql( $request->get_header( 'Accept-Language' ) );
		$lang         = $this->get_existing_accept_language( $header_value );
		if ( ! empty( $lang ) ) {
			set_query_var( 'lang', $lang );
		}
		return $result;
	}

	private function get_existing_accept_language( $header_value ) : string {
		if ( $header_value === '*' ) {
			return ''; // Default language.
		}

		$matches = [];
		if (
			! preg_match(
				'/([a-zA-Z-]{2,5})\s*((?:(?:\,\s*[a-zA-Z-]{2,5}\s*\;\s*q=\d+(?:\.\d*)?\s*)*)*)/',
				$header_value,
				$matches
			)
		) {
			return ''; // Default language.
		}

		$main_lang       = str_replace( '-', '_', $matches[1] );
		$other_langs_str = $matches[2];

		$allowed_languages = Options::get()['allowed_languages'];
		if ( in_array( $main_lang, $allowed_languages, true ) ) {
			return $main_lang;
		}

		if ( empty( $other_langs_str ) || ! str_starts_with( $other_langs_str, ',' ) ) {
			return ''; // Default language.
		}

		$other_langs_str = ltrim( $other_langs_str, ',' );
		$other_langs = explode( ',', $other_langs_str );
		$langs = [];
		foreach ( $other_langs as $lang_data ) {
			list( $lang, $factor ) = explode( ';q=', $lang_data );
			$langs[ str_replace( '-', '_', $lang ) ] = $factor;
		}

		arsort( $langs );

		foreach ( $langs as $lang => $factor ) {
			if ( in_array( $lang, $allowed_languages, true ) ) {
				return $lang;
			}
		}

		return ''; // Default language.
	}
}
