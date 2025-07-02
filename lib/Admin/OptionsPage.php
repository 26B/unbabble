<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;

class OptionsPage {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {

		// Show options page on debug mode or for admins.
		if (
			! \current_user_can( 'manage_options' )
			&& ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG )
		) {
			return;
		}

		\add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		\add_action( 'admin_init', [ $this, 'add_options_to_page' ] );
	}

	public function add_options_page() {
		\add_menu_page(
			'Unbabble',
			'Unbabble',
			'manage_options',
			'unbabble_options',
			[ $this, 'options_page_output' ],
			'dashicons-translation',
			100
		);

		\add_submenu_page(
			'unbabble_options',
			'Settings',
			'Settings',
			'manage_options',
			'unbabble_options',
			[ $this, 'options_page_output' ],
			101
		);

		\add_submenu_page(
			'unbabble_options',
			'Actions',
			'Actions',
			'manage_options',
			'unbabble_actions',
			[ $this, 'actions_page_output' ],
			101
		);
	}

	public function add_options_to_page() {
		\register_setting( 'ubb_options', 'ubb_options', [ $this, 'validate_options' ] );
		\add_settings_section( 'languages', 'Languages', '', 'unbabble' );
		\add_settings_section( 'post_types', 'Post Types', '', 'unbabble' );
		\add_settings_section( 'taxonomies', 'Taxonomies', '', 'unbabble' );
		\add_settings_section( 'router', 'Router', '', 'unbabble' );

		\add_settings_field( 'allowed_languages', 'Allowed Languages', [ $this, 'field_allowed_languages' ], 'unbabble', 'languages', [] );
		\add_settings_field( 'default_language', 'Default Language', [ $this, 'field_default_language' ], 'unbabble', 'languages', [] );

		\add_settings_field( 'post_types', 'Select translatable post types', [ $this, 'field_post_types' ], 'unbabble', 'post_types', [] );
		\add_settings_field( 'taxonomies', 'Select translatable taxonomies', [ $this, 'field_taxonomies' ], 'unbabble', 'taxonomies', [] );

		\add_settings_field( 'router', 'Select routing type', [ $this, 'field_router' ], 'unbabble', 'router', [] );

		// TODO: Field for directory names.
	}

	public function options_page_output() {
		?>
		<div id="ubb-options-page"></div>
		<?php
	}

	/**
	 * Output the actions page.
	 *
	 * @since 0.5.15
	 */
	public function actions_page_output() {
		?>
		<div id="ubb-options-page"></div>
		<?php
	}

	public function field_allowed_languages() {
		$langs = array_map(
			fn ( $lang ) => sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$lang,
				\selected( LangInterface::is_language_allowed( $lang ), true, false )
			),
			array_merge( [ 'en_US' ], \get_available_languages() )
		);

		printf(
			'<select multiple id="allowed_languages" name="ubb_options[allowed_languages][]">
				%s
			</select>',
			implode( '', $langs )
		);
	}

	public function field_default_language() {
		$langs   = array_map(
			fn ( $lang ) => sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$lang,
				\selected( $lang, LangInterface::get_default_language(), false )
			),
			LangInterface::get_languages()
		);

		printf(
			'<select id="default_language" name="ubb_options[default_language]">
				%s
			</select>',
			implode( '', $langs )
		);
	}

	// TODO: move this to Options
	public function validate_options( $input ) {
		if ( empty( $input['allowed_languages'] ) ) {
			$input['default_language'] = '';

		} elseif ( ! in_array( $input['default_language'], $input['allowed_languages'], true ) ) {
			$input['default_language'] = current( $input['allowed_languages'] );
		}

		return $input;
	}

	public function field_post_types() {
		$post_types         = \get_post_types();
		$post_type_options  = '';
		foreach ( $post_types as $post_type ) {
			$post_type_options .= sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$post_type,
				\selected( LangInterface::is_post_type_translatable( $post_type ), true, false ),
			);
		}

		printf(
			'<select multiple id="post_types" name="ubb_options[post_types][]" size="%1$s">
			%2$s
			</select>',
			count( $post_types ),
			$post_type_options
		);
	}

	public function field_taxonomies() {
		$taxonomies         = \get_taxonomies();
		$taxonomies_options = '';
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomies_options .= sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$taxonomy,
				\selected( LangInterface::is_taxonomy_translatable( $taxonomy ), true, false ),
			);
		}

		printf(
			'<select multiple id="taxonomies" name="ubb_options[taxonomies][]" size="%1$s">
			%2$s
			</select>',
			count( $taxonomies ),
			$taxonomies_options
		);
	}

	public function field_router() {
		$router_types    = Options::get_router_types();
		$selected_router = Options::get_router();
		$router_options  = '';
		foreach ( $router_types as $type ) {
			$router_options .= sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$type,
				\selected( $type === $selected_router, true, false ),
			);
		}

		printf(
			'<select id="router" name="ubb_options[router]">
			%1$s
			</select>',
			$router_options
		);
	}
}
