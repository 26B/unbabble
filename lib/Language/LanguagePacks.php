<?php

namespace TwentySixB\WP\Plugin\Unbabble\Language;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * For hooks related to the language packs.
 *
 * @since 0.0.0
 */
class LanguagePacks {
	public function register() {
		if ( Options::only_one_language_allowed() ) {
			return;
		}

		\add_action
		( 'admin_init', [ $this, 'install_lang_packs' ] );
	}

	// Check if all language packs are installed and install if needed.
	public function install_lang_packs() : void {
		if ( ! \current_user_can( 'install_languages' ) ) {
			return;
		}
		if ( ! function_exists( 'wp_can_install_language_pack' ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		}
		if ( ! \wp_can_install_language_pack() ) {
			return;
		}

		$allowed_languages      = Options::get()['allowed_languages'];
		$installed_languages    = array_merge( [ 'en_US' ], \get_available_languages() );
		$missing_language_packs = array_diff( $allowed_languages, $installed_languages );
		error_log( print_r( $missing_language_packs, true ) );
		if ( empty( $missing_language_packs ) ) {
			return;
		}
		foreach ( $missing_language_packs as $lang_code ) {
			$language = \wp_download_language_pack( $lang_code );
			if ( $language === false ) {
				error_log( "Failure to download language pack for language {$lang_code}." );
			}
		}
	}
}
