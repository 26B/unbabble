<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Term;

/**
 * For hooks related to admin notices for posts.
 *
 * @since 0.0.1
 */
class AdminNotices {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'terms_missing_language' ], PHP_INT_MAX );
	}

	/**
	 * Add an admin notice when a term has translation for the same language as itself.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function duplicate_language() : void {
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'term'
			|| ! isset( $_GET['tag_ID'], $_GET['taxonomy'] )
		) {
			return;
		}

		$term = get_term( $_GET['tag_ID'], $_GET['taxonomy'] );
		if (
			! $term instanceof WP_Term
			|| ! in_array( $term->taxonomy, Options::get_allowed_taxonomies(), true )
		) {
			return;
		}

		$term_lang    = LangInterface::get_term_language( $term->term_id );
		$translations = LangInterface::get_term_translations( $term->term_id );
		if ( ! in_array( $term_lang, $translations, true )) {
			return;
		}

		$message = __( 'There is a translation with the same language as this term.', 'unbabble' );
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Adds an admin notice for when there's terms with missing languages or with an unknown language.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function terms_missing_language() : void {
		global $wpdb;
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'edit-tags'
			|| ! current_user_can( 'manage_options' )
			|| ! isset( $_GET['taxonomy'] )
		) {
			return;
		}

		$taxonomy = $_GET['taxonomy'];

		if ( ! in_array( $taxonomy, Options::get_allowed_taxonomies(), true ) ) {
			return;
		}

		$allowed_languages  = implode( "','", Options::get()['allowed_languages'] );
		$translations_table = ( new TermTable() )->get_table_name();
		$bad_terms          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT T.term_id
				FROM {$wpdb->terms} as T
				INNER JOIN {$wpdb->term_taxonomy} as TT ON (T.term_id = TT.term_id)
				WHERE T.term_id NOT IN (
					SELECT term_id
					FROM {$translations_table} as TR
					WHERE TR.locale IN ('{$allowed_languages}')
				) AND TT.taxonomy = %s",
				esc_sql( $taxonomy )
			)
		);

		if ( count( $bad_terms ) === 0 ) {
			return;
		}

		// TODO: link to actions.
		$message = _n(
			'There is %1$s term without language or with an unknown language. Go to (link) to see possible actions.',
			'There are %1$s terms without language or with an unknown language. Go to (link) to see possible actions.',
			count( $bad_terms ),
			'unbabble'
		);
		printf(
			'<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>',
			esc_html( sprintf( $message, count( $bad_terms ) ) )
		);
	}
}
