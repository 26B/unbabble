<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;


/**
 * For hooks related to creating a translations from an existing term.
 *
 * @since 0.0.0
 */
class CreateTranslation {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'saved_term', [ $this, 'create_and_redirect' ], PHP_INT_MAX, 4 );
	}

	public function create_and_redirect( int $term_id, int $tt_id, string $taxonomy, bool $update ) : void {
		if ( ! $update ) {
			return;
		}
		if ( ! in_array( $taxonomy, Options::get_allowed_taxonomies(), true ) ) {
			return;
		}
		if ( ! ( $_POST['ubb_save_create'] ?? false ) ) {
			return;
		}
		$_POST['ubb_save_create'] = false; // Used to stop recursion and stop saving in the LangMetaBox.php.

		// Language to set to the new term.
		$lang_create = $_POST['ubb_create'] ?? '';
		if (
			empty( $lang_create )
			|| ! in_array( $lang_create, Options::get()['allowed_languages'] )
			// TODO: check if term_id has this language already
		) {
			// TODO: What else to do when this happens.
			error_log( print_r( 'CreateTranslation - lang create failed', true ) );
			return;
		}

		// TODO: Check if name already exists
		$new_term = wp_insert_term(
			"({$lang_create})-" . get_term( $term_id )->name . rand(0,10000), //FIXME:
			$taxonomy,
			[ 'slug' => get_term( $term_id )->slug . "-{$lang_create}", ]
		);

		if ( $new_term instanceof WP_Error ) {
			error_log( print_r( 'CreateTranslation - New term error', true ) );
			// TODO: How to show error.
			return;
		}

		$new_term_id = $new_term['term_id'];

		// Set language in the custom term lang table.
		if ( ! LangInterface::set_term_language( $new_term_id, $lang_create ) ) {
			error_log( print_r( 'CreateTranslation - language set failed', true ) );
			// TODO: What else to do when this happens.
			return;
		}

		$source_id = LangInterface::get_term_source( $term_id );
		error_log( print_r( 'Source -' . $source_id, true ) );

		// If first translations. set source on the original term.
		if ( ! $source_id ) {
			$source_id = $term_id;
			if ( ! LangInterface::set_term_source( $term_id, $term_id ) ) {
				error_log( print_r( 'CreateTranslation - set source original failed', true ) );
				// TODO: What to do when this happens.
				return;
			}
		}

		if ( ! LangInterface::set_term_source( $new_term_id, $source_id ) ) {
			error_log( print_r( 'CreateTranslation - set source on translation failed', true ) );
			// TODO: What to do when this happens.
			return;
		}

		error_log( print_r( get_edit_term_link( $new_term_id, $taxonomy, '&' ), true ) );
		wp_safe_redirect( get_edit_term_link( $new_term_id, $taxonomy, '&' ) . "&lang={$lang_create}", 302, 'Unbabble' );
		exit;
	}
}