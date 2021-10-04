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

		// TODO: Metaboxes should be somewhat disabled during translation create.
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
		$options = Options::get();
		$lang    = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];
		if ( empty( $lang ) ) {
			$lang = $options['default_language'];
		}

		$this->print_language_select( 'ubb_lang', $lang, $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox' );

		$translations                  = \get_post_meta( $post->ID, 'ubb_lang' );
		$available_languages           = array_flip( $options['allowed_languages'] );
		$allowed_translation_languages = [];

		$query_args = $_GET;
		unset( $query_args['lang'] );

		// Display existing translations.
		$translation_to_show = [];
		foreach ( $translations as $translation_lang ) {
			if ( ! in_array( $translation_lang, $options['allowed_languages'], true ) ) {
				continue;
			}
			unset( $available_languages[ $translation_lang ] );
			$allowed_translation_languages[] = $translation_lang;
			if ( $translation_lang === $lang ) {
				continue;
			}
			$args = $query_args;
			unset( $args['ubb_create'], $args['ubb_copy'] );
			$translation_to_show[] = sprintf(
				'<tr><td>%1$s</td><td><a href="%2$s" >View</a></td></tr>',
				$translation_lang,
				\add_query_arg( array_merge( $args, [ 'ubb_switch_lang' => $translation_lang ] ), $_SERVER['PHP_SELF'] )
			);
		}

		if ( $translation_to_show ) {
			printf(
				'<p><b>Translations:</b></p>
				<table>
				<tr>
					<th>Language</th>
					<th>Actions</th>
				</tr>
				%s
				</table>',
				implode( '', $translation_to_show )
			);
		} else {
			printf( '<p><b>No Translations</b></p>' );
		}

		unset( $available_languages[ $lang ] );

		// Can't create more translations currently.
		if ( empty( $available_languages ) ) {
			return;
		}

		$available_languages = array_keys( $available_languages );

		// Display language selector and button to create new translation.
		printf(
			'<p><b>Create Translation</b></p>
			<div>To: %1$s <br>Copy from: %2$s</div><br>
			<button type="button" id="ubb-translate-action">Save and Create</button>',
			$this->print_language_select( 'ubb_create', '', $available_languages, '', '', false ),
			$this->print_language_select( 'ubb_copy', '', array_merge( [ 'No Copy' => '' ], $allowed_translation_languages ), '', '', false ),
		);
	}

	public function save_language_meta( $post_id ) {

		if ( ! $this->verify_nonce( 'ubb_language_metabox', 'ubb_language_metabox_nonce' ) ) {
			return;
		}

		// TODO: Check the user's permissions.

		// Sanitize the user input.
		$lang = \sanitize_text_field( $_POST['ubb_lang'] );

		// Check langs for post.
		$post_languages = \get_post_meta( $post_id, 'ubb_lang' );

		// If lang already exists for this post, then don't add it.
		if ( in_array( $lang, $post_languages, true ) ) {
			return;
		}

		// Add the meta field.
		\add_post_meta( $post_id, 'ubb_lang', $lang );
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
			$this->print_language_select( 'ubb_lang', $options['default_language'], $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
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
			$this->print_language_select( 'ubb_lang', $meta, $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' )
		);
	}

	public function save_term_language_meta( $term_id ) {
		if ( ! $this->verify_nonce( 'ubb_language_metabox_nonce', 'ubb_language_metabox' ) ) {
			return;
		}

		$old_lang = \get_term_meta( $term_id, 'ubb_lang', true ); // TODO: should not be single=true.
		$new_lang = \sanitize_text_field( $_POST['ubb_lang'] );

		if ( $new_lang === '' ) {
			$options  = Options::get();
			$new_lang = $options['default_language'];
		}

		if ( $old_lang !== $new_lang ) {
			// TODO: should be add if it doesnt exist already.
			\update_term_meta( $term_id, 'ubb_lang', $new_lang );
		}
	}

	private function print_language_select( string $name, $selected, $options, string $nonce_action, string $nonce_name, $echo = true ) {
		$langs = array_map(
			function ( $text, $lang ) use ( $selected ) {
				if ( is_int( $text ) ) {
					$text = $lang;
				}
				return sprintf(
					'<option value="%1$s" %2$s>%3$s</option>',
					$lang,
					\selected( $lang, $selected, false ),
					$text
				);
			},
			array_keys( $options ),
			$options
		);

		if ( ! empty( $nonce_action ) && ! empty( $nonce_name ) ) {
			\wp_nonce_field( $nonce_action, $nonce_name );
		}

		$output = sprintf(
			'<select id="%1$s" name="%1$s">
				%2$s
			</select>',
			$name,
			implode( '', $langs )
		);

		return ! $echo ? $output : printf( $output );
	}

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
}
