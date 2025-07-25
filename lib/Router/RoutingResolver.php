<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Hooks for resolving routing type functionalities.
 *
 * @since 0.6.0 Change most methods to static methods to allow for easy removal of filters.
 * @since 0.0.3
 * @todo Refactor all the repetitive methods into a single reusable method.
 */
class RoutingResolver {

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0 Added one more argument to the hook `rest_url`.
	 * @since 0.0.3
	 */
	public function register() {
		if ( ! is_admin() ) {
			\add_filter( 'pre_get_posts', [ self::class, 'homepage_default_lang_redirect' ], 1 );
		}

		// Post Permalinks:
		$translatable_post_types = LangInterface::get_translatable_post_types();

		if ( in_array( 'post', $translatable_post_types, true ) ) {
			// Post permalink.
			\add_filter( 'post_link', [ self::class, 'apply_lang_to_post_url' ], 10, 2 );
		}

		if ( in_array( 'page', $translatable_post_types, true ) ) {
			// Page permalink.
			\add_filter( 'page_link', [ self::class, 'apply_lang_to_page_url' ], 10, 2 );
		}

		if ( in_array( 'attachment', $translatable_post_types, true ) ) {
			// Attachment permalink.
			\add_filter( 'attachment_link', [ self::class, 'apply_lang_to_attachment_url' ], 10, 2 );
		}

		// Custom post types permalinks.
		\add_filter( 'post_type_link', [ self::class, 'apply_lang_to_custom_post_url' ], 10, 2 );

		// Term archive permalinks.
		\add_filter( 'term_link', [ self::class, 'apply_lang_to_term_link' ], 10, 3 );

		// Post archive permalinks.
		\add_filter( 'post_type_archive_link', [ self::class, 'post_type_archive_link' ], 10, 2 );

		\add_filter( 'pre_redirect_guess_404_permalink', [ self::class, 'pre_redirect_guess_404_permalink' ] );

		\add_filter( 'home_url', [ self::class, 'home_url' ], 10, 3 );

		\add_filter( 'network_home_url', [ self::class, 'network_home_url' ], 10, 3 );

		\add_filter( 'admin_url', [ self::class, 'admin_url' ], 10 );

		\add_filter( 'rest_url', [ self::class, 'rest_url' ], 10, 4 );
	}

	/**
	 * Initialize router.
	 *
	 * @since 0.0.3
	 * @return void
	 */
	public function init() : void {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'init' ) ) {
			$router::init();
		}
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_post_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $post_link
	 * @param WP_Post|int|mixed $post
	 * @return string
	 */
	public static function apply_lang_to_post_url( string $post_link, $post ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_post_url' ) ) {
			return $router::apply_lang_to_post_url( $post_link, $post );
		}
		return $post_link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_page_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.1.1
	 *
	 * @param string $page_link
	 * @param WP_Post|int|mixed $page
	 * @return string
	 */
	public static function apply_lang_to_page_url( string $page_link, $page ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_page_url' ) ) {
			return $router::apply_lang_to_page_url( $page_link, $page );
		}
		return $page_link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_custom_post_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $post_link
	 * @param WP_Post $post
	 * @return string
	 */
	public static function apply_lang_to_custom_post_url( string $post_link, WP_Post $post ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_custom_post_url' ) ) {
			return $router::apply_lang_to_custom_post_url( $post_link, $post );
		}
		return $post_link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_attachment_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $link
	 * @param int $post_id
	 * @return string
	 */
	public static function apply_lang_to_attachment_url( string $link, int $post_id ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_attachment_url' ) ) {
			return $router::apply_lang_to_attachment_url( $link, $post_id );
		}
		return $link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_term_link`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $termlink
	 * @param WP_Term $term
	 * @param string $taxonomy
	 * @return string
	 */
	public static function apply_lang_to_term_link( string $termlink, WP_Term $term, string $taxonomy ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_term_link' ) ) {
			return $router::apply_lang_to_term_link( $termlink, $term, $taxonomy );
		}
		return $termlink;
	}

	/**
	 * Apply routing changes to hook `homepage_default_lang_redirect`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public static function homepage_default_lang_redirect( \WP_Query $query ) : void {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'homepage_default_lang_redirect' ) ) {
			$router::homepage_default_lang_redirect( $query );
			return;
		}
		return;
	}

	/**
	 * Apply routing changes to hook `post_type_archive_link`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $link
	 * @param string $post_type
	 * @return string
	 */
	public static function post_type_archive_link( string $link, string $post_type ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'post_type_archive_link' ) ) {
			return $router::post_type_archive_link( $link, $post_type );
		}
		return $link;
	}

	/**
	 * Apply routing changes to hook `pre_redirect_guess_404_permalink`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function pre_redirect_guess_404_permalink( $pre ) {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'pre_redirect_guess_404_permalink' ) ) {
			return $router::pre_redirect_guess_404_permalink( $pre );
		}
		return $pre;
	}

	/**
	 * Apply routing changes to hook `home_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.5.0 Added $scheme argument.
	 * @since 0.0.3
	 *
	 * @param string      $url
	 * @param string      $path
	 * @param string|null $scheme
	 * @return string
	 */
	public static function home_url( string $url, string $path, ?string $scheme ) : string {

		// TODO: add docs.
		if ( ! apply_filters( 'ubb_apply_lang_to_home_url', true, $url, $path, $scheme ) ) {
			return $url;
		}

		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'home_url' ) ) {
			return $router::home_url( $url, $path, $scheme );
		}
		return $url;
	}

	/**
	 * Apply routing changes to hook `network_home_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $url
	 * @param string $path
	 * @param $orig_scheme
	 * @return string
	 */
	public static function network_home_url( string $url, string $path, $orig_scheme ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'network_home_url' ) ) {
			return $router::network_home_url( $url, $path, $orig_scheme );
		}
		return $url;
	}

	/**
	 * Apply routing changes to hook `admin_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $lang
	 * @return string
	 */
	public static function admin_url( string $url ) : string {
		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'admin_url' ) ) {
			return $router::admin_url( $url );
		}
		return $url;
	}

	/**
	 * Apply routing changes to hook `rest_url`.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @param string $url     REST URL.
	 * @param string $path    REST route.
	 * @param mixed  $blog_id Blog ID.
	 * @param string $scheme  Sanitization scheme.
	 * @return string
	 */
	public static function rest_url( string $url, string $path, $blog_id, string $scheme ) : string {

		// TODO: add docs.
		if ( ! apply_filters( 'ubb_apply_lang_to_rest_url', true, $url, $path, $blog_id, $scheme ) ) {
			return $url;
		}

		$router = self::get_current_router_class();
		if ( $router !== null && method_exists( $router, 'rest_url' ) ) {
			return $router::rest_url( $url, $path, $blog_id, $scheme );
		}
		return $url;
	}

	/**
	 * Returns the current blog's router class.
	 *
	 * @since 0.6.0 Changed to static.
	 * @since 0.0.3
	 *
	 * @return ?string
	 */
	private static function get_current_router_class() : ?string {
		$router_type  = Options::get_router();
		$router_class = null;
		switch ( $router_type ) {
			case 'directory':
				$router_class = Directory::class;
				break;
			case 'query_var':
				$router_class = QueryVar::class;
				break;
		}
		return $router_class;
	}
}
