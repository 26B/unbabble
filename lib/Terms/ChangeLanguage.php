<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks related to changing a terms language.
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
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'saved_term', [ $this, 'change_language' ], PHP_INT_MAX );
	}

	/**
	 * Change the language of the saved term.
	 *
	 * @since 0.0.1
	 *
	 * @param int $term_id
	 * @return void
	 */
	public function change_language( int $term_id ) : void {
		// TODO: Finish this.
		return;

		$ubb_lang = \sanitize_text_field( $_POST['ubb_lang'] ?? '' );

		$status = LangInterface::change_term_language( $term_id, $ubb_lang );

		// TODO: show admin notice about translation with that language already existing and needing to disconnect a previous one.
		if ( $status === false ) {
			// TODO:
		}
	}
}
