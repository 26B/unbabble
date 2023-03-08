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

		if ( $link_source === null ) {
			$link_source = LangInterface::get_new_term_source_id();
			LangInterface::set_term_source( $link_term->term_id, $link_source, true );
		}

		if ( ! LangInterface::set_term_source( $term_id, $link_source, true ) ) {
			// TODO: show admin notice of failure to change new term source.
			LangInterface::set_term_source( $term_id, $term_source );
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
			|| ! isset( $_POST['ubb_link_translation'] )
			|| $_POST['ubb_link_translation'] !== 'unlink'
		) {
			return;
		}

		LangInterface::delete_term_source( $term_id );
	}
}
