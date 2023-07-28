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
		\add_action( 'save_post', [ $this, 'save_link_translations' ], PHP_INT_MAX - 10 );
		\add_action( 'save_post', [ $this, 'save_unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'save_unlink' ], PHP_INT_MAX - 10 );
		\add_action( 'edit_attachment', [ $this, 'save_link_translations' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Link translations for post $post_id.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function save_link_translations( int $post_id ) {
		$post_type = get_post_type( $post_id );
		if (
			! isset( $_POST['ubb_link_translation'] )
			|| ! is_numeric( $_POST['ubb_link_translation'] )
			|| isset( $_POST['menu'] ) // Stop if nav menu updated or saved.
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return false;
		}

		return $this->link_translations( $post_id, \sanitize_text_field( $_POST['ubb_link_translation'] ) );
	}

	public function link_translations( int $post_id, int $link_target ) : bool {
		$post_type = get_post_type( $post_id );

		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return false;
		}

		$link_post = \get_post( $link_target );;
		if (
			$link_post === null
			|| ! LangInterface::is_post_type_translatable( $link_post->post_type )
			|| $link_post->post_type !== $post_type
		) {
			return false;
		}

		$post_source = LangInterface::get_post_source( $post_id );
		$link_source = LangInterface::get_post_source( $link_post->ID );

		// Check if already linked.
		if ( $post_source !== null && $post_source === $link_source ) {
			return true;
		}

		if ( $link_source === null ) {
			$link_source = LangInterface::get_new_post_source_id();
			LangInterface::set_post_source( $link_post->ID, $link_source, true );
		}

		if ( ! LangInterface::set_post_source( $post_id, $link_source, true ) ) {
			// TODO: show admin notice of failure to change new post source.
			LangInterface::set_post_source( $post_id, $post_source );
			return false;
		}

		return true;
	}

	/**
	 * Unlink post $post_id from its translations.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public function save_unlink( int $post_id ) : bool {
		$post_type = get_post_type( $post_id );
		if (
			! isset( $_POST['ubb_link_translation'] )
			|| $_POST['ubb_link_translation'] !== 'unlink'
			|| $_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return false;
		}

		return $this->unlink( $post_id );
	}

	public function unlink( int $post_id ) : bool {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return false;
		}
		return LangInterface::delete_post_source( $post_id );
	}
}
