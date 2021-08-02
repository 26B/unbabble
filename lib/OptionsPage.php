<?php

namespace TwentySixB\WP\Plugin\Unbabble;

class OptionsPage {

	// TODO: replace with full list.
	private $available_langs = [
		'pt_PT',
		'en',
		'es_ES',
	];

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		\add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		\add_action( 'admin_init', [ $this, 'add_options_to_page' ] );
	}

	public function add_options_page() {
		\add_options_page( 'Unbabble', 'Unbabble', 'manage_options', 'unbabble', [ $this, 'page_output' ], null );
	}

	public function add_options_to_page() {
		\register_setting( 'unbabble_options', 'unbabble_options', [ $this, 'validate_options' ] );
		\add_settings_section( 'languages', 'Languages', '', 'unbabble' );
		\add_settings_section( 'post_types', 'Post Types', '', 'unbabble' );
		\add_settings_section( 'taxonomies', 'Taxonomies', '', 'unbabble' );

		\add_settings_field( 'allowed_languages', 'Allowed Languages', [ $this, 'field_allowed_languages' ], 'unbabble', 'languages', [] );
		\add_settings_field( 'default_language', 'Default Language', [ $this, 'field_default_language' ], 'unbabble', 'languages', [] );

		\add_settings_field( 'post_types', 'Select translatable post types', [ $this, 'field_post_types' ], 'unbabble', 'post_types', [] );
		\add_settings_field( 'taxonomies', 'Select translatable taxonomies', [ $this, 'field_taxonomies' ], 'unbabble', 'taxonomies', [] );
	}

	public function page_output() {
		?>
		<h2>Unbabble Settings</h2>
		<form action="options.php" method="post">
			<?php
			\settings_fields( 'unbabble_options' );
			\do_settings_sections( 'unbabble' );
			?>
			<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
		</form>
		<?php
	}

	public function field_allowed_languages() {
		$options = \get_option( 'unbabble_options' );
		$langs   = array_map(
			function ( $lang ) use ( $options ) {
				return sprintf(
					'<option value="%1$s" %2$s>%1$s</option>',
					$lang,
					\selected( in_array( $lang, $options['allowed_languages'], true ), true, false )
				);
			},
			$this->available_langs
		);

		printf(
			'<select multiple id="allowed_languages" name="unbabble_options[allowed_languages][]">
				%s
			</select>',
			implode( '', $langs )
		);
	}

	public function field_default_language() {
		$options = \get_option( 'unbabble_options' );
		$langs   = array_map(
			function ( $lang ) use ( $options ) {
				return sprintf(
					'<option value="%1$s" %2$s>%1$s</option>',
					$lang,
					\selected( $lang, $options['default_language'], false )
				);
			},
			is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : []
		);

		printf(
			'<select id="default_language" name="unbabble_options[default_language]">
				%s
			</select>',
			implode( '', $langs )
		);
	}

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
		$allowed_post_types = Options::get_allowed_post_types();
		$post_type_options  = '';

		foreach ( $post_types as $post_type ) {
			$post_type_options .= sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$post_type,
				\selected( in_array( $post_type, $allowed_post_types, true ), true, false ),
			);
		}

		printf(
			'<select multiple id="post_types" name="unbabble_options[post_types][]" size="%1$s">
			%2$s
			</select>',
			count( $post_types ),
			$post_type_options
		);
	}

	public function field_taxonomies() {
		$taxonomies         = \get_taxonomies();
		$allowed_taxonomies = Options::get_allowed_taxonomies();
		$taxonomies_options = '';

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomies_options .= sprintf(
				'<option value="%1$s" %2$s>%1$s</option>',
				$taxonomy,
				\selected( in_array( $taxonomy, $allowed_taxonomies, true ), true, false ),
			);
		}

		printf(
			'<select multiple id="taxonomies" name="unbabble_options[taxonomies][]" size="%1$s">
			%2$s
			</select>',
			count( $taxonomies ),
			$taxonomies_options
		);
	}
}
