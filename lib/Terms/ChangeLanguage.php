<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\Admin\Admin;
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

		// Handle terms with duplicate slugs when language is changed.
		\add_action( 'ubb_term_language_change', [ $this, 'duplicate_term_language_change' ], 10, 3 );

		// Add term updated messages for language changes.
		\add_filter( 'term_updated_messages', [ $this, 'add_term_updated_messages' ] );
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

	/**
	 * Handle terms with duplicate slugs when language is changed.
	 *
	 * @since 0.5.0
	 *
	 * @param int $term_id
	 * @param string $lang
	 * @param string|null $old_lang
	 * @return void
	 */
	public function duplicate_term_language_change( int $term_id, string $lang, ?string $old_lang ) : void {
		$term = \get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// Get the current language to revert to after checking for duplicates.
		$current_lang = LangInterface::get_current_language();

		// Change language for query lang filter.
		LangInterface::set_current_language( $lang );

		// Check term has a duplicate in its new language.
		$term_query = new \WP_Term_Query(
			[
				'slug'       => $term->slug,
				'taxonomy'   => $term->taxonomy,
				'exclude'    => [ $term_id ],
				'hide_empty' => false,
				'number'     => 1,
			]
		);

		$terms = $term_query->get_terms();
		if ( empty ( $terms ) ) {
			LangInterface::set_current_language( $current_lang );
			return;
		}

		// Since there is at least one duplicate, make a unique term slug.
		$unique_term_slug = \wp_unique_term_slug( $term->slug, $term );

		// Update the term slug.
		$success = \wp_update_term( $term_id, $term->taxonomy, [ 'slug' => $unique_term_slug ] );

		// Reset the current language.
		LangInterface::set_current_language( $current_lang );

		// If the term slug was successfully updated, select term edit message of successful change.
		if ( $success ) {
			\add_filter( 'redirect_term_location', function ( string $location ) use ( $term_id ) {
				return \add_query_arg( 'message', 50, $location );
			} );
			return;
		}

		// Select term edit message of failed change.
		\add_filter( 'redirect_term_location', function ( string $location ) use ( $term_id ) {
			/**
			 * TODO: Can't turn it into an error message due to how edit-tag-form.php handles
			 * error messages. Would only work if we override the '5' message.
			 */
			return \add_query_arg( 'message', 51, $location );
		} );
	}

	/**
	 * Add term updated messages for language changes.
	 *
	 * TODO: should this be in another class?
	 *
	 * @since 0.5.0
	 *
	 * @param array $messages
	 * @return array
	 */
	public function add_term_updated_messages( array $messages ) : array {
		global $taxonomy;
		if ( empty ( $taxonomy ) || ! LangInterface::is_taxonomy_translatable( $taxonomy ) ) {
			return $messages;
		}

		$key = '_item';
		if ( $taxonomy === 'category' || $taxonomy === 'post_tag' ) {
			$key = $taxonomy;
		}

		$messages[ $key ][ 50 ] = __( 'Unbabble: Slug updated to avoid duplicate.', 'unbabble' );
		$messages[ $key ][ 51 ] = __( 'Unbabble: Slug failed to update to avoid duplicate.', 'unbabble' );
		return $messages;
	}
}
