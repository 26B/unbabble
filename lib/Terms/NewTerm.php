<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * Hooks related to new terms.
 *
 * @since 0.4.7 Removed `check_term_slug_exists` method.
 * @since 0.0.1
 */
class NewTerm {

	/**
	 * Register hooks.
	 *
	 * @since 0.4.7 Removed adding `check_term_slug_exists` to the `pre_insert_term` filter.
	 * @since 0.0.1
	 */
	public function register() {
		\add_action( 'admin_init', [ $this, 'add_actions_for_term_create' ] );
		\add_action( 'rest_api_init', [ $this, 'add_actions_for_term_create' ] );

		// Allow for duplicate term slugs in different languages.
		\add_filter( 'wp_insert_term_duplicate_term_check', [ $this, 'allow_duplicates_in_different_languages' ], PHP_INT_MIN, 5 );
	}

	public function add_actions_for_term_create() : void {
		$taxonomies = array_intersect( \get_taxonomies(), LangInterface::get_translatable_taxonomies() );
		foreach ( $taxonomies as $taxonomy ) {
			\add_action( "create_{$taxonomy}", [ $this, 'new_term_ajax' ] );
		}
	}

	/**
	 * Set a new term's language, when its created via via ajax.
	 *
	 * @since 0.0.1
	 *
	 * @param int $term_id
	 * @return void
	 */
	public function new_term_ajax( int $term_id ) : void {
		if (
			! (
				defined( 'DOING_AJAX' )
				&& DOING_AJAX
				&& str_starts_with( $_POST['action'] ?? '' , 'add-' )
			)
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& $_POST['action'] !== 'editpost'
		) {
			return;
		}

		LangInterface::set_term_language( $term_id, LangInterface::get_current_language() );
	}

	/**
	 * Allow for duplicate terms in different languages.
	 *
	 * Stop WordPress from deleting a duplicate term if it's in a different language.
	 *
	 * @since Unreleased
	 *
	 * @param object $duplicate_term
	 * @return object|null
	 */
	public function allow_duplicates_in_different_languages( object $duplicate_term ) : ?object {
		if ( ! isset( $duplicate_term->term_id ) ) {
			return $duplicate_term;
		}

		$original_id   = $duplicate_term->term_id;
		$original_lang = LangInterface::get_term_language( $original_id );
		$lang_create   = $_POST['ubb_lang'] ?? '';

		// Delete the term if it's the same language.
		if ( $original_lang === $lang_create ) {
			return $duplicate_term;
		}

		return null;
	}
}
