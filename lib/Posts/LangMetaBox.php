<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
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
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		// Post meta box.
		\add_action( 'add_meta_boxes', [ $this, 'post_language_selector' ] );
		\add_action( 'save_post', [ $this, 'save_post_language' ] );
	}

	/**
	 * Adds metabox to select the post language in the post editor.
	 *
	 * @since 0.0.1
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

	/**
	 * Callback to print the metabox for the post language selection in the post editor.
	 *
	 * FIXME: Refactor.
	 *
	 * @since 0.0.1
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

		if ( is_numeric( $_GET['ubb_source'] ?? '' ) ) {
			printf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);
		}

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
				'<hr><p><b>Translations:</b></p>
				<table>
				<tr>
					<th>Language</th>
					<th>Actions</th>
				</tr>
				%s
				</table>
				<hr><details>
					<summary><b>Unlink:</b></summary>
					<input type="submit" %2$s name="ubb_unlink" value="Unlink from translations" class="button"/>
				</details>',
				implode( '', $translation_to_show ),
				$post->post_status === 'draft' ? 'id="save-post"' : ''
			);
		} else {
			printf( '<hr><p><b>No Translations</b></p>' );
		}

		unset( $available_languages[ $lang ] );

		// Can't create more translations currently.
		if ( empty( $available_languages ) ) {
			return;
		}

		$available_languages = array_keys( $available_languages );

		// Display language selector and button to create new translation.
		// TODO: Only show `ubb_copy_new` input if duplicate-post is active. Add filter and move the input to an integration class.
		printf(
			'<hr><details>
				<summary><b>Create Translation</b></summary>
				<div>To: %1$s</div>
				<input type="submit" %2$s name="ubb_redirect_new" value="Save and Create" class="button"/>
				<input type="submit" %2$s name="ubb_copy_new" value="Save and Copy" class="button"/>
			</details>',
			$this->print_language_select( 'ubb_create', '', $available_languages, '', '', false ),
			$post->post_status === 'draft' ? 'id="save-post"' : '',
		);

		$options = array_reduce(
			$this->get_possible_links( $post, $lang ),
			fn ( $carry, $data ) => $carry . sprintf( "<option value='%s'>%s</option>\n", $data[0], $data[1] ),
			''
		);
		printf(
			'<hr><details>
				<summary><b>Linking translation:</b></summary>
				<label>Link to:</label>
				<input list="ubb_link_translations_list" id="ubb_link_translation" name="ubb_link_translation">
				<datalist id="ubb_link_translations_list">%s</datalist>
			</details>',
			$options
		);
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

		if ( ! $this->verify_nonce( 'ubb_language_metabox', 'ubb_language_metabox_nonce' ) ) {
			return;
		}

		if (
			get_post_type( $post_id ) === 'revision'
			|| isset( $_POST['ubb_copy_new'] ) // Don't set post language when copying to a translation.
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
	 * @since 0.0.1
	 *
	 * @param  string $name
	 * @param  $selected
	 * @param  $options
	 * @param  string $nonce_action
	 * @param  string $nonce_name
	 * @param  $echo
	 * @return string
	 */
	private function print_language_select(
		string $name,
		$selected,
		$options,
		string $nonce_action,
		string $nonce_name,
		$echo = true
	) : string {
		$create_mode = is_numeric( $_GET['ubb_source'] ?? '' );
		$langs       = array_map(
			function ( $text, $lang ) use ( $selected, $create_mode ) {
				if ( is_int( $text ) ) {
					$text = $lang;
				}
				$selected_str = \selected( $lang, $selected, false );
				return sprintf(
					'<option value="%1$s" %2$s %3$s>%4$s</option>',
					$lang,
					$selected_str,
					$create_mode && empty( $selected_str ) ? 'disabled' : '',
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
			implode( '', $langs ),
		);

		return ! $echo ? $output : printf( $output );
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
	 * Get possible posts for the $post to link to.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Post $post
	 * @param string  $post_lang
	 * @return array
	 */
	private function get_possible_links( WP_Post $post, string $post_lang ) : array {
		global $wpdb;
		$existing_langs        = array_merge( [ $post_lang ], array_values( LangInterface::get_post_translations( $post->ID ) ) );
		$lang_string           = implode( "','", array_map( fn( $lang ) => esc_sql( $lang ), $existing_langs ) );
		$translations_table    = ( new PostTable() )->get_table_name();
		$allowed_languages_str = implode( "','", Options::get()['allowed_languages'] );

		$possible_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, post_title, locale
				FROM (
					SELECT PT.post_id, P.post_title, locale, IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					INNER JOIN {$wpdb->posts} as P ON (PT.post_id = P.ID)
					WHERE post_type = %s AND post_status NOT IN ('revision','auto-draft')
					AND PT.locale IN ('{$allowed_languages_str}')
				) AS A
				WHERE locale NOT IN ('{$lang_string}')
				AND source NOT IN (
					SELECT IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					WHERE locale IN ('{$lang_string}')
				)",
				$post->post_type
			)
		);

		$options = [];
		foreach ( $possible_posts as $post ) {
			$options[] = [ $post->post_id, "{$post->post_title} ({$post->locale})", ];
		}
		return $options;
	}
}
