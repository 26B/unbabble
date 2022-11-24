<?php

namespace TwentySixB\WP\Plugin\Unbabble\Terms;

use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Term;

/**
 * Handle Language Meta Box for Terms.
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

		// Term meta box.
		\add_action( 'admin_init', [ $this, 'term_language_selector' ] );
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
			\add_action( "edit_{$taxonomy}", [ $this, 'save_term_language' ] );
			\add_action( "create_{$taxonomy}", [ $this, 'save_term_language' ] );
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
			$this->print_language_select( 'ubb_lang', LangInterface::get_current_language(), $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
			esc_html__( 'The term only appears on the site for this language.', 'unbabble' )
		);

		if ( is_numeric( $_GET['ubb_source'] ?? '' ) ) {
			printf(
				'<input type="hidden" id="ubb_source" name="ubb_source" value="%s">',
				esc_sql( $_GET['ubb_source'] )
			);
		}
	}

	public function edit_term_language_metabox( $term ) {
		$lang    = LangInterface::get_term_language( $term->term_id );
		$options = Options::get();

		$translations        = LangInterface::get_term_translations( $term->term_id );
		$available_languages = array_flip( $options['allowed_languages'] );

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
				add_query_arg( 'lang', $translation_lang, get_edit_term_link( $translation_id ) ),
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
			$this->print_language_select( 'ubb_lang', $lang, $options['allowed_languages'], 'ubb_language_metabox_nonce', 'ubb_language_metabox', false ),
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

		if ( ! empty( $translation_to_show ) ) {
			printf(
				'<tr class="form-field term-language-wrap-2">
				<th scope="row"><label for="language">%1$s</label></th>
				<td>
				<input type="submit" name="ubb_unlink" value="Unlink from translations" class="button"/>
				</td>
				</tr>',
				esc_html__( 'Unlink from translations:', 'unbabble' ),
			);
		}

		// Linking.
		$options = array_reduce(
			$this->get_possible_links( $term, $lang ),
			fn ( $carry, $data ) => $carry . sprintf( "<option value='%s'>%s</option>\n", $data[0], $data[1] ),
			''
		);
		printf(
			'<tr class="form-field term-language-wrap-3">
			<th scope="row"><label for="language">%1$s</label></th>
			<td>
			<input list="ubb_link_translations_list" id="ubb_link_translation" name="ubb_link_translation">
			<datalist id="ubb_link_translations_list">%2$s</datalist>
			</td>
			</tr>',
			esc_html__( 'Linking translation:', 'unbabble' ),
			$options
		);
	}

	public function save_term_language( $term_id ) {
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

	// TODO: Duplicated in Posts/LangMetaBox
	private function print_language_select( string $name, $selected, $options, string $nonce_action, string $nonce_name, $echo = true ) {
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

	// TODO: Duplicated in Posts/LangMetaBox
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

	private function get_possible_links( WP_Term $term, string $term_lang ) : array {
		global $wpdb;
		$existing_langs        = array_merge( [ $term_lang ], array_values( LangInterface::get_term_translations( $term->term_id ) ) );
		$lang_string           = implode( "','", array_map( fn( $lang ) => esc_sql( $lang ), $existing_langs ) );
		$translations_table    = ( new TermTable() )->get_table_name();
		$allowed_languages_str = implode( "','", Options::get()['allowed_languages'] );

		$possible_terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, name, locale
				FROM (
					SELECT TT.term_id, T.name, locale, IFNULL(meta_value, TT.term_id) AS source
					FROM {$translations_table} AS TT
					LEFT JOIN {$wpdb->termmeta} AS TM ON (TT.term_id = TM.term_id AND meta_key = 'ubb_source')
					INNER JOIN {$wpdb->terms} as T ON (TT.term_id = T.term_id)
					INNER JOIN {$wpdb->term_taxonomy} as TAX ON(TAX.term_id = T.term_id)
					WHERE taxonomy = %s
					AND TT.locale IN ('{$allowed_languages_str}')
				) AS A
				WHERE locale NOT IN ('{$lang_string}')
				AND source NOT IN (
					SELECT IFNULL(meta_value, TT.term_id) AS source
					FROM {$translations_table} AS TT
					LEFT JOIN {$wpdb->termmeta} AS TM ON (TM.term_id = TT.term_id AND meta_key = 'ubb_source')
					WHERE locale IN ('{$lang_string}')
				)",
				$term->taxonomy
			)
		);

		$options = [];
		foreach ( $possible_terms as $term ) {
			$options[] = [ $term->term_id, "{$term->name} ({$term->locale})", ];
		}
		return $options;
	}
}
