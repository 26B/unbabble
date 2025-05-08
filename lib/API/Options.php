<?php

namespace TwentySixB\WP\Plugin\Unbabble\API;

use TwentySixB\WP\Plugin\Unbabble\Options as UnbabbleOptions;
use TwentySixB\WP\Plugin\Unbabble\Plugin;

/**
 * Options API.
 *
 * @since 0.2.0
 */
class Options {

	/**
	 * The namespace.
	 *
	 * @since Unreleased
	 * @var string
	 */
	protected $namespace;

	/**
	 * @since 0.2.0
	 */
	public function __construct( Plugin $plugin, string $namespace ) {
		$this->namespace = $namespace;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0 Add register for options update route.
	 * @since 0.2.0
	 */
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

		\register_rest_route(
			$this->namespace,
			'/options/update',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_options' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}


	/**
	 * Check if the user has the right to update the options.
	 *
	 * @since 0.2.0
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ) : bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Submit options.
	 *
	 * @since 0.5.0 Improve response with new options and canUpdate.
	 * @since 0.2.0
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function submit_options( \WP_REST_Request $request ) {
		$updated = UnbabbleOptions::update_via_api( $request );
		if ( is_array( $updated ) ) {
			return new \WP_REST_Response( [ 'errors' => $updated ], 500 );
		}

		// Need to fetch new option values.
		UnbabbleOptions::clear_static_cache();

		return new \WP_REST_Response(
			[
				'options'            => UnbabbleOptions::get(),
				'has_manual_changes' => UnbabbleOptions::has_manual_changes(),
			],
			200
		);
	}

	/**
	 * Update options with filter values.
	 *
	 * @since 0.5.0
	 *
	 * @return \WP_REST_Response
	 */
	public function update_options() {
		if ( ! UnbabbleOptions::has_filter_settings() ) {
			return new \WP_REST_Response( [ 'errors' => [] ], 404 );
		}

		$updated = UnbabbleOptions::update();
		if ( ! $updated ) {
			// TODO: errors
			return new \WP_REST_Response( [ 'errors' => [] ], 500 );
		}

		UnbabbleOptions::clear_static_cache();

		return new \WP_REST_Response(
			[
				'options'            => UnbabbleOptions::get(),
				'has_manual_changes' => UnbabbleOptions::has_manual_changes(),
			],
			200
		);
	}
}
