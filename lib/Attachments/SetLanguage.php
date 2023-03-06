<?php

namespace TwentySixB\WP\Plugin\Unbabble\Attachments;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks for setting the language on attachments.
 *
 * @since 0.0.1
 */
class SetLanguage {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		if ( ! in_array( 'attachment', Options::get_allowed_post_types(), true ) ) {
			return;
		}

		\add_action( 'add_attachment', [ $this, 'set_language_on_attachment' ] );
	}

	/**
	 * Set language for an attachment.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function set_language_on_attachment( int $post_id ) : void {
		$curr_lang = LangInterface::get_current_language();
		LangInterface::set_post_language( $post_id, $curr_lang, true );
	}
}
