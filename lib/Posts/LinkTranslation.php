<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

/**
 * Hooks related to linking and unlinking translations between existing posts or translation
 * sets of posts.
 *
 * @since 0.0.1
 */
class LinkTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}
		\add_action( 'save_post', [ $this, 'link_translations' ], PHP_INT_MAX - 10 );
		\add_action( 'save_post', [ $this, 'unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'link_translations' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Link translations for post $post_id.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function link_translations( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type === 'revision'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_POST['ubb_link_translation'] )
			|| ! is_numeric( $_POST['ubb_link_translation'] )
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return;
		}

		$link_post = get_post( \sanitize_text_field( $_POST['ubb_link_translation'] ) );
		if (
			$link_post === null
			|| ! in_array( $link_post->post_type, $allowed_post_types, true )
			|| $link_post->post_type !== $post_type
		) {
			return;
		}

		$post_source = LangInterface::get_post_source( $post_id );
		$link_source = LangInterface::get_post_source( $link_post->ID );

		if ( $link_source === null ) {
			$link_source = LangInterface::get_new_post_source_id();
			LangInterface::set_post_source( $link_post->ID, $link_source, true );
		}

		if ( ! LangInterface::set_post_source( $post_id, $link_source, true ) ) {
			// TODO: show admin notice of failure to change new post source.
			LangInterface::set_post_source( $post_id, $post_source );
			return;
		}
	}

	/**
	 * Unlink post $post_id from its translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function unlink( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type === 'revision'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_POST['ubb_link_translation'] )
			|| $_POST['ubb_link_translation'] !== 'unlink'
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return;
		}

		LangInterface::delete_post_source( $post_id );
	}
}
