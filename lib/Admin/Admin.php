<?php

namespace TwentySixB\WP\Plugin\Unbabble\Admin;

use TwentySixB\WP\Plugin\Unbabble\Options;
use TwentySixB\WP\Plugin\Unbabble\Plugin;

/**
 * The dashboard-specific functionality of the plugin
 *
 * @since 0.0.0
 */
class Admin {

	/**
	 * The plugin's instance.
	 *
	 * @since  0.0.0
	 * @access private
	 * @var    Plugin
	 */
	private $plugin;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 0.0.0
	 * @param Plugin $plugin This plugin's instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.0.0
	 */
	public function register() {

		// Handle language switching.
		if ( is_admin() && isset( $_GET['ubb_switch_lang'] ) ) {
			$this->handle_language_switch_redirect( $_GET['ubb_switch_lang'] );
		}

		// Whitelist 'lang' as a query_var.
		add_filter( 'query_vars', function( $query_vars ) {
			$query_vars[] = 'lang';
			return $query_vars;
		} );

		\add_action( 'admin_init', [ $this, 'action_callback' ] );
		\add_action( 'admin_init', [ $this, 'update_lang_cookie' ] );
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts'] );
		\add_action( 'set_auth_cookie', [ $this, 'set_lang_cookie_on_login' ], PHP_INT_MAX, 3 );
		\add_action( 'wp_logout', [ $this, 'unset_lang_cookie_on_logout' ] );
		\add_action( 'update_option_ubb_options', [ $this, 'set_lang_cookie_on_options_save' ], 10, 0 );
	}

	/**
	 * My action callback.
	 *
	 * @since  0.0.0
	 * @return void
	 */
	public function action_callback() {}

	public function enqueue_scripts() : void {
		\wp_enqueue_script( 'ubb-admin', plugin_dir_url( dirname( __FILE__, 1 ) ) . 'src/scripts/ubb-admin.js', [], '0.0.0', true );
	}

	public function handle_language_switch_redirect( string $new_lang ) : void {
		$options = Options::get();

		// Validate new_lang.
		if ( ! in_array( $new_lang, $options['allowed_languages'], true ) ) {
			return;
		}

		// Update cookie with new lang.

		// Try to get cookie with expiration time. Otherwise use User Session default expiration time.
		$expiration = $this->get_lang_expire_cookie();
		setcookie( 'ubb_lang', $new_lang, $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );

		// Change `ubb_switch_lang` in query in url to `lang` if not default language.
		$path    = str_replace(
			"ubb_switch_lang={$new_lang}",
			$new_lang === $options['default_language'] ? '' : "lang={$new_lang}",
			$_SERVER['REQUEST_URI']
		);
		$new_url = home_url( $path );

		// Redirect.
		nocache_headers();
		wp_safe_redirect( $new_url, 302, 'WordPress - Unbabble' );
		exit;
	}

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
			$expiration   = $this->get_lang_expire_cookie();
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
			$expiration = $this->get_lang_expire_cookie();
			setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
			return;
		}
	}

	public function set_lang_cookie_on_login( string $auth_cookie, int $expire, int $expiration ) : void {
		$options = Options::get();
		setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
		setcookie( 'ubb_lang_expire', $expiration, $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
	}

	public function unset_lang_cookie_on_logout() : void {
		unset( $_COOKIE['ubb_lang'], $_COOKIE['ubb_lang_expire'] );
		setcookie( 'ubb_lang', '', 1, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
		setcookie( 'ubb_lang_expire', '', 1, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
	}

	/**
	 * Handle cookie change when options are saved.
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
			$expiration = $this->get_lang_expire_cookie();
			setcookie( 'ubb_lang', $options['default_language'], $expiration, '/', $_SERVER['HTTP_HOST'], is_ssl(), true );
			return;
		}
	}

	private function get_lang_expire_cookie() : int {
		if (
			isset( $_COOKIE['ubb_lang_expire'] )
			// Verify if its a Unix Timestamp.
			&& ( (string) (int) $_COOKIE['ubb_lang_expire'] === $_COOKIE['ubb_lang_expire'] )
			&& ( $_COOKIE['ubb_lang_expire'] <= PHP_INT_MAX )
			&& ( $_COOKIE['ubb_lang_expire'] >= ~PHP_INT_MAX )
		) {
			return $_COOKIE['ubb_lang_expire'];
		}

		return \apply_filters( 'ubb_lang_cookie_expire_time', time() + 14 * DAY_IN_SECONDS );
	}
}
