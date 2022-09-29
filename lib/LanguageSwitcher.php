<?php

namespace TwentySixB\WP\Plugin\Unbabble;

use TwentySixB\WP\Plugin\Unbabble\Refactor\LangInterface;
use WP_Post;

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

		// TODO: This shouldn't happen. Should always be array.
		$allowed_languages = is_array( $options['allowed_languages'] ) ? $options['allowed_languages'] : [];

		$langs = [];
		foreach ( $allowed_languages as $allowed_lang ) {
			if ( ! isset( $_REQUEST['post'] ) || ! is_numeric( $_REQUEST['post'] ) ) {
				$url = $this->make_switch_url( $allowed_lang );
			} else {
				$url = $this->make_switch_post_url( $_REQUEST['post'], $allowed_lang );
			}

			$langs[] = sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				$url,
				\selected( $allowed_lang, $current, false ),
				$allowed_lang
			);
		}

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

	/**
	 * Make the default language switch url.
	 *
	 * @param  string $lang
	 * @return string
	 */
	private function make_switch_url( string $lang ) : string {
		$params = $_GET;
		unset( $params['lang'], $params['ubb_switch_lang'] );
		$params['ubb_switch_lang'] = $lang;
		$query                     = http_build_query( $params );
		if ( empty( $query ) ) {
			return $_SERVER['PHP_SELF'];
		}
		return $_SERVER['PHP_SELF'] . '?' . $query;
	}

	/**
	 * Make the post language switch url.
	 *
	 * @param  int    $post_id
	 * @param  string $lang
	 * @return string
	 */
	private function make_switch_post_url( int $post_id, string $lang ) : string {
		$translations   = LangInterface::get_translation_list( $post_id );
		$translation_id = array_search( $lang, $translations, true );
		if ( ! $translation_id ) {
			$post_type = get_post_type( $post_id );
			// TODO: better way to get this
			return get_site_url(
				null,
				sprintf(
					"/wp-admin/edit.php?%slang=%s",
					$post_type === 'post' ? '' : "post_type={$post_type}",
					$lang
				)
			);
		}

		$params = $_GET;
		unset( $params['lang'], $params['ubb_switch_lang'] );
		$params['post']            = $translation_id;
		$params['ubb_switch_lang'] = $lang;
		$query                     = http_build_query( $params );
		if ( empty( $query ) ) {
			return $_SERVER['PHP_SELF'];
		}
		return $_SERVER['PHP_SELF'] . '?' . $query;
	}
}
