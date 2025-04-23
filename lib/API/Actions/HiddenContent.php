<?php

namespace TwentySixB\WP\Plugin\Unbabble\API\Actions;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
use TwentySixB\WP\Plugin\Unbabble\DB\TermTable;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Options;
use TwentySixB\WP\Plugin\Unbabble\Plugin;
use WP_REST_Request;
use WP_REST_Response;

class HiddenContent {

	/**
	 * The namespace.
	 *
	 * @since 0.0.0
	 * @var   string
	 */
	protected $namespace;

	public function __construct( Plugin $plugin, string $namespace ) {
		$this->namespace = $namespace;
	}

	public function register() {
		\register_rest_route(
			$this->namespace,
			'/default_on_hidden/posts',
			[
				'methods'             => 'GET', // TODO: change to POST when called via js
				'callback'            => [ $this, 'set_default_lang_on_hidden_posts' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
		\register_rest_route(
			$this->namespace,
			'/default_on_hidden/terms',
			[
				'methods'             => 'GET', // TODO: change to POST when called via js
				'callback'            => [ $this, 'set_default_lang_on_hidden_terms' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $wp_request ) : bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function set_default_lang_on_hidden_posts( WP_REST_Request $request ) : WP_REST_Response {
		global $wpdb;
		\add_filter( 'ubb_do_hidden_languages_filter', '__return_false' );

		$allowed_languages       = implode( "','", LangInterface::get_languages() );
		$translatable_post_types = LangInterface::get_translatable_post_types();
		$translations_table      = ( new PostTable() )->get_table_name();

		$post_types = $request->get_param( 'post-types' ) ?? $translatable_post_types;
		if ( is_string( $post_types ) ) {
			$post_types = array_filter(
				explode( ',', $post_types ),
				function ( $post_type ) use ( $translatable_post_types ) {
					if ( ! is_string( $post_type ) ) {
						return false;
					}
					if ( ! \post_type_exists( $post_type ) ) {
						return false;
					}
					return in_array( $post_type, $translatable_post_types, true );
				}
			);
		}
		$post_types = implode( "','", $post_types );

		$focus = $request->get_param( 'focus' ) ?? 'all';
		if ( $focus === 'missing' ) {
			$where_focus = "ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
			)";

		} else if ( $focus === 'unknown' ) {
			$where_focus = "ID IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale NOT IN ('{$allowed_languages}')
			)";

		} else if ( $focus === 'all' ) {
			$where_focus = $where_focus = "ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale IN ('{$allowed_languages}')
			)";
		} else {
			$response = new WP_REST_Response( '', 500 );
			return $response;
		}

		$bad_posts          = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->posts} as P
			WHERE post_status != 'auto-draft'
			AND post_type IN ('{$post_types}')
			AND {$where_focus}",
			OBJECT
		);

		$default_lang       = LangInterface::get_default_language();
		$successful         = 0;
		$success_post_type  = [];
		$fail_post_type     = [];
		foreach ( $bad_posts as $post ) {
			$success = LangInterface::set_post_language( $post->ID, $default_lang, true );
			if ( ! $success ) {
				$fail_post_type[ $post->post_type ] = 1 + ( $fail_post_type[ $post->post_type ] ?? 0 );
				continue;
			}
			$success_post_type[ $post->post_type ] = 1 + ( $success_post_type[ $post->post_type ] ?? 0 );
			$successful += 1;
		}

		// TODO: what to show in message if $successful !== count( $bad_posts ) ?

		$data = [
			'message'    => "Updated {$successful} posts with the default language.",
			'successful' => $success_post_type,
			'failed'     => $fail_post_type,
		];

		$response = new WP_REST_Response( $data, 200 );
		return $response;
	}

	public function set_default_lang_on_hidden_terms( WP_REST_Request $request ) : WP_REST_Response {
		global $wpdb;
		\add_filter( 'ubb_do_hidden_languages_filter', '__return_false' );

		$allowed_languages       = implode( "','", LangInterface::get_languages() );
		$translatable_taxonomies = LangInterface::get_translatable_taxonomies();
		$translations_table      = ( new TermTable() )->get_table_name();

		$taxonomies = $request->get_param( 'taxonomies' ) ?? $translatable_taxonomies;
		if ( is_string( $taxonomies ) ) {
			$taxonomies = array_filter(
				explode( ',', $taxonomies ),
				function ( $taxonomy ) use ( $translatable_taxonomies ) {
					if ( ! is_string( $taxonomy ) ) {
						return false;
					}
					if ( ! \taxonomy_exists( $taxonomy ) ) {
						return false;
					}
					return in_array( $taxonomy, $translatable_taxonomies, true );
				}
			);
		}
		$taxonomies = implode( "','", $taxonomies );

		$focus = $request->get_param( 'focus' ) ?? 'all';
		if ( $focus === 'missing' ) {
			$where_focus = "T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as PT
			)";

		} else if ( $focus === 'unknown' ) {
			$where_focus = "T.term_id IN (
				SELECT term_id
				FROM {$translations_table} as PT
				WHERE PT.locale NOT IN ('{$allowed_languages}')
			)";

		} else if ( $focus === 'all' ) {
			$where_focus = $where_focus = "T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as PT
				WHERE PT.locale IN ('{$allowed_languages}')
			)";
		} else {
			$response = new WP_REST_Response( '', 500 );
			return $response;
		}

		$bad_terms = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->terms} as T
			INNER JOIN {$wpdb->term_taxonomy} as TT ON (T.term_id = TT.term_id)
			WHERE T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as TR
				WHERE TR.locale IN ('{$allowed_languages}')
			) AND TT.taxonomy IN ('{$taxonomies}')
			AND {$where_focus}",
			OBJECT
		);

		$default_lang      = LangInterface::get_default_language();
		$successful        = 0;
		$success_taxonomy = [];
		$fail_taxonomy    = [];
		foreach ( $bad_terms as $term ) {
			$success = LangInterface::set_term_language( $term->term_id, $default_lang, true );
			if ( ! $success ) {
				$fail_taxonomy[ $term->taxonomy ] = 1 + ( $fail_taxonomy[ $term->taxonomy ] ?? 0 );
				continue;
			}
			$success_taxonomy[ $term->taxonomy ] = 1 + ( $success_taxonomy[ $term->taxonomy ] ?? 0 );
			$successful += 1;
		}

		// TODO: what to show in message if $successful !== count( $bad_terms ) ?

		$data = [
			'message'    => "Updated {$successful} terms with the default language.",
			'successful' => $success_taxonomy,
			'failed'     => $fail_taxonomy,
		];

		$response = new WP_REST_Response( $data, 200 );
		return $response;
	}
}
