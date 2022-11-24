<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

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
	 * Enqueues admin scripts.
	 *
	 * @since 0.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() : void {
		\wp_enqueue_script( 'ubb-admin', plugin_dir_url( dirname( __FILE__, 1 ) ) . 'src/scripts/ubb-admin.js', [], '0.0.0', true );
	}

	/**
	 * Whitelists Unbabble's query vars.
	 *
	 * @since 0.0.0
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
