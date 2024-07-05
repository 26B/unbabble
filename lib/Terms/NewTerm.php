<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Error;

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
		add_action( 'admin_init', [ $this, 'add_actions_for_term_create' ] );
		add_action( 'rest_api_init', [ $this, 'add_actions_for_term_create' ] );

		// Check if a term's slug already existed when being inserted due to the language filter applied to terms.
		\add_filter( 'pre_insert_term', [ $this, 'check_term_slug_exists' ], 10, 3 );
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
	 * Return WP_Error if the term being created has a slug that already exists.
	 *
	 * This check is needed to circumvent the language filter in terms, to prevent for false
	 * positives when creating terms with the same slug in the backoffice.
	 *
	 * @param string|WP_Error $term
	 * @param string          $taxonomy
	 * @param array|string    $args
	 * @return string|WP_Error
	 */
	public function check_term_slug_exists( $term, $taxonomy, $args ) {
		global $wpdb;

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$args = wp_parse_args( $args );

		if ( empty( $args['slug'] ?? '' ) ) {
			$slug = sanitize_title( wp_unslash( $term ) );
		} else {
			$slug = $args['slug'];
		}

		$term_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.term_id
					FROM $wpdb->terms AS t
					INNER JOIN $wpdb->term_taxonomy AS tt ON ( tt.term_id = t.term_id )
					WHERE t.slug = %s AND tt.taxonomy = %s LIMIT 1",
				$slug,
				$taxonomy,
			)
		);

		if ( $term_exists ) {
			return new WP_Error( 'term_exists', __( 'A term with the name provided already exists in this taxonomy.' ), $term_exists );
		}

		return $term;
	}
}
