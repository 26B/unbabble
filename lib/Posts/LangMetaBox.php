<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Handle Language Meta Box for Posts.
 *
 * @since 0.0.0
 */
class LangMetaBox {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		// FIXME: Can't copy from draft. (maybe pending review too)

		// Post meta box.
		\add_action( 'add_meta_boxes', [ $this, 'post_language_selector' ] );
		\add_action( 'save_post', [ $this, 'save_post_language' ] );

		// FIXME: Set post language when attachment is saved.

		// TODO: Metaboxes should be somewhat disabled during translation create.
	}

	/**
	 * Add metabox to select the post language in the post editor.
	 *
	 * @return void
	 */
	public function post_language_selector() : void {

		// Filter for allowed post types.
		$post_types = array_intersect( \get_post_types(), Options::get_allowed_post_types() );

		/**
		 * Allow for the post types that are translatable to be filtered.
		 */
		$post_types = \apply_filters( 'ubb_translatable_post_types', $post_types );

		\add_meta_box(
			'ubb_lang',
			\__( 'Language', 'textdomain' ),
			[ $this, 'post_language_selector_callback' ],
			$post_types,
			'side',
			'high',
			[
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			]
		);
	}

	//
	/**
	 * Callback to print the metabox for the post language selection in the post editor.
	 *
	 * FIXME: Refactor.
	 *
	 * @param  WP_Post $post
	 * @return void
	 */
	public function post_language_selector_callback( \WP_Post $post ) : void {
		$options = Options::get();
		// TODO: use LangInterface for language.
		$lang    = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];
		if ( empty( $lang ) ) {
			$lang = $options['default_language'];
		}

		$this->print_language_select( 'ubb_lang', $lang, $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox' );
		$current_screen = get_current_screen();
		if ( $current_screen->base == "post" && $current_screen->action == "add" ) {
			return;
		}

		$translations                  = LangInterface::get_post_translations( $post->ID );
		$available_languages           = array_flip( $options['allowed_languages'] );

		$query_args = $_GET;
		unset( $query_args['lang'] );

		// Display existing translations.
		$translation_to_show = [];
		$seen_languages      = [];
		foreach ( $translations as $translation_id => $translation_lang ) {
			if ( ! in_array( $translation_lang, $options['allowed_languages'], true ) ) {
				continue;
			}

			/**
			 * It is possible that there might be more than one translation for the same language
			 * due to some action outside of the plugin. We place a warning that one of them is a
			 * duplicate for the user to handle.
			 */
			$duplicate_language = false;
			if (
				isset( $seen_languages[ $translation_lang ] )
				|| $translation_lang === $lang
			) {
				$duplicate_language = true;
			}

			unset( $available_languages[ $translation_lang ] );
			$seen_languages[ $translation_lang ] = true;

			$args = $query_args;
			unset( $args['ubb_create'], $args['ubb_copy'] );
			$translation_to_show[] = sprintf(
				'<tr><td>%1$s</td><td><a href="%2$s" >Edit</a>%3$s</td></tr>',
				$translation_lang,
				add_query_arg( 'lang', $translation_lang, get_edit_post_link( $translation_id ) ),
				! $duplicate_language ? '' : ' <b style="color:FireBrick">Duplicate</b>',
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
			<div>To: %1$s</div>
			<input type="submit" %2$s name="ubb_save_create" value="Save and Create" class="button"/>',
			$this->print_language_select( 'ubb_create', '', $available_languages, '', '', false ),
			$post->post_status === 'draft' ? 'id="save-post"' : ''
		);
	}

	/**
	 * Save the selected post language.
	 *
	 * If it's already set, it will not change. FIXME: there needs to be a way to change it.
	 *
	 * @param  int $post_id
	 * @return void
	 */
	public function save_post_language( int $post_id ) : void {
		if ( 'auto-draft' === get_post( $post_id )->post_status) {
			LangInterface::set_post_language( $post_id, LangInterface::get_current_language() );
			return;
		}

		if ( ! $this->verify_nonce( 'ubb_language_metabox', 'ubb_language_metabox_nonce' ) ) {
			return;
		}

		if (
			get_post_type( $post_id ) === 'revision'
			|| isset( $_POST['ubb_save_create'] ) // Don't set post language when creating a translation.
		) {
			return;
		}

		// TODO: Check the user's permissions.

		// Sanitize the user input.
		$lang = \sanitize_text_field( $_POST['ubb_lang'] );

		LangInterface::set_post_language( $post_id, $lang );
	}

	/**
	 * Print language select for the language metabox.
	 *
	 * @param  string $name
	 * @param  $selected
	 * @param  $options
	 * @param  string $nonce_action
	 * @param  string $nonce_name
	 * @param  $echo
	 * @return string
	 */
	private function print_language_select( string $name, $selected, $options, string $nonce_action, string $nonce_name, $echo = true ) : string {
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

	/**
	 * Verify a nonce.
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
}
