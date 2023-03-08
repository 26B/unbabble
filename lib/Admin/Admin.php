<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * General hooks for the back-office.
 *
 * @since 0.0.1
 */
class Admin {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Admin scripts.
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts'] );

		// Whitelist query vars.
		add_filter( 'query_vars', [ $this, 'whitelist_query_vars' ] );
	}

	/**
	 * Add an admin notice when Unbabble is idling.
	 *
	 * @since 0.0.9
	 *
	 * @return void
	 */
	public function idle_notice() : void {
		$message = sprintf(
			/* translators: %s: Code html with constant name */
			esc_html( __( 'Unbabble is not running due to the constant %s.', 'unbabble' ) ),
			'<code>UNBABBLE_IDLE</code>'
		);
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', $message );
	}

	/**
	 * Enqueues admin scripts.
	 *
	 * @since 0.0.6
	 *
	 * @return void
	 */
	public function enqueue_scripts() : void {
		\wp_enqueue_script( 'ubb-admin', plugin_dir_url( dirname( __FILE__, 1 ) ) . 'src/scripts/ubb-admin.js', [], '0.0.6', true );
	}

	/**
	 * Whitelists Unbabble's query vars.
	 *
	 * @since 0.0.6
	 *
	 * @param  array $query_vars Whitelist of query vars.
	 * @return array
	 */
	public function whitelist_query_vars( array $query_vars ) : array {
		$query_vars[] = 'lang';
		$query_vars[] = 'ubb_source';
		return $query_vars;
	}
}
