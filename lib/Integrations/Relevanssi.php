<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\Posts\LangFilter;

class Relevanssi {
	public function register() {
		\add_filter( 'relevanssi_where', [ $this, 'relevanssi_where' ] );
	}

	public function relevanssi_where( string $query_restrictions ) : string {
		$query_restrictions .= sprintf(
			" AND ( relevanssi.doc IN (
				%s
			) )",
			( new LangFilter() )->get_lang_filter_where_query()
		);
		return $query_restrictions;
	}
}
