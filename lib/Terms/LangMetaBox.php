<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Term;

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

		// Term meta box.
		\add_action( 'admin_init', [ $this, 'add_ubb_meta_box' ] );
	}

	/**
	 * Adds term metabox for inputs/actions for language/translations.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function add_ubb_meta_box() : void {

		// Filter for translatable taxonomies.
		$taxonomies = array_intersect( \get_taxonomies(), LangInterface::get_translatable_taxonomies() );

		/**
		 * Filters taxonomies that will have the language meta box.
		 *
		 * @since 0.0.1
		 *
		 * @param string[] $taxonomies
		 */
		$taxonomies = \apply_filters( 'ubb_translatable_taxonomies', $taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			\add_action( "{$taxonomy}_add_form_fields", [ $this, 'new_term_language_metabox' ] );
			\add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_term_language_metabox' ] );
			\add_action( "edit_{$taxonomy}", [ $this, 'save_term_language' ] );
			\add_action( "create_{$taxonomy}", [ $this, 'save_term_language' ] );
		}
	}

	/**
	 * Prints metabox for when a new term is being created.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function new_term_language_metabox() : void {
		printf(
			'<div class="form-field term-language-wrap">
				<label for="tag-language">%1$s</label>
				%2$s
				<p>%3$s</p>
			</div>',
			esc_html__( 'Language', 'unbabble' ),
			$this->print_language_select( 'ubb_lang', LangInterface::get_current_language(), LangInterface::get_languages(), 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' )
		);

		if ( is_numeric( $_GET['ubb_source'] ?? '' ) ) {
			printf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);
		}
	}

	/**
	 * Prints metabox for when an existing term is being edited.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Term $term
	 * @return void
	 */
	public function edit_term_language_metabox( WP_Term $term ) : void {
		$lang                = LangInterface::get_term_language( $term->term_id );
		$translations        = LangInterface::get_term_translations( $term->term_id );
		$available_languages = array_flip( LangInterface::get_languages() );

		$query_args = $_GET;
		unset( $query_args['lang'] );

		// Display existing translations.
		$translation_to_show = [];
		$seen_languages      = [];
		foreach ( $translations as $translation_id => $translation_lang ) {
			if ( ! LangInterface::is_language_allowed( $translation_lang ) ) {
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
			$term_link = get_edit_term_link( $translation_id );
			if ( $term->taxonomy === 'nav_menu' ) {
				$term_link = admin_url( "nav-menus.php?action=edit&menu={$translation_id}" );
			}
			$translation_to_show[] = sprintf(
				'<tr><td>%1$s</td><td><a href="%2$s" >Edit</a>%3$s</td></tr>',
				$translation_lang,
				add_query_arg( 'lang', $translation_lang, $term_link ),
				! $duplicate_language ? '' : ' <b style="color:FireBrick">Duplicate</b>',
			);
		}

		$translations_string = sprintf( '<p><b>No Translations</b></p>' );;
		if ( $translation_to_show ) {
			$translations_string = sprintf(
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
		}

		unset( $available_languages[ $lang ] );

		$available_languages = array_keys( $available_languages );

		printf(
			'<tr class="form-field term-language-wrap">
				<th scope="row"><label for="language">%1$s</label></th>
				<td>
					%2$s
					<p class="description">%3$s</p>
				</td>
			</tr>
			<tr class="form-field term-language-wrap-1">
				<th scope="row"><label for="language">%4$s</label></th>
				<td>
				%5$s
				</td>
			</tr>',
			esc_html__( 'Language', 'unbabble' ),
			$this->print_language_select( 'ubb_lang', $lang, LangInterface::get_languages(), 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' ),
			esc_html__( 'Translations', 'unbabble' ),
			$translations_string,
		);

		if ( ! empty( $available_languages ) ) {
			printf(
				'<tr class="form-field term-language-wrap-2">
					<th scope="row"><label for="language">%1$s</label></th>
					<td>
					%2$s
					<input type="submit" name="ubb_redirect_new" value="Save and Create" class="button"/>
					<input type="submit" disabled name="ubb_copy_new" value="Save and Copy" class="button"/>
					</td>
				</tr>',
				esc_html__( 'Create Translation', 'unbabble' ),
				$this->print_language_select( 'ubb_create', '', $available_languages, '', '', false ),
			);
		}

		// Linking.
		$options = array_reduce(
			$this->get_possible_links( $term, $lang ),
			fn ( $carry, $data ) => $carry . sprintf( "<option value='%s'>%s</option>\n", $data[0], $data[1] ),
			! $translation_to_show ? '' : sprintf( "<option value='%s'>%s</option>\n", 'unlink', __( 'Unlink from translations', 'unbabble' ) )
		);

		printf(
			'<tr class="form-field term-language-wrap-3">
			<th scope="row"><label for="language">%1$s</label></th>
			<td>
			<input list="ubb_link_translations_list" id="ubb_link_translation" name="ubb_link_translation" placeholder="%2$s">
			<datalist id="ubb_link_translations_list">%3$s</datalist>
			</td>
			</tr>',
			esc_html__( 'Linked to:', 'unbabble' ),
			__( 'Unchanged', 'unbabble' ),
			$options
		);
	}

	/**
	 * Sets the language to the saved term.
	 *
	 * If it's already set, it will not change.
	 *
	 * @since 0.0.1
	 *
	 * @param  int $term_id
	 * @return void
	 */
	public function save_term_language( int $term_id ) : void {
		if ( ! $this->verify_nonce( 'ubb_language_metabox', 'ubb_language_metabox_nonce' ) ) {
			return;
		}

		if ( isset( $_POST['ubb_copy_new'] ) ) { // Don't set post language when creating a translation.
			return;
		}

		// TODO: Check the user's permissions.

		// Sanitize the user input.
		$lang = \sanitize_text_field( $_POST['ubb_lang'] );

		LangInterface::set_term_language( $term_id, $lang );
	}

	/**
	 * Print language select for the language metabox.
	 *
	 * @since 0.0.1
	 * @todo duplicated in Posts/LangMetaBox
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
			implode( '', $langs )
		);

		return ! $echo ? $output : printf( $output );
	}

	/**
	 * Verifies a nonce.
	 *
	 * @since 0.0.1
	 * @todo duplicated in Posts/LangMetaBox
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
	 * Get possible terms for the $term to link to.
	 *
	 * @since 0.0.1
	 *
	 * @param WP_Term $term
	 * @param string  $term_lang
	 * @return array
	 */
	private function get_possible_links( WP_Term $term, string $term_lang ) : array {
		global $wpdb;
		$translations_table    = ( new TermTable() )->get_table_name();
		$allowed_languages_str = implode( "','", LangInterface::get_languages() );

		$possible_sources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MIN(A.term_id) as term_id, GROUP_CONCAT( CONCAT(A.name, '(', locale, ')' ) ) as group_label
				FROM (
					SELECT TT.term_id, T.name, locale, IFNULL(meta_value, TT.term_id) AS source
					FROM {$translations_table} AS TT
					LEFT JOIN {$wpdb->termmeta} AS TM ON (TT.term_id = TM.term_id AND meta_key = 'ubb_source')
					INNER JOIN {$wpdb->terms} as T ON (TT.term_id = T.term_id)
					INNER JOIN {$wpdb->term_taxonomy} as TAX ON(TAX.term_id = T.term_id)
					WHERE taxonomy = %s
					AND TT.locale IN ('{$allowed_languages_str}')
				) AS A
				WHERE locale != %s
				AND source NOT IN (
					SELECT IFNULL(meta_value, TT.term_id) AS source
					FROM {$translations_table} AS TT
					LEFT JOIN {$wpdb->termmeta} AS TM ON (TM.term_id = TT.term_id AND meta_key = 'ubb_source')
					WHERE locale = %s
				) GROUP BY source",
				$term->taxonomy,
				$term_lang,
				$term_lang
			)
		);

		$options = [];
		foreach ( $possible_sources as $source ) {
			$options[] = [ $source->term_id, $source->group_label ];
		}
		return $options;
	}
}
