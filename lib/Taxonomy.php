<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * Taxonomy handler.
 *
 * @since 0.0.0
 */
abstract class Taxonomy {

	/**
	 * The plugin's instance.
	 *
	 * @since  0.0.0
	 * @access protected
	 * @var    Plugin
	 */
	protected $plugin;

	/**
	 * The taxonomy slug.
	 *
	 * @since  0.0.0
	 * @access protected
	 * @var    string
	 */
	protected $slug;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.0.0
	 * @param Plugin $plugin This plugin's instance.
	 * @param string $slug   The taxonomy slug.
	 */
	public function __construct( Plugin $plugin, $slug ) {
		$this->plugin = $plugin;
		$this->slug   = $slug;
	}

	/**
	 * Register custom taxonomy.
	 *
	 * @since 0.0.0
	 */
	abstract public function register();
}
