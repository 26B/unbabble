<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to creating a translations from an existing post.
 *
 * @since 0.0.0
 */
class CreateTranslation {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		\add_action( 'admin_init', function() {
			// Redirect to create new post page to create a translation.
			// FIXME: Saving an auto-draft (no title) does not call save_post and so source is not set.
			\add_action( 'save_post', [ $this, 'redirect_to_new' ], PHP_INT_MAX );
			\add_action( 'save_post', [ $this, 'set_new_source' ], PHP_INT_MAX );

			// TODO: Move to separate class for integration.
			// Use Yoast's duplicate-post plugin to duplicate post before redirect.
			if ( \is_plugin_active( 'duplicate-post/duplicate-post.php' ) ) {
				\add_action( 'save_post', [ $this, 'copy_and_redirect' ], PHP_INT_MAX );
			}
		} );

	}

	public function redirect_to_new( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return;
		}
		if ( ! ( $_POST['ubb_redirect_new'] ?? false ) ) {
			return;
		}

		// Language to set to the new post.
		$lang_create = $_POST['ubb_create'] ?? '';
		if (
			empty( $lang_create )
			|| ! in_array( $lang_create, Options::get()['allowed_languages'] )
			// TODO: check if post_id has this language already
		) {
			// TODO: What else to do when this happens.
			error_log( print_r( 'CreateTranslation - lang create failed', true ) );
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'lang'       => $lang_create,
					'ubb_source' => $post_id,
				],
				admin_url( 'post-new.php' )
			),
			302,
			'Unbabble'
		);
		exit;
	}

	public function set_new_source( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type === 'revision'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_POST['ubb_source'] )
		) {
			return;
		}

		if ( ! is_numeric( $_POST['ubb_source'] ) ) {
			return;
		}

		$src_post = get_post( \sanitize_text_field( $_POST['ubb_source'] ) );
		if ( $src_post === null || ! in_array( $src_post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		$original_source = LangInterface::get_post_source( $src_post->ID );
		if ( $original_source === null ) {
			$original_source = $src_post->ID;
			LangInterface::set_post_source( $src_post->ID, $src_post->ID );
		}

		LangInterface::set_post_source( $post_id, $original_source );
	}

	public function copy_and_redirect( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return;
		}
		if ( ! ( $_POST['ubb_copy_new'] ?? false ) ) {
			return;
		}
		$_POST['ubb_copy_new'] = false; // Used to stop recursion and stop saving in the LangMetaBox.php.

		// Language to set to the new post.
		$lang_create = $_POST['ubb_create'] ?? '';
		if (
			empty( $lang_create )
			|| ! in_array( $lang_create, Options::get()['allowed_languages'] )
			// TODO: check if post_id has this language already
		) {
			// TODO: What else to do when this happens.
			error_log( print_r( 'CreateTranslation - lang create failed', true ) );
			return;
		}

		$post_duplicator = new \Yoast\WP\Duplicate_Post\Post_Duplicator();
		$new_post_id     = $post_duplicator->create_duplicate( get_post( $post_id ), [] );

		if ( $new_post_id instanceof WP_Error ) {
			error_log( print_r( 'CreateTranslation - New post error', true ) );
			// TODO: How to show error.
			return;
		}

		\delete_post_meta( $new_post_id, '_dp_original' );

		// Set language in the custom post lang table.
		if ( ! LangInterface::set_post_language( $new_post_id, $lang_create ) ) {
			error_log( print_r( 'CreateTranslation - language set failed', true ) );
			// TODO: What else to do when this happens.
			return;
		}

		$source_id = LangInterface::get_post_source( $post_id );
		error_log( print_r( 'Source -' . $source_id, true ) );

		// If first translations. set source on the original post.
		if ( ! $source_id ) {
			$source_id = $post_id;
			if ( ! LangInterface::set_post_source( $post_id, $post_id ) ) {
				error_log( print_r( 'CreateTranslation - set source original failed', true ) );
				// TODO: What to do when this happens.
				return;
			}
		}

		if ( ! LangInterface::set_post_source( $new_post_id, $source_id ) ) {
			error_log( print_r( 'CreateTranslation - set source on translation failed', true ) );
			// TODO: What to do when this happens.
			return;
		}

		wp_safe_redirect( get_edit_post_link( $new_post_id, '&' ) . "&lang={$lang_create}", 302, 'Unbabble' );
		exit;
	}
}
