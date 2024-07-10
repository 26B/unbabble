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
	 * @since Unreleased Add notices for a term missing/unknown languages and an explanation for the missing language filter.
	 * @since 0.0.1
	 */
	public function register() {
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'terms_missing_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'term_missing_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'term_unknown_language' ], PHP_INT_MAX );
		\add_filter( 'admin_notices', [ $this, 'term_missing_language_filter_explanation' ], PHP_INT_MAX );
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
			|| ! LangInterface::is_taxonomy_translatable( $term->taxonomy )
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
	 * @since Unreleased Stop showing when the user is already on the no language filter. Fix link for filter.
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

		// Don't show when the user is already on the no language filter.
		if ( isset( $_GET['ubb_empty_lang_filter'] ) ) {
			return;
		}

		if ( ! LangInterface::is_taxonomy_translatable( $taxonomy ) ) {
			return;
		}

		$allowed_languages  = implode( "','", LangInterface::get_languages() );
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

		$message = _n(
			'There is %1$s term without language or with an unknown language. <a href="%2$s">See term</a>',
			'There are %1$s terms without language or with an unknown language. <a href="%2$s">See terms</a>',
			count( $bad_terms ),
			'unbabble'
		);

		$url = add_query_arg( 'ubb_empty_lang_filter', '', parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
		if ( $taxonomy !== 'post' ) {
			$url = add_query_arg( 'taxonomy', $taxonomy, $url );
		}
		// TODO: keep post type if its in the url.

		printf(
			'<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>',
			sprintf(
				$message,
				count( $bad_terms ),
				$url
			)
		);
	}

	/**
	 * Add an admin notice when a term has no language.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function term_missing_language() : void {
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
			|| ! LangInterface::is_taxonomy_translatable( $term->taxonomy )
		) {
			return;
		}

		if ( LangInterface::get_term_language( $term->term_id ) ) {
			return;
		}

		$message = __( 'This term has no language. Please select a language and update the term.', 'unbabble' );
		printf( '<div class="notice notice-error"><p><b>Unbabble: </b>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Add an admin notice when a term has an unknown language.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function term_unknown_language() : void {
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
			|| ! LangInterface::is_taxonomy_translatable( $term->taxonomy )
		) {
			return;
		}

		$term_lang = LangInterface::get_term_language( $term->term_id );
		if ( empty( $term_lang ) ) {
			return;
		}

		$languages = LangInterface::get_languages();
		if ( in_array( $term_lang, $languages, true ) ) {
			return;
		}

		$message = __( 'This term has an unknown language <b>%s</b>. Please select a language and update the term.', 'unbabble' );
		printf( '<div class="notice notice-error"><p><b>Unbabble: </b>%s</p></div>', sprintf( $message, $term_lang ) );
	}

	/**
	 * Add an explanation for the missing language filter.
	 *
	 * @since Unreleased
	 *
	 * @return void
	 */
	public function term_missing_language_filter_explanation() : void {
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'edit-tags'
		) {
			return;
		}

		if ( ! isset( $_GET['ubb_empty_lang_filter'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><b>Unbabble:</b> %s</p></div>',
			__( 'The terms presented here have no language or an unknown language. Use the Term Edit Page to assign languages.', 'unbabble' )
		);
	}
}
