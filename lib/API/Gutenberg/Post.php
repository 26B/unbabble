<?php

namespace TwentySixB\WP\Plugin\Unbabble\API\Gutenberg;

use TwentySixB\WP\Plugin\Unbabble\Integrations\YoastDuplicatePost;
use TwentySixB\WP\Plugin\Unbabble\LangInterface;
use TwentySixB\WP\Plugin\Unbabble\Plugin;
use TwentySixB\WP\Plugin\Unbabble\Posts\LinkTranslation;
use WP_REST_Request;
use WP_REST_Response;

class Post {
	public function __construct( Plugin $plugin, string $namespace ) {
		$this->namespace = $namespace;
	}

	public function register() {
		// create new translation: No need for API, can be done from just a redirect.
		// copy new translation and redirect
		// Linking/Unlinking

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'post_information' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/(?P<lang>[a-zA-Z_-]+)/copy',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'new_translation_copy' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/(?P<translation_id>[0-9]+)/link',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'link_translations' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		\register_rest_route(
			$this->namespace,
			'/edit/post/(?P<id>[0-9]+)/translation/unlink',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'unlink_from_translations' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	public function permission_callback( \WP_REST_Request $wp_request ) : bool {
		return true; // FIXME: remove after testing.
		return is_user_logged_in(); // TODO: rest of permissions
	}

	// TODO: better name.
	public function post_information( \WP_REST_Request $request ) {
		$data    = [];
		$post_id = $request['id'];

		$post_type = get_post_type( $post_id );
		if ( $post_type === 'revision' || ! LangInterface::is_post_type_translatable( $post_type ) ) {
			return new WP_REST_Response( $data, 403 );
		}

		$data['language'] = LangInterface::get_post_language( $post_id );

		$data['translations'] = [];
		$translations = LangInterface::get_post_translations( $post_id );
		foreach ( $translations as $translation_id => $language ) {
			$data['translations'][ $language ] = [
				'ID'   => $translation_id,
				'edit' => get_edit_post_link( $translation_id),
				'view' => get_permalink( $translation_id ),
			];
		}

		return new WP_REST_Response( $data, 200 );
	}

	public function new_translation_copy( \WP_REST_Request $request ) {
		// TODO: Add try catch around code.
		// TODO: check if yoast duplicate is available.
		$post_id = $request['id'];

		$new_post_id = ( new YoastDuplicatePost() )->copy( $post_id, $request['lang'] );
		if ( ! is_int( $new_post_id ) || $new_post_id < 1 ) {
			// TODO: error message
			return new WP_REST_Response( null, 400 );
		}

		$new_post_url = get_edit_post_link( $new_post_id, '' );
		return new WP_REST_Response( null, 200, [ 'Location' => $new_post_url ] );
	}

	public function link_translations( \WP_REST_Request $request ) {
		// TODO: Add try catch around code.
		$post_id        = $request['id'];
		$translation_id = $request['translation_id'];

		$link = ( new LinkTranslation() )->link_translations( $post_id, $translation_id );

		if ( ! $link ) {
			return new WP_REST_Response( null, 400 );
		}

		return new WP_REST_Response( null, 200 );
	}

	public function unlink_from_translations( \WP_REST_Request $request ) {
		// TODO: Add try catch around code.
		$post_id = $request['id'];

		$unlinked = ( new LinkTranslation() )->unlink( $post_id );

		if ( ! $unlinked ) {
			return new WP_REST_Response( null, 400 );
		}

		return new WP_REST_Response( null, 200 );
	}
}
