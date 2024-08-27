<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * Hooks related to quick edits and terms.
 *
 * @since 0.5.0
 */
class QuickEdit {

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 */
	public function register() : void {

		// Handle quick edit for new tags.
		\add_action( 'create_post_tag', [ $this, 'quick_edit_new_tag' ], PHP_INT_MAX );
	}

	/**
	 * Handle quick edit for new tags.
	 *
	 * @since 0.5.0
	 *
	 * @param int $term_id
	 * @return void
	 */
	public function quick_edit_new_tag( int $term_id ) : void {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		if (
			( $_POST['screen'] ?? '' ) !== 'edit-post'
			|| ( $_POST['action'] ?? '' ) !== 'inline-save'
			|| ! in_array(
				$term->name,
				array_map( fn ( $name ) => trim( $name ), explode( ',', $_POST['tax_input']['post_tag'] ?? '' ) ),
				true
			)
		) {
			return;
		}

		$post_id = (int) ( $_POST['post_ID'] ?? '' );
		if ( ! $post_id ) {
			return;
		}

		$post_lang = LangInterface::get_post_language( $post_id );
		if ( ! $post_lang ) {
			return;
		}

		$status = LangInterface::set_term_language( $term_id, $post_lang );
		if ( $status === false ) {
			// TODO: how to handle error here?
			return;
		}
	}
}
