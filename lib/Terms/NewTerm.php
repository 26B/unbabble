<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks related to new terms.
 *
 * @since 0.0.1
 */
class NewTerm {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		$taxonomies = array_intersect( \get_taxonomies(), Options::get_allowed_taxonomies() );
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
		if ( ( $_POST['action'] ?? '' ) !== 'add-category' ) {
			return;
		}

		LangInterface::set_term_language( $term_id, LangInterface::get_current_language() );
	}
}
