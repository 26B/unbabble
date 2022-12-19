<?php

namespace TwentySixB\WP\Plugin\Unbabble\Integrations;

use TwentySixB\WP\Plugin\Unbabble\Options;

class AdvancedCustomFieldsPro {
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		add_filter( 'option_acf_pro_license', [ $this, 'change_home_url' ] );
	}

	/**
	 * Change the home url in the acf pro license.
	 *
	 * If the home url is not fixed, then the acf deactivates and activates itself which takes a
	 * long time when changing languages in the backoffice.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $pro_license
	 * @return mixed
	 */
	public function change_home_url( $pro_license ) {
		$decoded = maybe_unserialize( base64_decode( $pro_license ) );
		if ( ! is_array( $decoded ) || ! isset( $decoded['url'] ) ) {
			return $pro_license;
		}
		$decoded['url'] = get_home_url();
		return base64_encode( maybe_serialize( $decoded ) );
	}
}
