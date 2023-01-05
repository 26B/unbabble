<?php

namespace TwentySixB\WP\Plugin\Unbabble\Router;

use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use WP_Post;
use WP_Term;

/**
 * Hooks for resolving routing type functionalities.
 *
 * @since 0.0.3
 * @todo Refactor all the repetitive methods into a single reusable method.
 */
class RoutingResolver {

	/**
	 * Register hooks.
	 *
	 * @since 0.0.3
	 */
	public function register() {
		if ( ! Options::should_run_unbabble() ) {
			return;
		}

		if ( ! is_admin() ) {
			// Needs to be done as early as possible.
			$this->init();

			\add_filter( 'pre_get_posts', [ $this, 'homepage_default_lang_redirect' ], 1 );
		}

		// Post Permalinks:
		$allowed_post_types = Options::get_allowed_post_types();

		if ( in_array( 'post', $allowed_post_types, true ) ) {
			// Post permalink.
			\add_filter( 'post_link', [ $this, 'apply_lang_to_post_url' ], 10, 2 );
		}

		if ( in_array( 'page', $allowed_post_types, true ) ) {
			// Page permalink.
			\add_filter( 'page_link', [ $this, 'apply_lang_to_post_url' ], 10, 2 );
		}

		if ( in_array( 'attachment', $allowed_post_types, true ) ) {
			// Attachment permalink.
			\add_filter( 'attachment_link', [ $this, 'apply_lang_to_attachment_url' ], 10, 2 );
		}

		// Custom post types permalinks.
		\add_filter( 'post_type_link', [ $this, 'apply_lang_to_custom_post_url' ], 10, 2 );

		// Term archive permalinks.
		\add_filter( 'term_link', [ $this, 'apply_lang_to_term_link' ], 10, 3 );

		// Post archive permalinks.
		\add_filter( 'post_type_archive_link', [ $this, 'post_type_archive_link' ], 10, 2 );

		\add_filter( 'pre_redirect_guess_404_permalink', [ $this, 'pre_redirect_guess_404_permalink' ] );

		\add_filter( 'home_url', [ $this, 'home_url' ], 10, 2 );

		\add_filter( 'network_home_url', [ $this, 'network_home_url' ], 10, 3 );

		add_filter( 'admin_url', [ $this, 'admin_url' ], 10 );
	}

	/**
	 * Initialize router.
	 *
	 * @since 0.0.3
	 * @return void
	 */
	public function init() : void {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'init' ) ) {
			$router->init();
		}
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_post_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $post_link
	 * @param WP_Post|int|mixed $post
	 * @return string
	 */
	public function apply_lang_to_post_url( string $post_link, $post ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_post_url' ) ) {
			return $router->apply_lang_to_post_url( $post_link, $post );
		}
		return $post_link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_custom_post_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $post_link
	 * @param WP_Post $post
	 * @return string
	 */
	public function apply_lang_to_custom_post_url( string $post_link, WP_Post $post ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_custom_post_url' ) ) {
			return $router->apply_lang_to_custom_post_url( $post_link, $post );
		}
		return $post_link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_attachment_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $link
	 * @param int $post_id
	 * @return string
	 */
	public function apply_lang_to_attachment_url( string $link, int $post_id ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_attachment_url' ) ) {
			return $router->apply_lang_to_attachment_url( $link, $post_id );
		}
		return $link;
	}

	/**
	 * Apply routing changes to hook `apply_lang_to_term_link`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $termlink
	 * @param WP_Term $term
	 * @param string $taxonomy
	 * @return string
	 */
	public function apply_lang_to_term_link( string $termlink, WP_Term $term, string $taxonomy ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'apply_lang_to_term_link' ) ) {
			return $router->apply_lang_to_term_link( $termlink, $term, $taxonomy );
		}
		return $termlink;
	}

	/**
	 * Apply routing changes to hook `homepage_default_lang_redirect`.
	 *
	 * @since 0.0.3
	 *
	 * @param WP_Query $query
	 * @return void
	 */
	public function homepage_default_lang_redirect( \WP_Query $query ) : void {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'homepage_default_lang_redirect' ) ) {
			$router->homepage_default_lang_redirect( $query );
			return;
		}
		return;
	}

	/**
	 * Apply routing changes to hook `post_type_archive_link`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $link
	 * @param string $post_type
	 * @return string
	 */
	public function post_type_archive_link( string $link, string $post_type ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'post_type_archive_link' ) ) {
			return $router->post_type_archive_link( $link, $post_type );
		}
		return $link;
	}

	/**
	 * Apply routing changes to hook `pre_redirect_guess_404_permalink`.
	 *
	 * @since 0.0.3
	 *
	 * @param mixed $pre
	 * @return mixed
	 */
	public function pre_redirect_guess_404_permalink( $pre ) {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'pre_redirect_guess_404_permalink' ) ) {
			return $router->pre_redirect_guess_404_permalink( $pre );
		}
		return $pre;
	}

	/**
	 * Apply routing changes to hook `home_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $url
	 * @param string $path
	 * @return string
	 */
	public function home_url( string $url, string $path ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'home_url' ) ) {
			return $router->home_url( $url, $path );
		}
		return $url;
	}

	/**
	 * Apply routing changes to hook `network_home_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $url
	 * @param string $path
	 * @param $orig_scheme
	 * @return string
	 */
	public function network_home_url( string $url, string $path, $orig_scheme ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'network_home_url' ) ) {
			return $router->network_home_url( $url, $path, $orig_scheme );
		}
		return $url;
	}

	/**
	 * Apply routing changes to hook `admin_url`.
	 *
	 * @since 0.0.3
	 *
	 * @param string $lang
	 * @return string
	 */
	public function admin_url( string $url ) : string {
		$router = $this->get_current_router_object();
		if ( $router !== null && method_exists( $router, 'admin_url' ) ) {
			return $router->admin_url( $url );
		}
		return $url;
	}

	/**
	 * Returns the current blog's router object.
	 *
	 * @since 0.0.3
	 *
	 * @return ?object
	 */
	private function get_current_router_object() : ?object {
		$router_type  = Options::get()['router'];
		$router_class = null;
		switch ( $router_type ) {
			case 'directory':
				$router_class = new Directory();
				break;
			case 'query_var':
				$router_class = new QueryVar();
				break;
		}
		return $router_class;
	}
}
