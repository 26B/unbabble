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
		return true; // FIXME: remove this when the call to the action is possible via JS on the backoffice.
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function set_default_lang_on_hidden_posts( WP_REST_Request $request ) : WP_REST_Response {
		global $wpdb;
		// TODO: Allow for only certain post_types.

		$allowed_languages  = implode( "','", Options::get()['allowed_languages'] );
		$allowed_post_types = implode( "','", Options::get_allowed_post_types() );
		$translations_table = ( new PostTable() )->get_table_name();

		// TODO: We might only want to place a language in the ones that don't have language, not just on everything that's not allowed.
		$bad_posts          = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->posts} as P
			WHERE ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale IN ('{$allowed_languages}')
			) AND post_status != 'auto-draft'
			AND post_type IN ('{$allowed_post_types}')",
			OBJECT
		);

		$default_lang       = Options::get()['default_language'];
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
		// TODO: Allow for only certain taxonomies.

		$allowed_languages  = implode( "','", Options::get()['allowed_languages'] );
		$allowed_taxonomies = implode( "','", Options::get_allowed_taxonomies() );
		$translations_table = ( new TermTable() )->get_table_name();
		$bad_terms          = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->terms} as T
			INNER JOIN {$wpdb->term_taxonomy} as TT ON (T.term_id = TT.term_id)
			WHERE T.term_id NOT IN (
				SELECT term_id
				FROM {$translations_table} as TR
				WHERE TR.locale IN ('{$allowed_languages}')
			) AND TT.taxonomy IN ('{$allowed_taxonomies}')",
			OBJECT
		);

		$default_lang      = Options::get()['default_language'];
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
