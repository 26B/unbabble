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
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		// Post meta box.
		\add_action( 'add_meta_boxes', [ $this, 'post_language_selector' ] );
		\add_action( 'save_post', [ $this, 'save_language_meta' ] );

		// Term meta box.
		\add_action( 'init', [ $this, 'register_term_meta' ] );
		\add_action( 'init', [ $this, 'term_language_selector' ] );
	}

	public function post_language_selector() {

		// Filter for allowed post types.
		$post_types = array_intersect( \get_post_types(), Options::get_allowed_post_types() );

		/**
		 * Allow for the post types that are translatable to be filtered.
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
		// TODO: This should come from the cookie/query_var
		$meta    = \get_post_meta( $post->ID, 'ubb_lang', true );
		$options = Options::get();
		if ( empty( $meta ) ) {
			$meta = $options['default_language'];
		}

		$this->print_language_select( $meta, $options );
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

	public function term_language_selector() {

		// Filter for allowed taxonomies.
		$taxonomies = array_intersect( \get_taxonomies(), Options::get_allowed_taxonomies() );

		/**
		 * Allow for the taxonomies that are translatable to be filtered.
		 */
		$taxonomies = \apply_filters( 'ubb_translatable_taxonomies', $taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			\add_action( "{$taxonomy}_add_form_fields", [ $this, 'new_term_language_metabox' ] );
			\add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_term_language_metabox' ] );
			\add_action( "edit_{$taxonomy}", [ $this, 'save_term_language_meta' ] );
			\add_action( "create_{$taxonomy}", [ $this, 'save_term_language_meta' ] );
		}
	}

	public function new_term_language_metabox() {
		$options = Options::get();

		printf(
			'<div class="form-field term-language-wrap">
				<label for="tag-language">%1$s</label>
				%2$s
				<p>%3$s</p>
			</div>',
			esc_html__( 'Language', 'unbabble' ),
			$this->print_language_select( $options['default_language'], $options, false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' )
		);
	}

	public function edit_term_language_metabox( $term ) {
		$meta    = \get_term_meta( $term->term_id, 'ubb_lang', true );
		$options = Options::get();
		if ( empty( $meta ) ) {
			$meta = $options['default_language'];
		}

		printf(
			'<tr class="form-field term-language-wrap">
				<th scope="row"><label for="language">%1$s</label></th>
				<td>
					%2$s
					<p class="description">%3$s</p>
				</td>
			</tr>',
			esc_html__( 'Language', 'unbabble' ),
			$this->print_language_select( $meta, $options, false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' )
		);
	}

	public function save_term_language_meta( $term_id ) {
		if ( ! $this->verify_nonce() ) {
			return;
		}

		$old_lang = \get_term_meta( $term_id, 'ubb_lang', true );
		$new_lang = \sanitize_text_field( $_POST['ubb_lang'] );

		if ( $new_lang === '' ) {
			$options  = Options::get();
			$new_lang = $options['default_language'];
		}

		if ( $old_lang !== $new_lang ) {
			\update_term_meta( $term_id, 'ubb_lang', $new_lang );
		}
	}

	private function print_language_select( $selected, $options, $echo = true ) {
		$langs = array_map(
			function ( $lang ) use ( $selected ) {
				return sprintf(
					'<option value="%1$s" %2$s>%1$s</option>',
					$lang,
					\selected( $lang, $selected, false )
				);
			},
			// TODO: This shouldn't happen. Should always be array.
			is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : []
		);

		\wp_nonce_field( 'ubb_language_metabox', 'ubb_language_metabox_nonce' );

		$output = sprintf(
			'<select id="ubb_lang" name="ubb_lang">
				%s
			</select>',
			implode( '', $langs )
		);

		return ! $echo ? $output : printf( $output );
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
