<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * The dashboard-specific functionality of the plugin
 *
 * @since 0.0.0
 */
class LanguageMetaBox {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		\add_action( 'add_meta_boxes', [ $this, 'language_metabox' ] );
		\add_action( 'save_post', [ $this, 'save_language_meta' ] );
	}

	public function language_metabox() {
		$post_types = \get_post_types( [ 'public' => 'true' ] );
		$post_types = array_filter(
			$post_types,
			fn ( $post_type ) => ! in_array( $post_type, [ 'revision', 'attachment' ], true )
		);

		/**
		 * Allow for the post types that are translatable to be filtered.
		 *
		 * TODO: we need to add the post types as a configuration for non dev users.
		 */
		$post_types = apply_filters( 'ubb_translatable_post_types', $post_types );

		\add_meta_box(
			'ubb_lang',
			\__( 'Language', 'textdomain' ),
			[ $this, 'ubb_lang_callback' ],
			$post_types,
			'side',
			'high',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	public function ubb_lang_callback( \WP_Post $post ) {
		$meta    = \get_post_meta( $post->ID, 'ubb_lang', true );
		$options = \get_option( 'unbabble_options' );
		if ( empty( $meta ) ) {
			$meta = $options['default_language'];
		}

		$langs = array_map(
			function ( $lang ) use ( $meta ) {
				return sprintf(
					'<option value="%1$s" %2$s>%1$s</option>',
					$lang,
					\selected( $lang, $meta, false )
				);
			},
			is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : []
		);

		wp_nonce_field( 'ubb_language_metabox', 'ubb_language_metabox_nonce' );

		printf(
			'<select id="ubb_lang" name="ubb_lang">
				%s
			</select>',
			implode( '', $langs )
		);

		// TODO: Add translate or duplicate into other language fields options
	}

	public function save_language_meta( $post_id ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['ubb_language_metabox_nonce'] ) ) {
			return;
		}

		$nonce = $_POST['ubb_language_metabox_nonce'];

		// Verify that the nonce is valid.
		if ( ! \wp_verify_nonce( $nonce, 'ubb_language_metabox' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// TODO: Check the user's permissions.

		// Sanitize the user input.
		$lang = sanitize_text_field( $_POST['ubb_lang'] );

		// Update the meta field.
		update_post_meta( $post_id, 'ubb_lang', $lang );
	}
}
