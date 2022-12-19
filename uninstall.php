<?php
/**
 * Fired when the plugin is uninstalled
 *
 * @since   0.0.1
 * @package 26b
 */

// TODO: Clean tables and metas.

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
