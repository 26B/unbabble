<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

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
		if ( ! Options::should_run_unbabble() ) {
			return;
		}
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'posts_missing_language' ], PHP_INT_MAX );
	}

	/**
	 * Adds an admin notice when a post has translation for the same language as itself.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function duplicate_language() : void {
		$screen = get_current_screen();
		$post   = get_post();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'post'
			|| ! $post instanceof WP_Post
			|| ! in_array( $post->post_type, Options::get_allowed_post_types(), true )
		) {
			return;
		}

		$post_lang    = LangInterface::get_post_language( $post->ID );
		$translations = LangInterface::get_post_translations( $post->ID );
		if ( ! in_array( $post_lang, $translations, true )) {
			return;
		}

		$message = __( 'There is a translation with the same language as this post.', 'unbabble' );
		printf( '<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>', esc_html( $message ) );
	}

	/**
	 * Adds an admin notice for when there's posts with missing languages or with an unknown language.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function posts_missing_language() : void {
		global $wpdb;
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'edit'
			|| ! current_user_can( 'manage_options' )
		) {
			return;
		}

		$post_type = $_GET['post_type'] ?? 'post';

		if ( ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return;
		}

		$allowed_languages  = implode( "','", Options::get()['allowed_languages'] );
		$translations_table = ( new PostTable() )->get_table_name();
		$bad_posts          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID
				FROM {$wpdb->posts} as P
				WHERE ID NOT IN (
					SELECT post_id
					FROM {$translations_table} as PT
					WHERE PT.locale IN ('{$allowed_languages}')
				) AND post_type = %s AND post_status != 'auto-draft'",
				esc_sql( $post_type )
			)
		);

		if ( count( $bad_posts ) === 0 ) {
			return;
		}

		// TODO: link to actions.
		$message = _n(
			'There is %1$s post without language or with an unknown language. Go to (link) to see possible actions.',
			'There are %1$s posts without language or with an unknown language. Go to (link) to see possible actions.',
			count( $bad_posts ),
			'unbabble'
		);
		printf(
			'<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>',
			esc_html( sprintf( $message, count( $bad_posts ) ) )
		);
	}
}
