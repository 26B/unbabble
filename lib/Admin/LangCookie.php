<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\Options;

/**
 * Hooks for the admin language cookie.
 *
 * @since 0.0.1
 */
class LangCookie {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.1
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		\add_action( 'admin_init', [ $this, 'update_lang_cookie' ] );
		\add_action( 'set_auth_cookie', [ $this, 'set_lang_cookie_on_login' ], PHP_INT_MAX, 3 );
		\add_action( 'wp_logout', [ $this, 'unset_lang_cookie_on_logout' ] );
		\add_action( 'update_option_ubb_options', [ $this, 'set_lang_cookie_on_options_save' ], 10, 0 );
	}

	/**
	 * Updates the language cookie.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function update_lang_cookie() : void {
		/**
		 * Query var 'lang' has precedence over the `ubb_lang` cookie to allow for link sharing of
		 * different languages.
		 */
		$lang_url = $_GET['lang'] ?? '';
		$options  = Options::get();
		$redirect = false;

		/**
		 * If lang in url is no longer allowed, then set cookie to default language and redirect to
		 * current url with default language.
		 */
		if ( ! empty( $lang_url ) && ! in_array( $lang_url, $options['allowed_languages'], true ) ) {
			$lang_url = ''; // Force default language.
			$redirect = true;
		}

		// TODO: Conditions might not handle all cases.
		if (
			! isset( $_COOKIE['ubb_lang'] )
			|| $redirect
			|| ( $_COOKIE['ubb_lang'] !== $lang_url && ! empty( $lang_url ) )
		) {

			// Don't set cookie when only one language is allowed.
			if ( count( $options['allowed_languages'] ) < 2 ) {
				return;
			}

			// Temporary expiration time. User Session default.
			$expiration   = self::get_lang_expire_cookie();
			$cookie_value = empty( $lang_url ) ? $options['default_language'] : $lang_url;
			setcookie( 'ubb_lang', $cookie_value, $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );

			// TODO: Need to be careful with redirect when this hook is called from admin-ajax/heartbeat.
			if ( $redirect ) {
				$path    = str_replace( "lang={$_GET['lang']}", "lang={$cookie_value}", $_SERVER['REQUEST_URI'] );
				$new_url = home_url( $path );
				nocache_headers();
				wp_safe_redirect( $new_url, 302, 'WordPress - Unbabble' );
				exit;
			}
			return;
		}

		/**
		 * If there is a cookie, but the cookie value is a language that is no longer allowed, then
		 * update the cookie with the default language.
		 */
		$lang_cookie = $_COOKIE['ubb_lang'];
		if ( ! in_array( $lang_cookie, $options['allowed_languages'], true ) ) {

			// Try to get cookie with expiration time. Otherwise use User Session default expiration time.
			$expiration = self::get_lang_expire_cookie();
			setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
			return;
		}
	}

	/**
	 * On login, sets language cookie to the default language and its expiration cookie.
	 *
	 * @since 0.0.1
	 *
	 * @see set_auth_cookie
	 * @param string $auth_cookie
	 * @param int    $expire
	 * @param int    $expiration The time when the authentication cookie expires as a UNIX timestamp.
	 * @return void
	 */
	public function set_lang_cookie_on_login( string $auth_cookie, int $expire, int $expiration ) : void {
		$options = Options::get();
		setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
		setcookie( 'ubb_lang_expire', $expiration, $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
	}

	/**
	 * On logout, unsets the language cookie and the language expiration cookie.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function unset_lang_cookie_on_logout() : void {
		unset( $_COOKIE['ubb_lang'], $_COOKIE['ubb_lang_expire'] );
		setcookie( 'ubb_lang', '', 1, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
		setcookie( 'ubb_lang_expire', '', 1, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
	}

	/**
	 * Sets the language cookie when the unbabble options are saved.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function set_lang_cookie_on_options_save() : void {
		$options = Options::get();

		// If cookie doesn't exist and there is more than one allowed language.
		if ( ! isset( $_COOKIE['ubb_lang'] ) ) {
			if ( count( $options['allowed_languages'] ) < 2 ) {
				return;
			}

			// Temporary expiration time. User Session default.
			$expiration = time() + 14 * DAY_IN_SECONDS;
			setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
			return;
		}

		// If cookie value was removed from allowed languages.
		$lang_cookie = $_COOKIE['ubb_lang'];
		if ( ! in_array( $lang_cookie, $options['allowed_languages'], true ) ) {

			// Try to get cookie with expiration time. Otherwise use User Session default expiration time.
			$expiration = self::get_lang_expire_cookie();
			setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
			return;
		}
	}

	/**
	 * Returns value for the language expiration cookie.
	 *
	 * This cookie is needed to maintain the main language cookie expiration date matched with the
	 * user session expiration date which cannot be easily retrieved.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public static function get_lang_expire_cookie() : int {
		if (
			isset( $_COOKIE['ubb_lang_expire'] )
			// Verify if its a Unix Timestamp.
			&& ( (string) (int) $_COOKIE['ubb_lang_expire'] === $_COOKIE['ubb_lang_expire'] )
			&& ( $_COOKIE['ubb_lang_expire'] <= PHP_INT_MAX )
			&& ( $_COOKIE['ubb_lang_expire'] >= ~PHP_INT_MAX )
		) {
			return $_COOKIE['ubb_lang_expire'];
		}

		/**
		 * Filters the Unbabble's language cookie's expire time.
		 *
		 * @since 0.0.1
		 * @param int $expire_time
		 */
		return \apply_filters( 'ubb_lang_cookie_expire_time', time() + 14 * DAY_IN_SECONDS );
	}
}
