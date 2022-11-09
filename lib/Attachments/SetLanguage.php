<?php

namespace TwentySixB\WP\Plugin\Unbabble\Attachments;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

class SetLanguage {
	public function register() {
		if ( ! in_array( 'attachment', Options::get_allowed_post_types(), true ) ) {
			return;
		}
		\add_action( 'add_attachment', [ $this, 'set_language_on_attachment' ] );
	}

	public function set_language_on_attachment( int $post_id ) : void {
		$curr_lang = LangInterface::get_current_language();
		LangInterface::set_post_language( $post_id, $curr_lang, true );
	}
}
