<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

class AdminNotices {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
	}

	/**
	 * Add an admin notice when a post has translation for the same language as itself.
	 *
	 * @return void
	 */
	public function duplicate_language() : void {
		$screen = get_current_screen();
		$post   = get_post();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'post'
			|| ! $post instanceof WP_Post
			|| ! in_array( $post->post_type, Options::get_allowed_post_types(), true )
		) {
			return;
		}

		$post_lang    = LangInterface::get_post_language( $post->ID );
		$translations = LangInterface::get_post_translations( $post->ID );
		if ( ! in_array( $post_lang, $translations, true )) {
			return;
		}

		$message = __( 'There is a translation with the same language as this post.', 'unbabble' );
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', esc_html( $message ) );
	}
}
