<?php

namespace TwentySixB\WP\Plugin\Unbabble\Posts;

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

		// Post meta box.
		\add_action( 'add_meta_boxes', [ $this, 'add_ubb_meta_box' ] );
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
		$lang                 = LangInterface::get_post_language( $post->ID );
		$allowed_languages    = LangInterface::get_languages();
		$translated_languages = array_unique( array_values( LangInterface::get_post_translations( $post->ID ) ) );

		$this->print_language_select( 'ubb_lang', $lang, $allowed_languages, $translated_languages, 'ubb_language_metabox_nonce', 'ubb_language_metabox' );

		if ( is_numeric( $_GET['ubb_source'] ?? '' ) ) {
			printf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);
		}

		printf( '<hr><div id="ubb-language"></div>' );
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
	 * @param  $disabled_options
	 * @param  string $nonce_action
	 * @param  string $nonce_name
	 * @param  $echo
	 * @return string
	 */
	private function print_language_select(
		string $name,
		$selected,
		$options,
		$disabled_options,
		string $nonce_action,
		string $nonce_name,
		$echo = true
	) : string {
		$create_mode = is_numeric( $_GET['ubb_source'] ?? '' );
		$langs       = array_map(
			function ( $text, $lang ) use ( $selected, $create_mode, $disabled_options ) {
				if ( is_int( $text ) ) {
					$text = $lang;
				}
				$selected_str = \selected( $lang, $selected, false );
				return sprintf(
					'<option value="%1$s" %2$s %3$s>%4$s</option>',
					$lang,
					$selected_str,
					in_array( $lang, $disabled_options, true ) || ( $create_mode && empty( $selected_str ) ) ? 'disabled' : '',
					$text
				);
			},
			is_string( $selected ) ? array_keys( $options ) : array_merge( [ __( 'Select Language', 'unbabble' ) ], array_keys( $options ) ),
			is_string( $selected ) ? $options : array_merge( [ '' ], $options )
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
		$translations_table    = ( new PostTable() )->get_table_name();
		$allowed_languages_str = implode( "','", LangInterface::get_languages() );

		$possible_sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MIN(A.post_id) as post_id, GROUP_CONCAT( CONCAT(A.post_title, '(', locale, ')' ) ) as group_label
				FROM (
					SELECT PT.post_id, P.post_title, locale, IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					INNER JOIN {$wpdb->posts} as P ON (PT.post_id = P.ID)
					WHERE post_type = %s AND post_status NOT IN ('revision','auto-draft')
					AND PT.locale IN ('{$allowed_languages_str}')
				) AS A
				WHERE locale != %s
				AND source NOT IN (
					SELECT IFNULL(meta_value, PT.post_id) AS source
					FROM {$translations_table} AS PT
					LEFT JOIN {$wpdb->postmeta} AS PM ON (PT.post_id = PM.post_id AND meta_key = 'ubb_source')
					WHERE locale = %s
				) GROUP BY source",
				$post->post_type,
				$post_lang,
				$post_lang
			)
		);

		$options = [];
		foreach ( $possible_sources as $source ) {
			$options[] = [ $source->post_id, $source->group_label ];
		}
		return $options;
	}
}
