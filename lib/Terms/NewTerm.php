<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;

/**
 * Hooks related to new terms.
 *
 * @since Unreleased Removed `check_term_slug_exists` method.
 * @since 0.0.1
 */
class NewTerm {

	/**
	 * Register hooks.
	 *
	 * @since Unreleased Removed adding `check_term_slug_exists` to the `pre_insert_term` filter.
	 * @since 0.0.1
	 */
	public function register() {
		add_action( 'admin_init', [ $this, 'add_actions_for_term_create' ] );
		add_action( 'rest_api_init', [ $this, 'add_actions_for_term_create' ] );
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
}
