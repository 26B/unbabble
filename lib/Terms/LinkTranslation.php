<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to linking and unlinking translations between existing terms or translation
 * sets of terms.
 *
 * @since 0.0.1
 */
class LinkTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'saved_term', [ $this, 'link_translations' ], PHP_INT_MAX, 4 );
		\add_action( 'saved_term', [ $this, 'unlink' ], PHP_INT_MAX, 4 );
	}

	/**
	 * Link translations for term $term_id.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 * @param bool   $update
	 * @return void
	 */
	public function link_translations( int $term_id, int $tt_id, string $taxonomy, bool $update ) : void {
		$allowed_taxonomies = Options::get_allowed_taxonomies();
		if (
			! in_array( $taxonomy, $allowed_taxonomies, true )
			|| ! isset( $_POST['ubb_link_translation'] )
			|| ! is_numeric( $_POST['ubb_link_translation'] )
		) {
			return;
		}

		$link_term = get_term( \sanitize_text_field( $_POST['ubb_link_translation'] ) );
		if (
			$link_term === null
			|| ! in_array( $link_term->taxonomy, $allowed_taxonomies, true )
			|| $link_term->taxonomy !== $taxonomy
		) {
			return;
		}

		$term_source = LangInterface::get_term_source( $term_id );
		$link_source = LangInterface::get_term_source( $link_term->term_id );

		// If none of them have source, use the lowest ID and set that as source for both.
		if ( $term_source === null && $link_source === null ) {
			$source_id = LangInterface::get_new_term_source_id();
			LangInterface::set_term_source( $term_id, $source_id );
			LangInterface::set_term_source( $link_term->term_id, $source_id );
			return;
		}

		// If the term has source but not the link, set source in link to term source.
		if ( $term_source !== null && $link_source === null ) {
			LangInterface::set_term_source( $link_term->term_id, $term_source );
			return;
		}

		// If the link has source but not the term, set source in term to link source.
		if ( $term_source === null && $link_source !== null ) {
			LangInterface::set_term_source( $term_id, $link_source );
			return;
		}

		// If both of them have a source, use lowest source ID and change occurrences of the other one to that value.
		if ( $term_source !== null && $link_source !== null ) {
			$source_id = min( $term_source, $link_source );
			$terms     = LangInterface::get_terms_for_source( max( $term_source, $link_source ) );
			foreach ( $terms as $term_to_change ) {
				LangInterface::set_term_source( $term_to_change, $source_id, true );
			}
			return;
		}
	}

	/**
	 * Unlink term $term_id from its translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 * @param bool   $update
	 * @return void
	 */
	public function unlink( int $term_id, int $tt_id, string $taxonomy, bool $update ) : void {
		if (
			! $update
			|| ! in_array( $taxonomy, Options::get_allowed_taxonomies(), true )
			|| ! isset( $_POST['ubb_unlink'] )
		) {
			return;
		}

		LangInterface::delete_term_source( $term_id );
	}
}
