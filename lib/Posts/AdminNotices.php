<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\Cache\Keys;
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
		\add_action( 'admin_notices', [ $this, 'duplicate_language' ], PHP_INT_MAX );
		\add_action( 'admin_notices', [ $this, 'posts_missing_language' ], PHP_INT_MAX );
		\add_filter( 'admin_notices', [ $this, 'post_missing_language_filter_explanation' ], PHP_INT_MAX );
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
			|| ! LangInterface::is_post_type_translatable( $post->post_type )
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
	 * @since 0.4.2 Remove TODO and duplicate $post_type.
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

		// Don't show when the user is already on the no language filter.
		if ( isset( $_GET['ubb_empty_lang_filter'] ) ) {
			return;
		}

		if ( ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return;
		}

		// Check if the cache exists.
		$cache_key       = sprintf( Keys::POST_TYPE_MISSING_LANGUAGE, $post_type );
		$bad_posts_count = get_transient( $cache_key );
		if ( ! is_numeric( $bad_posts_count ) ) {
			$allowed_languages  = implode( "','", LangInterface::get_languages() );
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

			$bad_posts_count = count( $bad_posts );
			set_transient( $cache_key, $bad_posts_count, 30 );
		}

		if ( $bad_posts_count === 0 ) {
			return;
		}

		$message = _n(
			'There is %1$s post without language or with an unknown language. <a href="%2$s">See post</a>',
			'There are %1$s posts without language or with an unknown language. <a href="%2$s">See posts</a>',
			$bad_posts_count,
			'unbabble'
		);

		$url = add_query_arg( 'ubb_empty_lang_filter', '', parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
		if ( $post_type !== 'post' ) {
			$url = add_query_arg( 'post_type', $post_type, $url );
		}

		printf(
			'<div class="notice notice-warning"><p><b>Unbabble: </b>%s</p></div>',
			sprintf(
				$message,
				$bad_posts_count,
				$url
			)
		);
	}

	/**
	 * Adds an admin notice explaining the post filter for unknown or missing languages.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function post_missing_language_filter_explanation() : void {
		$screen = get_current_screen();
		if (
			! is_admin()
			|| $screen->parent_base !== 'edit'
			|| $screen->base !== 'edit'
		) {
			return;
		}

		if ( ! isset( $_GET['ubb_empty_lang_filter'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><b>Unbabble:</b> %s</p></div>',
			__( 'The posts presented here have no language or an unknown language. Use the Bulk Edit or the Post Edit Page to assign languages.', 'unbabble' )
		);
	}
}
