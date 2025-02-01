<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use FCG\Lib\Website\WP\Lang;
use TwentySixB\WP\Plugin\Unbabble\Admin\Admin;
use TwentySixB\WP\Plugin\Unbabble\Cache\Keys;
use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use WP_Post;

/**
 * Hooks for the language meta box for posts.
 *
 * @since 0.0.1
 */
class LangMetaBox {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		\add_action(
			'current_screen',
			function ( $screen ) {
				if ( Admin::is_block_editor( $screen ) ) {
					return;
				}

				// Post meta box.
				\add_action( 'add_meta_boxes', [ $this, 'add_ubb_meta_box' ] );
			},
			PHP_INT_MAX
		);

		// TODO: Move somewhere else.
		// FIXME: better solution for initial post language
		\add_action( 'save_post', [ $this, 'save_post_language' ], PHP_INT_MAX - 10 );
	}

	/**
	 * Adds post metabox for inputs/actions for language/translations.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function add_ubb_meta_box() : void {

		// Filter for allowed post types.
		$post_types = array_intersect( \get_post_types(), LangInterface::get_translatable_post_types() );

		/**
		 * Filters post types that will have the language meta box.
		 *
		 * @since 0.0.1
		 *
		 * @param string[] $post_types
		 */
		$post_types = \apply_filters( 'ubb_translatable_post_types', $post_types );

		\add_meta_box(
			'ubb_lang',
			\__( 'Language', 'textdomain' ),
			[ $this, 'add_ubb_meta_box_callback' ],
			$post_types,
			'side',
			'high',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	/**
	 * Prints the metabox for the post language selection in the post editor.
	 *
	 * FIXME: Refactor.
	 *
	 * @since 0.0.1
	 *
	 * @param  WP_Post $post
	 * @return void
	 */
	public function add_ubb_meta_box_callback( \WP_Post $post ) : void {
		if ( is_numeric( $_GET['ubb_source'] ?? '' ) ) {
			printf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);
		}

		printf( '<div id="ubb-language"></div>' );
	}

	/**
	 * Sets the language to the saved post.
	 *
	 * If it's already set, it will not change.
	 *
	 * @since 0.0.1
	 *
	 * @param  int $post_id
	 * @return void
	 */
	public function save_post_language( int $post_id ) : void {
		if ( 'auto-draft' === get_post( $post_id )->post_status ) {
			LangInterface::set_post_language( $post_id, LangInterface::get_current_language() );
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== $post_type || $post_id !== (int) $_POST['post_ID'] ) {
			return;
		}

		if (
			get_post_type( $post_id ) === 'revision'
			|| isset( $_POST['ubb_copy_new'] ) // Don't set post language when copying to a translation.
		) {
			return;
		}

		// TODO: Check the user's permissions.

		if ( ! isset( $_POST['ubb_lang'] ) ) {
			return;
		}

		// Delete the posts with missing language transient if the post didn't have a language before.
		if ( empty( LangInterface::get_post_language( $post_id ) ) ) {
			delete_transient( sprintf( Keys::POST_TYPE_MISSING_LANGUAGE, $post_type ) );
		}

		// Sanitize the user input.
		$lang = \sanitize_text_field( $_POST['ubb_lang'] );

		LangInterface::set_post_language( $post_id, $lang );
	}

	/**
	 * Verifies a nonce.
	 *
	 * @since 0.0.1
	 *
	 * @param $name
	 * @param $action
	 * @return bool
	 */
	private function verify_nonce( $name, $action ) : bool {

		// Check if our nonce is set.
		if ( ! isset( $_POST[ $name ] ) ) {
			return false;
		}

		$nonce = $_POST[ $name ];

		// Verify that the nonce is valid.
		if ( ! \wp_verify_nonce( $nonce, $action ) ) {
			return false;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns whether metabox actions are allowed.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public static function allow_actions() : bool {
		return \apply_filters( 'ubb_allow_metabox_actions', true );
	}
}
