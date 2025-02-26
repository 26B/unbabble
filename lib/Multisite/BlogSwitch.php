<?php

namespace TwentySixB\WP\Plugin\Unbabble\Multisite;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to multisite and blog switching.
 *
 * @since Unreleased
 */
class BlogSwitch {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() || ! is_multisite() ) {
			return;
		}

		add_action( 'switch_blog', [ $this, 'switch_blog' ], 10, 3 );
	}

	/**
	 * Set the language correctly when switching and restoring the current blog.
	 *
	 * @since Unreleased
	 *
	 * @param int    $new_blog_id
	 * @param int    $prev_blog_id
	 * @param string $context
	 * @return void
	 */
	public function switch_blog( int $new_blog_id, int $prev_blog_id, string $context ) : void {

		// If the new blog is the same as the previous blog, we don't need to do anything.
		if ( $new_blog_id === $prev_blog_id ) {
			return;
		}

		/**
		 * Filters whether to prevent the options static cache from being cleared upon a blog
		 * switch or restore.
		 *
		 * This filter is applied before clearing the options static cache. Clearing the options
		 * static cache upon a switch/restore means the next time the options are accessed, they
		 * are fetched from the database for the new blog context.
		 *
		 * @param bool $prevent_switch Whether to prevent the blog switch. Default false.
		 * @param int $new_blog_id The ID of the blog to switch to.
		 * @param int $old_blog_id The ID of the current blog.
		 */
		if ( apply_filters( 'ubb_stop_switch_blog', false, $new_blog_id, $prev_blog_id, $context ) ) {
			return;
		}

		// Clear the Options static cache so the next time it is accessed, the options for the new blog will be fetched.
		Options::clear_static_cache();
	}
}
