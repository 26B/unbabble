<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to creating a translations from an existing post.
 *
 * @since 0.0.0
 */
class ChangeLanguage {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'save_post', [ $this, 'change_language' ], PHP_INT_MAX );
	}

	public function change_language( int $post_id ) : void {
		$ubb_lang = $_POST['ubb_lang'] ?? '';

		$status = LangInterface::change_post_language( $post_id, $ubb_lang );

		// TODO: show admin notice about translation with that language already existing and needing to disconnect a previous one.
		if ( $status === false ) {
			// TODO:
		}
	}
}
