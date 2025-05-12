<?php
/**
 * @wordpress-plugin
 * Plugin Name: Unbabble
 * Plugin URI:  https://github.com/26B/unbabble
 * Description: A new and simple i18n system for WordPress
 * Version:     0.5.13
 * Author:      26B
 * Author URI:  https://26b.io/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: unbabble
 * Domain Path: /languages
 */

// Useful global constants.
define( 'UNBABBLE_PLUGIN_VERSION', '0.5.13' );
define( 'UNBABBLE_PLUGIN_URL', \plugin_dir_url( __FILE__ ) );
define( 'UNBABBLE_PLUGIN_PATH', \plugin_dir_path( __FILE__ ) );
define( 'UNBABBLE_PLUGIN_LIB', UNBABBLE_PLUGIN_PATH . 'lib/' );
define( 'UNBABBLE_PLUGIN_BUILD_URL', UNBABBLE_PLUGIN_URL . 'build/' );
define( 'UNBABBLE_PLUGIN_BUILD_PATH', UNBABBLE_PLUGIN_PATH . 'build/' );

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in lib/Activator.php
 */
\register_activation_hook( __FILE__, '\TwentySixB\WP\Plugin\Unbabble\Activator::activate' );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in lib/Deactivator.php
 */
\register_deactivation_hook( __FILE__, '\TwentySixB\WP\Plugin\Unbabble\Deactivator::deactivate' );

$plugin = new TwentySixB\WP\Plugin\Unbabble\Plugin( 'unbabble', '0.5.13' );

// Initialize plugin.
$plugin->init();

/**
 * Begins execution of the plugin.
 *
 * @since 0.0.1
 */
\add_action( 'plugins_loaded', function () use ( $plugin ) {
	/**
	 * Detect plugin everywhere, including the frontend.
	 *
	 * @see https://developer.wordpress.org/reference/functions/is_plugin_active/
	 */
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$plugin->run();
} );
