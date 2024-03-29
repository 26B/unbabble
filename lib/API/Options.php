<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options as UnbabbleOptions;
use TwentySixB\WP\Plugin\Unbabble\Plugin;

class Options {
	public function __construct( Plugin $plugin, string $namespace ) {
		$this->namespace = $namespace;
	}

	public function register() {
		\register_rest_route(
			$this->namespace,
			'/options',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'submit_options' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $request ) : bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	public function submit_options( \WP_REST_Request $request ) {
		$updated = UnbabbleOptions::update_via_api( $request );
		if ( is_array( $updated ) ) {
			return new \WP_REST_Response( [ 'errors' => $updated ], 500 );
		}

		return new \WP_REST_Response( null, 200 );
	}
}
