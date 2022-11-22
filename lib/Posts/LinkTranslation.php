<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

/**
 * For hooks related to linking translations between existing posts or translation sets of posts.
 *
 * @since 0.0.0
 */
class LinkTranslation {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\add_action( 'save_post', [ $this, 'link_translations' ], PHP_INT_MAX );
	}

	public function link_translations( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type === 'revision'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_POST['ubb_link_translation'] )
			|| ! is_numeric( $_POST['ubb_link_translation'] )
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

		// If none of them have source, use the lowest ID and set that as source for both.
		if ( $post_source === null && $link_source === null ) {
			$source_id = min( $post_id, $link_post->ID );
			LangInterface::set_post_source( $post_id, $source_id );
			LangInterface::set_post_source( $link_post->ID, $source_id );
			return;
		}

		// If the post has source but not the link, set source in link to post source.
		if ( $post_source !== null && $link_source === null ) {
			LangInterface::set_post_source( $link_post->ID, $post_source );
			return;
		}

		// If the link has source but not the post, set source in post to link source.
		if ( $post_source === null && $link_source !== null ) {
			LangInterface::set_post_source( $post_id, $link_source );
			return;
		}

		// If both of them have a source, use lowest source ID and change occurrences of the other one to that value.
		if ( $post_source !== null && $link_source !== null ) {
			$source_id = min( $post_source, $link_source );
			$posts     = LangInterface::get_posts_for_source( max( $post_source, $link_source ) );
			foreach ( $posts as $post_to_change ) {
				LangInterface::set_post_source( $post_to_change, $source_id, true );
			}
			return;
		}
	}
}
