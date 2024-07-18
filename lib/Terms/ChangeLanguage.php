<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Term;

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
		\add_action( 'saved_term', [ $this, 'change_language' ], PHP_INT_MAX );
	}

	/**
	 * Change the language of the saved term.
	 *
	 * @since 0.4.5 Add handling for nav_menu language change.
	 * @since 0.0.1
	 *
	 * @param int $term_id
	 * @return void
	 */
	public function change_language( int $term_id ) : void {
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$taxonomy = $term->taxonomy;
		if (
			// Check for nav_menu edits.
			! (
				LangInterface::is_taxonomy_translatable( 'nav_menu' )
				&& $term_id === (int) ( $_POST['menu'] ?? '' )
			)
			&&
			// Check for most taxonomy edits.
			! (
				( $_POST['taxonomy'] ?? '' ) === $taxonomy
				&& $term_id === (int) ( $_POST['tag_ID']  ?? '' )
			)
		) {
			return;
		}

		$ubb_lang = \sanitize_text_field( $_POST['ubb_lang'] ?? '' );

		$status = LangInterface::change_term_language( $term_id, $ubb_lang );

		// TODO: show admin notice about translation with that language already existing and needing to disconnect a previous one.
		if ( $status === false ) {
			// TODO:
			return;
		}

		// Remove relations to posts that do not have the destination language.

		add_filter( 'ubb_use_post_lang_filter', '__return_false' );
		add_filter( 'ubb_use_term_lang_filter', '__return_false' );

		$posts = get_posts(
			[
				'post_type'   => LangInterface::get_translatable_post_types(),
				'numberposts' => -1,
				'post_status' => array_keys( get_post_stati() ),
				'tax_query'   => [
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_id,
					]
				]
			]
		);

		foreach ( $posts as $post ) {
			if ( LangInterface::get_post_language( $post->ID ) !== $ubb_lang ) {
				$status = wp_remove_object_terms( $post->ID, $term_id, $taxonomy );
			}
		}

		remove_filter( 'ubb_use_post_lang_filter', '__return_false' );
		remove_filter( 'ubb_use_term_lang_filter', '__return_false' );
	}
}
