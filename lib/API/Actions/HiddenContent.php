<?php

namespace TwentySixB\WP\Plugin\Unbabble\API\Actions;

use TwentySixB\WP\Plugin\Unbabble\DB\PostTable;
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
		if ( Options::only_one_language_allowed() ) {
			return;
		}
		\register_rest_route(
			$this->namespace,
			'/default_on_hidden/posts',
			[
				'methods'             => 'GET', // TODO: change to POST
				'callback'            => [ $this, 'set_default_lang_on_hidden_posts' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
		// TODO: route for terms.
	}

	public function permission_callback( \WP_REST_Request $wp_request ) : bool {
		return true; // FIXME: remove this when the call to the action is possible via JS on the backoffice.
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function set_default_lang_on_hidden_posts( WP_REST_Request $request  ) : WP_REST_Response {
		global $wpdb;
		// TODO: Allow for only certain post_types.

		$allowed_languages  = implode( "','", Options::get()['allowed_languages'] );
		$translations_table = ( new PostTable() )->get_table_name();
		$bad_posts          = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->posts} as P
			WHERE ID NOT IN (
				SELECT post_id
				FROM {$translations_table} as PT
				WHERE PT.locale IN ('{$allowed_languages}')
			) AND post_status != 'auto-draft' AND post_type != 'revision'",
			OBJECT
		);

		$default_lang      = Options::get()['default_language'];
		$successful        = 0;
		$success_post_type = [];
		$fail_post_type    = [];
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
}
