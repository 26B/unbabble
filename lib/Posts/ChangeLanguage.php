<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks related to changing a posts language.
 *
 * @since 0.0.1
 */
class ChangeLanguage {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Priority needs to be before the set post language when post is first saved.
		\add_action( 'save_post', [ $this, 'change_language' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Change the language of the saved post.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function change_language( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( ( $_POST['post_type'] ?? '' ) !== $post_type || $post_id !== (int) $_POST['post_ID'] ) {
			return;
		}

		$ubb_lang = $_POST['ubb_lang'] ?? '';

		if ( empty( LangInterface::get_post_language( $post_id ) ) ) {
			return;
		}

		$status = LangInterface::change_post_language( $post_id, $ubb_lang );
		// TODO: show admin notice about translation with that language already existing and needing to disconnect a previous one.
		if ( $status === false ) {
			// TODO:
		}
	}
}
