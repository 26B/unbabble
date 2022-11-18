<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Error;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;

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

		// TODO: Attachment copy not done yet.

		// Redirect to create new post page to create a translation.
		// FIXME: Saving an auto-draft (no title) does not call save_post and so source is not set.
		\add_action( 'save_post', [ $this, 'redirect_to_new' ], PHP_INT_MAX );
		\add_action( 'save_post', [ $this, 'set_new_source' ], PHP_INT_MAX );

		// Attachment translations.
		\add_action( 'edit_attachment', [ $this, 'redirect_to_new' ], PHP_INT_MAX );
		\add_filter( 'plupload_init', [ $this, 'add_params_to_upload' ], PHP_INT_MAX );
		\add_action( 'add_attachment', [ $this, 'set_new_source_attachment' ], PHP_INT_MAX );
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
				array_merge(
					[
						'lang'       => $lang_create,
						'ubb_source' => $post_id,
					],
					$post_type === 'post' ? [] : [
						'post_type' => $post_type
					],
				),
				$post_type === 'attachment' ? admin_url( 'media-new.php' ) : admin_url( 'post-new.php' )
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
			|| ! is_numeric( $_POST['ubb_source'] )
		) {
			return;
		}

		$src_post = get_post( \sanitize_text_field( $_POST['ubb_source'] ) );
		if ( $src_post === null || ! in_array( $src_post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		$this->set_post_source( $post_id, $src_post->ID );
	}

	public function set_new_source_attachment( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type !== 'attachment'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_GET['ubb_source'] )
			|| ! is_numeric( $_GET['ubb_source'] )
		) {
			return;
		}

		$src_post = get_post( \sanitize_text_field( $_GET['ubb_source'] ) );
		if ( $src_post === null || ! in_array( $src_post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		$this->set_post_source( $post_id, $src_post->ID );
	}

	public function add_params_to_upload( $plupload_init ) : array {
		$url = $plupload_init['url'];
		$url = add_query_arg( 'lang', LangInterface::get_current_language(), $url );
		if ( isset( $_GET['ubb_source'] ) && is_numeric( $_GET['ubb_source'] ) ) {
			$url = add_query_arg( 'ubb_source', $_GET['ubb_source'], $url );
		}
		$plupload_init['url'] = $url;
		return $plupload_init;
	}

	private function set_post_source( $post_id, $src_post_id ) {
		$original_source = LangInterface::get_post_source( $src_post_id );
		if ( $original_source === null ) {
			$original_source = $src_post_id;
			LangInterface::set_post_source( $src_post_id, $src_post_id );
		}

		LangInterface::set_post_source( $post_id, $original_source );
	}
}
