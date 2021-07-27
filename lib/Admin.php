<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * The dashboard-specific functionality of the plugin
 *
 * @since 0.0.0
 */
class Admin {

	/**
	 * The plugin's instance.
	 *
	 * @since  0.0.0
	 * @access private
	 * @var    Plugin
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.0.0
	 * @param Plugin $plugin This plugin's instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		\add_action( 'admin_init', [ $this, 'action_callback' ] );
	}

	/**
	 * My action callback.
	 *
	 * @since  0.0.0
	 * @return void
	 */
	public function action_callback() {}
}
