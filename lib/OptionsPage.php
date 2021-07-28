<?php

namespace TwentySixB\WP\Plugin\Unbabble;

class OptionsPage {

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
		\register_setting( 'unbabble_options', 'unbabble_options', [ $this, 'test_validate' ] ); //TODO: args
		\add_settings_section( 'languages', 'Languages', '', 'unbabble' );

		\add_settings_field( 'allowed_languages', 'Allowed Languages', [ $this, 'field_allowed_languages' ], 'unbabble', 'languages', [] );
		\add_settings_field( 'default_language', 'Default Language', [ $this, 'field_default_language' ], 'unbabble', 'languages', [] );
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

	public function test_validate( $input ) {
		if ( empty( $input['allowed_languages'] ) ) {
			$input['default_language'] = '';

		} elseif ( ! in_array( $input['default_language'], $input['allowed_languages'], true ) ) {
			$input['default_language'] = current( $input['allowed_languages'] );
		}

		return $input;
	}
}
