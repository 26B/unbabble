<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * Handle Language Meta Box for Posts and Terms.
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
		// Post meta box.
		\add_action( 'add_meta_boxes', [ $this, 'language_metabox' ] );
		\add_action( 'save_post', [ $this, 'save_language_meta' ] );

		// Term meta box.
		\add_action( 'init', [ $this, 'register_term_meta' ] );
		$taxonomies = \get_taxonomies(
			[
				'public'  => true,
				'show_ui' => true,
			]
		);
		foreach ( $taxonomies as $taxonomy ) {
			\add_action( "{$taxonomy}_add_form_fields", [ $this, 'new_term_language_metabox' ] );
			\add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_term_language_metabox' ] );
			\add_action( "edit_{$taxonomy}", [ $this, 'save_term_language_meta' ] );
			\add_action( "create_{$taxonomy}", [ $this, 'save_term_language_meta' ] );
		}
	}

	public function language_metabox() {
		$post_types = \get_post_types(
			[
				'public'  => true,
				'show_ui' => true,
			]
		);
		$post_types = array_filter(
			$post_types,
			fn ( $post_type ) => ! in_array( $post_type, [ 'revision', 'attachment' ], true )
		);

		/**
		 * Allow for the post types that are translatable to be filtered.
		 *
		 * TODO: we need to add the post types as a configuration for non dev users.
		 */
		$post_types = \apply_filters( 'ubb_translatable_post_types', $post_types );

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

		$this->print_language_metabox( $meta, $options );
		// TODO: Add translate or duplicate into other language fields options
	}

	public function save_language_meta( $post_id ) {
		if ( ! $this->verify_nonce() ) {
			return;
		}

		// TODO: Check the user's permissions.

		// Sanitize the user input.
		$lang = \sanitize_text_field( $_POST['ubb_lang'] );

		// Update the meta field.
		\update_post_meta( $post_id, 'ubb_lang', $lang );
	}

	public function register_term_meta() {
		\register_meta( 'term', 'ubb_lang', [] );
	}

	public function new_term_language_metabox() {
		$options = \get_option( 'unbabble_options' );
		$this->print_language_metabox( $options['default_language'], $options );
	}

	public function edit_term_language_metabox( $term ) {
		$meta    = \get_term_meta( $term->term_id, 'ubb_lang', true );
		$options = \get_option( 'unbabble_options' );
		if ( empty( $meta ) ) {
			$meta = $options['default_language'];
		}
		$this->print_language_metabox( $meta, $options );
	}

	public function save_term_language_meta( $term_id ) {
		if ( ! $this->verify_nonce() ) {
			return;
		}

		$old_lang = \get_term_meta( $term_id, 'ubb_lang', true );
		$new_lang = \sanitize_text_field( $_POST['ubb_lang'] );

		if ( $new_lang === '' ) {
			$options  = \get_option( 'unbabble_options' );
			$new_lang = $options['default_language'];
		}

		if ( $old_lang !== $new_lang ) {
			\update_term_meta( $term_id, 'ubb_lang', $new_lang );
		}
	}

	private function print_language_metabox( $selected, $options ) : void {
		$langs = array_map(
			function ( $lang ) use ( $selected ) {
				return sprintf(
					'<option value="%1$s" %2$s>%1$s</option>',
					$lang,
					\selected( $lang, $selected, false )
				);
			},
			is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : []
		);

		\wp_nonce_field( 'ubb_language_metabox', 'ubb_language_metabox_nonce' );

		printf(
			'<label for="term-meta-text">%s</label>
			<select id="ubb_lang" name="ubb_lang">
				%s
			</select>',
			__( 'Language', 'unbabble' ),
			implode( '', $langs )
		);
	}

	private function verify_nonce() : bool {

		// Check if our nonce is set.
		if ( ! isset( $_POST['ubb_language_metabox_nonce'] ) ) {
			return false;
		}

		$nonce = $_POST['ubb_language_metabox_nonce'];

		// Verify that the nonce is valid.
		if ( ! \wp_verify_nonce( $nonce, 'ubb_language_metabox' ) ) {
			return false;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		return true;
	}
}
