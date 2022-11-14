<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Embed;
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
		// Redirect to create new post page to create a translation.
		// FIXME: Saving an auto-draft (no title) does not call save_post and so source is not set.
		\add_action( 'save_post', [ $this, 'redirect_to_new' ], PHP_INT_MAX );
		\add_action( 'save_post', [ $this, 'set_source' ], PHP_INT_MAX );

		// TODO: Use Yoast's duplicate-post plugin to duplicate post before redirect.
		// \add_action( 'save_post', [ $this, 'create_and_redirect' ], PHP_INT_MAX );
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

	public function set_source( int $post_id ) : void {
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

	// TODO: Refactor to use duplicate-post for copying and redirecting.
	public function create_and_redirect( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! in_array( $post_type, Options::get_allowed_post_types(), true ) ) {
			return;
		}
		if ( ! ( $_POST['ubb_save_create'] ?? false ) ) {
			return;
		}
		$_POST['ubb_save_create'] = false; // Used to stop recursion and stop saving in the LangMetaBox.php.

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

		add_filter( 'wp_insert_post_empty_content', '__return_false' );

		// TODO: slug needs to be different.
		$new_post_id = wp_insert_post( [
			'post_title'   => "({$lang_create})-" . get_post( $post_id )->post_title,
			'post_content' => '',
			'post_status'  => 'draft',
			'post_type'    => get_post_type( $post_id ),
			'post_name'    => get_post( $post_id )->post_title . "-{$lang_create}",
		], true );

		// TODO: check if its being removed correctly.
		remove_filter( 'wp_insert_post_empty_content', '__return_false' );

		if ( $new_post_id instanceof WP_Error ) {
			error_log( print_r( 'CreateTranslation - New post error', true ) );
			// TODO: How to show error.
			return;
		}

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
