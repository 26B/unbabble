<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Term;

class AdminNotices {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
	}

	/**
	 * Add an admin notice when a term has translation for the same language as itself.
	 *
	 * @return void
	 */
	public function duplicate_language() : void {
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'term'
			|| ! isset( $_GET['tag_ID'], $_GET['taxonomy'] )
		) {
			return;
		}

		$term = get_term( $_GET['tag_ID'], $_GET['taxonomy'] );
		if (
			! $term instanceof WP_Term
			|| ! in_array( $term->taxonomy, Options::get_allowed_taxonomies(), true )
		) {
			return;
		}

		$term_lang    = LangInterface::get_term_language( $term->term_id );
		$translations = LangInterface::get_term_translations( $term->term_id );
		if ( ! in_array( $term_lang, $translations, true )) {
			return;
		}

		$message = __( 'There is a translation with the same language as this term.', 'unbabble' );
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', esc_html( $message ) );
	}
}
