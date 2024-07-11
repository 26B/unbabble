<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

class YoastSEO {
	public function register() {
		add_filter( 'ubb_proxy_options', fn ( $options ) => array_merge(
			$options,
			[ 'wpseo_titles' ]
		) );
	}
}
