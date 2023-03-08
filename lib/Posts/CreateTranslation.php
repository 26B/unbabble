<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use Exception;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks related to creating translations from an existing post.
 *
 * @since 0.0.1
 */
class CreateTranslation {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Redirect to create new post page to create a translation.
		// FIXME: Saving an auto-draft (no title) does not call save_post and so source is not set.
		\add_action( 'save_post', [ $this, 'redirect_to_new' ], PHP_INT_MAX - 10 );
		\add_action( 'save_post', [ $this, 'set_new_source' ], PHP_INT_MAX - 10 );

		// Attachment translations.
		\add_action( 'edit_attachment', [ $this, 'redirect_to_new' ], PHP_INT_MAX - 10 );
		\add_filter( 'plupload_init', [ $this, 'add_params_to_upload' ], PHP_INT_MAX - 10 );
		\add_action( 'add_attachment', [ $this, 'set_new_source_attachment' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Redirect to new post creation page to make a new translation.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function redirect_to_new( int $post_id ) : void {
		$post_type = get_post_type( $post_id );
		if ( ( $_POST['post_type'] ?? '' ) !== $post_type || $post_id !== (int) $_POST['post_ID'] ) {
			return;
		}
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

	/**
	 * Set new source for saved post given the source in the $_POST form.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function set_new_source( int $post_id ) : void {
		$post_type          = get_post_type( $post_id );
		$allowed_post_types = Options::get_allowed_post_types();
		if (
			$post_type === 'revision'
			|| ! in_array( $post_type, $allowed_post_types, true )
			|| ! isset( $_POST['ubb_source'] )
			|| ! is_numeric( $_POST['ubb_source'] )
			||$_POST['post_type'] !== $post_type
			|| $post_id !== (int) $_POST['post_ID']
		) {
			return;
		}

		$src_post = get_post( \sanitize_text_field( $_POST['ubb_source'] ) );
		if (
			$src_post === null
			|| ! in_array( $src_post->post_type, $allowed_post_types, true )
			|| $src_post->post_type !== $post_type
		) {
			return;
		}

		$this->set_post_source( $post_id, $src_post->ID );
	}

	/**
	 * Set source for a newly uploaded attachment given the source in the request.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @return void
	 */
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

	/**
	 * Adds necessary url parameters to create attachment translation during attachment upload.
	 *
	 * @since 0.0.1
	 *
	 * @param array $plupload_init
	 * @return array
	 */
	public function add_params_to_upload( $plupload_init ) : array {
		$url = $plupload_init['url'];
		$url = add_query_arg( 'lang', LangInterface::get_current_language(), $url );
		if ( isset( $_GET['ubb_source'] ) && is_numeric( $_GET['ubb_source'] ) ) {
			$url = add_query_arg( 'ubb_source', $_GET['ubb_source'], $url );
		}
		$plupload_init['url'] = $url;
		return $plupload_init;
	}

	/**
	 * Sets a $post_id's source to that of $src_post_id's.
	 *
	 * If the post for $src_post_id does not have a source yet, one will be created for it.
	 *
	 * @since 0.0.1
	 *
	 * @param int $post_id
	 * @param int $src_post_id
	 * @return void
	 */
	private function set_post_source( int $post_id, int $src_post_id ) : void {
		$original_source = LangInterface::get_post_source( $src_post_id );
		if ( $original_source === null ) {
			$original_source = LangInterface::get_new_post_source_id();
			LangInterface::set_post_source( $src_post_id, $original_source );
		}

		LangInterface::set_post_source( $post_id, $original_source );
	}
}
