<?php

namespace TwentySixB\WP\Plugin\Unbabble;

/**
 * Handle Language switching for backoffice and frontend.
 *
 * @since 0.0.0
 */
class LanguageSwitcher {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {
		// TODO: Frontend

		// Backoffice.
		if ( is_admin() ) {
			add_action( 'admin_bar_menu', [ $this, 'add_switcher_backoffice_admin_bar' ], PHP_INT_MAX - 10 );
		}
	}

	public function add_switcher_backoffice_admin_bar( \WP_Admin_Bar $wp_admin_bar ) : void {
		$options = Options::get();
		$current = $_GET['lang'] ?? $_COOKIE['ubb_lang'] ?? $options['default_language'];

		$make_lang_url = function ( $lang ) {
			$params = $_GET;
			unset( $params['lang'], $params['ubb_switch_lang'] );
			$params['ubb_switch_lang'] = $lang;
			$query                     = http_build_query( $params );
			if ( empty( $query ) ) {
				return $_SERVER['PHP_SELF'];
			}
			return $_SERVER['PHP_SELF'] . '?' . $query;
		};

		$langs = array_map(
			function ( $lang ) use ( $make_lang_url, $current ) {
				return sprintf(
					'<option value="%1$s" %2$s>%3$s</option>',
					$make_lang_url( $lang ),
					\selected( $lang, $current, false ),
					$lang
				);
			},
			// TODO: This shouldn't happen. Should always be array.
			is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : []
		);

		$html = sprintf(
			'<select onChange="window.location.href=this.value" style="width:4.5em;">
				%1$s
			</select>',
			implode( '', $langs )
		);

		$wp_admin_bar->add_node(
			(object) [
				'id'     => 'ubb_lang_switcher',
				'title'  => $html,
				'parent' => '',
				'href'   => '',
				'group'  => '',
				'meta'   => [],
			]
		);
	}
}
